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

log() {
  local msg="$1"
  echo "[$(date -u +%Y-%m-%dT%H:%M:%SZ)] ${msg}" | tee -a "${log_file}"
}

log "=== Leopardo RH backup drill ${timestamp} ==="

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
psql "${RESTORE_DB_URL}" -v ON_ERROR_STOP=1 -c "DROP SCHEMA IF EXISTS shared_tenants CASCADE; DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;" >/dev/null

restore_input="${dump_file}"
if [[ "${dump_file}" == *.age ]]; then
  # Restaurer depuis un dump chiffre : on le dechiffre a la volee.
  : "${BACKUP_AGE_IDENTITY_FILE:?BACKUP_AGE_IDENTITY_FILE required to restore age dump}"
  restore_input="$(mktemp)"
  trap 'rm -f "${restore_input}"' EXIT
  age --decrypt --identity "${BACKUP_AGE_IDENTITY_FILE}" --output "${restore_input}" "${dump_file}"
fi

pg_restore \
  --no-owner \
  --no-privileges \
  --dbname="${RESTORE_DB_URL}" \
  "${restore_input}"

# ---------------------------------------------------------------------------
# 4. Verification : on compare quelques tables critiques source vs cible.
# ---------------------------------------------------------------------------
log "[4/4] row count verification"

# Le schema tenant partage s'appelle `shared_tenants`, les tables metier
# vivent dedans ; les tables de plateforme restent dans `public`.
tables=(
  "public.companies"
  "public.plans"
  "public.super_admins"
  "shared_tenants.employees"
  "shared_tenants.attendance_logs"
  "shared_tenants.user_invitations"
)

mismatch=0
for fq in "${tables[@]}"; do
  schema="${fq%%.*}"
  table="${fq##*.}"

  source_count=$(psql "${DATABASE_URL}" -Atc "SELECT COUNT(*) FROM ${schema}.${table};" 2>/dev/null || echo "NA")
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

# Nettoyage de la base scratch pour ne pas garder de donnees sensibles.
psql "${RESTORE_DB_URL}" -c "DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;" >/dev/null 2>&1 || true
psql "${RESTORE_DB_URL}" -c "DROP SCHEMA IF EXISTS shared_tenants CASCADE;" >/dev/null 2>&1 || true

if [[ "${mismatch}" -gt 0 ]]; then
  log "DRILL FAILED: ${mismatch} table(s) out of sync"
  exit 1
fi

log "DRILL PASSED"
