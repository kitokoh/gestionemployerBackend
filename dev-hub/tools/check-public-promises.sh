#!/usr/bin/env bash
# Garde anti-sur-promesse (P03 §5, issue #7058) — scanne la copie publique
# (vitrine + README) contre les promesses interdites du registre MESSAGE
# (docs/REFERENTIEL_PRODUIT/MESSAGE.md). Fail-loud, jamais de warning silencieux.
set -uo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
ERRORS=0

# Cibles : contenu marketing vitrine + README racine (pas les pages légales).
TARGETS=(
  "$ROOT/front/web/src/modules/vitrine"
  "$ROOT/front/web/src/app/(landing)"
  "$ROOT/README.md"
)

# Promesses INTERDITES (FR/EN) — maintenir en synchro avec MESSAGE.md.
PATTERNS=(
  'certifi[ée]s? (RGPD|ISO|paie|payroll)'
  'conformit[ée] l[ée]gale'
  'juridiquement valid[ée]'
  '100[ ]?% conforme'
  'legally compliant'
  'fully compliant'
  'certifi[ée]s? (GDPR|ISO|payroll)'
)

scan() {
  local target="$1"
  [[ -e "$target" ]] || return 0
  for pat in "${PATTERNS[@]}"; do
    while IFS=: read -r file line rest; do
      echo "::error file=${file#$ROOT/},line=${line}::promesse interdite détectée (pattern: ${pat}) — voir docs/REFERENTIEL_PRODUIT/MESSAGE.md"
      ERRORS=$((ERRORS + 1))
    done < <(grep -rniE --include='*.tsx' --include='*.ts' --include='*.md' --include='*.mdx' "$pat" "$target" 2>/dev/null || true)
  done
}

for t in "${TARGETS[@]}"; do scan "$t"; done

if [[ $ERRORS -gt 0 ]]; then
  echo "::error::${ERRORS} promesse(s) interdite(s) dans la copie publique (registre MESSAGE)."
  exit 1
fi
echo "✅ Copie publique conforme au registre MESSAGE (0 promesse interdite)."
