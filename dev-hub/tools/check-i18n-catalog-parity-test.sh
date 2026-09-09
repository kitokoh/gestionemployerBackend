#!/usr/bin/env bash
# ============================================================
# check-i18n-catalog-parity-test.sh — Auto-test de la garde de parité i18n
# (issue #7089). Exécute check-i18n-catalog-parity.sh sur des fixtures :
#   1. rouge attendu quand une langue manque une clé / en a une en trop ;
#   2. vert attendu après réalignement des 4 langues.
# Usage : bash dev-hub/tools/check-i18n-catalog-parity-test.sh
# ============================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-i18n-catalog-parity.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

mkdir -p "$TMP/api/lang/fr" "$TMP/api/lang/en" "$TMP/api/lang/ar" "$TMP/api/lang/tr"

write_lang() { # $1 = langue, $2 = clés supplémentaires ('' ou " 'x.y' => 'v',")
  cat > "$TMP/api/lang/$1/test.php" <<PHP
<?php

return [
    'common.hello' => 'Bonjour',
    'common.world' => 'Monde',$2
];
PHP
}

write_lang fr ""
write_lang en " 'extra.en' => 'Only EN',"
write_lang ar ""
write_lang tr ""

# --- Cas 1 : dérive (en a une clé en trop) → rouge
if bash "$GUARD" "$TMP" >/dev/null 2>&1; then
  echo "FAIL : dérive i18n non détectée (cas 1)" >&2
  exit 1
fi
echo "ok: dérive détectée (clé surnuméraire en)"

# --- Cas 2 : clé manquante (tr perd common.hello) → rouge
write_lang tr " "
sed -i "/common.hello/d" "$TMP/api/lang/tr/test.php"
if bash "$GUARD" "$TMP" >/dev/null 2>&1; then
  echo "FAIL : clé manquante non détectée (cas 2)" >&2
  exit 1
fi
echo "ok: clé manquante détectée (tr)"

# --- Cas 3 : réalignement complet → vert
write_lang fr " 'extra.en' => 'Shared',"
write_lang en " 'extra.en' => 'Shared',"
write_lang ar " 'extra.en' => 'Shared',"
write_lang tr " 'common.hello' => 'Bonjour', 'extra.en' => 'Shared',"
bash "$GUARD" "$TMP" >/dev/null
echo "ok: parité réalignée acceptée"

echo "PASS : check-i18n-catalog-parity-test.sh"
