#!/usr/bin/env bash
#
# check-web-design-tokens.sh — Garde tokens design web/admin (issue #7062, protocole P05 §3)
#
# Vérifie que les surfaces front/web et front/admin-dashboard n'introduisent pas
# de couleur hors palette ni d'utilitaires legacy contournant le design system.
#
#   - HEX HORS PALETTE (BLOQUANT)  : tout `#rrggbb` hors de
#     docs/REFERENTIEL_PRODUIT/COULEURS.md, sauf tolérance explicite dans
#     dev-hub/tools/web-design-hex-allowlist.txt (raison documentée par entrée).
#   - CLASSES LEGACY (AVERTISSEMENT, V1) : utilitaires génériques
#     (`bg-white`, `rounded-lg`, `shadow`, `text-gray-*`) signalés pour migration
#     vers les tokens/glass — non bloquant en V1 (bruit attendu sur l'existant).
#
# Usage : dev-hub/tools/check-web-design-tokens.sh
# Sortie : 0 = OK, 1 = écart bloquant (hex hors palette non toléré).
set -uo pipefail
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

PALETTE_FILE="docs/REFERENTIEL_PRODUIT/COULEURS.md"
ALLOWLIST_FILE="dev-hub/tools/web-design-hex-allowlist.txt"
SCAN_DIRS="front/web/src front/admin-dashboard/src"
EXT_RE='\.(ts|tsx|js|jsx|vue|css|html)$'

# Palette : hex documentés dans COULEURS.md
grep -oE '#[0-9A-Fa-f]{6}\b' "$PALETTE_FILE" \
  | tr 'A-F' 'a-f' | sort -u > /tmp/palette.$$.txt

# Allowlist : hex tolérés (lignes : "#hex — raison"), commentaires autorisés
grep -oE '#[0-9A-Fa-f]{6}\b' "$ALLOWLIST_FILE" \
  | tr 'A-F' 'a-f' | sort -u > /tmp/allow.$$.txt

# Extraction des hex du code
find $SCAN_DIRS -type f \( -name '*.ts' -o -name '*.tsx' -o -name '*.js' \
  -o -name '*.jsx' -o -name '*.vue' -o -name '*.css' -o -name '*.html' \) \
  -not -path '*/node_modules/*' -print0 \
  | xargs -0 grep -hoE '#[0-9A-Fa-f]{6}\b' 2>/dev/null \
  | tr 'A-F' 'a-f' | sort -u > /tmp/codehex.$$.txt

# 1) Hex hors palette (bloquant si absent de l'allowlist)
OFF=$(comm -23 /tmp/codehex.$$.txt /tmp/palette.$$.txt)
OFF_UNALLOWED=$(comm -23 <(echo "$OFF") /tmp/allow.$$.txt)
if [ -n "$OFF_UNALLOWED" ]; then
  echo "❌ Hex hors palette SANS tolérance documentée :"
  echo "$OFF_UNALLOWED" | sed 's/^/   /'
  echo "→ Ajouter la couleur à COULEURS.md (règle L.07 : même commit que AppColors/tailwind)"
  echo "  ou la tolérer dans $ALLOWLIST_FILE avec une raison."
  rm -f /tmp/palette.$$.txt /tmp/allow.$$.txt /tmp/codehex.$$.txt
  exit 1
fi
TOLERATED=$(comm -12 <(echo "$OFF") /tmp/allow.$$.txt | wc -l | tr -d ' ')
echo "✅ Hex OK (palette ou tolérance documentée). Hors palette tolérés : $TOLERATED"

# 2) Classes legacy — AVERTISSEMENT V1 (non bloquant)
LEGACY=$(find $SCAN_DIRS -type f \( -name '*.vue' -o -name '*.tsx' -o -name '*.html' \) \
  -not -path '*/node_modules/*' -print0 2>/dev/null \
  | xargs -0 grep -hoE '"(bg-white|rounded-lg|shadow|text-gray-[0-9]+)"' 2>/dev/null \
  | sort | uniq -c | sort -rn | head -8)
if [ -n "$LEGACY" ]; then
  echo "⚠️  Classes legacy détectées (V1 : avertissement — migration vers tokens/glass à planifier) :"
  echo "$LEGACY" | sed 's/^/   /'
fi
rm -f /tmp/palette.$$.txt /tmp/allow.$$.txt /tmp/codehex.$$.txt
echo "✅ Garde tokens web/admin OK."
