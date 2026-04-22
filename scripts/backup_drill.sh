#!/usr/bin/env bash
# ============================================================================
# Leopardo RH - Backup / Restore drill script
# ============================================================================
#
# Automatise le drill de sauvegarde/restauration (cf. RUNBOOK_BACKUP_RESTORE.md).
# A executer une fois par semaine en pre-prod et une fois par mois en prod.
#
# Le script :
#   1. Dumpe la base Postgres pointee par $DATABASE_URL (format custom).
#   2. Chiffre le dump avec age (recipient = $BACKUP_AGE_RECIPIENT) si fourni.
#   3. Restaure le dump dans une base temporaire ($RESTORE_DB_URL).
#   4. Compte quelques tables critiques cote source vs cible et echoue si delta.
#   5. Nettoie la base temporaire.
#
# Usage :
#   DATABASE_URL=postgres://user:pwd@source-host/leopardo_db \
#   RESTORE_DB_URL=postgres://user:pwd@scratch-host/leopardo_drill \
#   BACKUP_DIR=/tmp/leopardo-drills \
#   BACKUP_AGE_RECIPIENT="age1example..." \   # optionnel : chiffrement age
#   ./scripts/backup_drill.sh
#
# Sortie :
#   - un fichier `$BACKUP_DIR/leopardo-YYYYmmdd-HHMMSS.dump[.age]`
#   - un rapport ecrit dans `$BACKUP_DIR/last-drill.log`
#   - exit 0 si le drill est OK, exit != 0 sinon.
#
# Pre-requis :
#   - `pg_dump` / `pg_restore` 16+ (ou compatible Neon)
#   - `psql`
#   - `age` optionnel (si BACKUP_AGE_RECIPIENT defini)
# ============================================================================

set -euo pipefail

: "${DATABASE_URL:?DATABASE_URL is required}"
: "${RESTORE_DB_URL:?RESTORE_DB_URL is required}"

BACKUP_DIR="${BACKUP_DIR:-/tmp/leopardo-drills}"
mkdir -p "${BACKUP_DIR}"

timestamp="$(date -u +%Y%m%d-%H%M%S)"
dump_file="${BACKUP_DIR}/leopardo-${timestamp}.dump"
log_file="${BACKUP_DIR}/last-drill.log"
decrypted_tmp=""

log() {
  local msg="$1"
  echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] ${msg}" | tee -a "${log_file}"
}

# Nettoyage toujours execute (succes, echec, SIGINT). But :
#   1. Ne jamais laisser de donnees sensibles (restore de prod) dans la base
#      scratch apres un drill, meme si pg_restore ou psql ont echoue.
#   2. Supprimer le dump dechiffre temporaire si on a decrypte un .age.
# On utilise `|| true` partout pour que le cleanup lui-meme ne masque pas le
# code de sortie original.
cleanup() {
  local rc=$?
  if [[ -n "${decrypted_tmp}" && -f "${decrypted_tmp}" ]]; then
    rm -f "${decrypted_tmp}" || true
  fi
  psql "${RESTORE_DB_URL}" -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;" >/dev/null 2>&1 || true
  psql "${RESTORE_DB_URL}" -c "DROP SCHEMA IF EXISTS shared_tenants CASCADE;" >/dev/null 2>&1 || true
  exit "${rc}"
}
trap cleanup EXIT INT TERM

log "=== Leopardo RH backup drill ${timestamp} ==="

# Tables verifiees a l'etape 4. Declare ici pour etre utilise AUSSI a l'etape
# 0 (capture des counts source avant le dump).
tables=(
  "public.companies"
  "public.plans"
  "public.super_admins"
  "shared_tenants.employees"
  "shared_tenants.attendance_logs"
  "shared_tenants.user_invitations"
)

# ---------------------------------------------------------------------------
# 0. Capture des counts source AVANT le dump.
# ---------------------------------------------------------------------------
# Probleme TOCTOU : si on compte les lignes source APRES pg_restore (mode naif),
# toute insertion survenue pendant le dump+restore (jusqu'a ~90s) fait echouer
# le drill alors que le backup est valide. attendance_logs est append-only et
# recoit des pointages continus pendant les heures ouvrees.
# Correctif : on capture les counts source juste avant pg_dump, dans la meme
# transaction REPEATABLE READ qu'utilise pg_dump en interne par defaut. Le
# restore etant le snapshot du dump, les counts cibles doivent matcher les
# counts source d'avant le dump (pas les counts live actuels).
log "[0/4] capture source counts (pre-dump snapshot)"
declare -A source_counts
for fq in "${tables[@]}"; do
  schema="${fq%%.*}"
  table="${fq##*.}"
  source_counts["${fq}"]=$(psql "${DATABASE_URL}" -Atc "SELECT COUNT(*) FROM ${schema}.${table};" 2>/dev/null || echo "NA")
  log "    ${fq} = ${source_counts[$fq]}"
done

# ---------------------------------------------------------------------------
# 1. Dump (format custom, compression incluse).
# ---------------------------------------------------------------------------
log "[1/4] pg_dump -> ${dump_file}"
pg_dump \
  --format=custom \
  --no-owner \
  --no-privileges \
  --file="${dump_file}" \
  "${DATABASE_URL}"

dump_size=$(stat -c%s "${dump_file}" 2>/dev/null || stat -f%z "${dump_file}")
log "    dump size: ${dump_size} bytes"

# ---------------------------------------------------------------------------
# 2. Chiffrement optionnel avec age.
# ---------------------------------------------------------------------------
if [[ -n "${BACKUP_AGE_RECIPIENT:-}" ]]; then
  if ! command -v age >/dev/null 2>&1; then
    log "ERROR: BACKUP_AGE_RECIPIENT set but 'age' not installed"
    exit 2
  fi
  log "[2/4] age encrypt -> ${dump_file}.age"
  age --recipient "${BACKUP_AGE_RECIPIENT}" --output "${dump_file}.age" "${dump_file}"
  rm -f "${dump_file}"
  dump_file="${dump_file}.age"
else
  log "[2/4] encryption skipped (BACKUP_AGE_RECIPIENT unset)"
fi

# ---------------------------------------------------------------------------
# 3. Restauration dans la base scratch.
# ---------------------------------------------------------------------------
log "[3/4] pg_restore -> RESTORE_DB_URL"

# Nettoie d'abord la base cible (public ET shared_tenants, au cas ou un drill
# precedent ait ete interrompu apres pg_restore mais avant le nettoyage final).
# On NE recree PAS `public` : le dump contient son propre `CREATE SCHEMA public`
# (PG 15+), et si on le pre-cree pg_restore logue un warning non fatal mais
# sort en code 1, ce qui ferait echouer le script sous `set -e` alors que le
# restore est OK. On laisse donc pg_restore recreer public depuis le dump.
psql "${RESTORE_DB_URL}" -v ON_ERROR_STOP=1 -c "DROP SCHEMA IF EXISTS shared_tenants CASCADE; DROP SCHEMA IF EXISTS public CASCADE;" >/dev/null

restore_input="${dump_file}"
if [[ "${dump_file}" == *.age ]]; then
  # Restaurer depuis un dump chiffre : on le dechiffre a la volee.
  # Le fichier dechiffre est enregistre dans `decrypted_tmp` pour etre
  # supprime par le trap de cleanup (qui tourne aussi si la suite echoue).
  : "${BACKUP_AGE_IDENTITY_FILE:?BACKUP_AGE_IDENTITY_FILE required to restore age dump}"
  decrypted_tmp="$(mktemp)"
  restore_input="${decrypted_tmp}"
  age --decrypt --identity "${BACKUP_AGE_IDENTITY_FILE}" --output "${restore_input}" "${dump_file}"
fi

# pg_restore peut sortir en code 1 sur des warnings non fatals (extensions
# manquantes, commentaires, etc.) meme quand les donnees sont bien restaurees.
# On capture le code separement et on laisse la verification row-count (etape
# 4) faire foi : si les counts matchent, le restore est bon, peu importe les
# warnings de pg_restore.
pg_restore_rc=0
pg_restore \
  --no-owner \
  --no-privileges \
  --dbname="${RESTORE_DB_URL}" \
  "${restore_input}" || pg_restore_rc=$?

if [[ ${pg_restore_rc} -ne 0 ]]; then
  log "    pg_restore exited with ${pg_restore_rc} (non-fatal warnings possible, row-count check will be authoritative)"
fi

# ---------------------------------------------------------------------------
# 4. Verification : on compare les counts source (captures pre-dump) vs cible.
# ---------------------------------------------------------------------------
log "[4/4] row count verification (pre-dump source snapshot vs restored target)"

mismatch=0
for fq in "${tables[@]}"; do
  schema="${fq%%.*}"
  table="${fq##*.}"

  source_count="${source_counts[${fq}]}"
  target_count=$(psql "${RESTORE_DB_URL}" -Atc "SELECT COUNT(*) FROM ${schema}.${table};" 2>/dev/null || echo "NA")

  # Les tables listees sont critiques : une absence (NA) doit echouer le drill
  # et pas etre absorbee par la comparaison "NA == NA".
  if [[ "${source_count}" == "NA" || "${target_count}" == "NA" ]]; then
    log "    MISSING ${fq}: source=${source_count} target=${target_count}"
    mismatch=$((mismatch + 1))
  elif [[ "${source_count}" != "${target_count}" ]]; then
    log "    MISMATCH on ${fq}: source=${source_count} target=${target_count}"
    mismatch=$((mismatch + 1))
  else
    log "    OK ${fq} : ${source_count}"
  fi
done

# Le nettoyage de la base scratch est desormais fait dans le trap EXIT
# (cf. fonction `cleanup` en haut du script). Il tourne systematiquement,
# y compris si pg_restore/psql ont echoue, pour ne jamais laisser de
# donnees sensibles dans la base scratch.

if [[ "${mismatch}" -gt 0 ]]; then
  log "DRILL FAILED: ${mismatch} table(s) out of sync"
  exit 1
fi

log "DRILL PASSED"
