#!/usr/bin/env bash
# ============================================================
# check-mobile-duplicated-drift-test.sh — Auto-test de la garde de dérive sur
# fichiers dupliqués mobiles (issue #7091). Construit un dépôt git temporaire
# avec 2 apps partageant un fichier byte-identique contenant du mojibake :
#   1. correctif appliqué à UNE seule copie → rouge attendu ;
#   2. correctif propagé aux deux copies → vert attendu.
# Usage : bash dev-hub/tools/check-mobile-duplicated-drift-test.sh
# ============================================================
set -euo pipefail

HERE="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
GUARD="$HERE/check-mobile-duplicated-drift.sh"
TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

git -C "$TMP" init -q
git -C "$TMP" config user.email test@local
git -C "$TMP" config user.name "test"

DIR="$TMP/front/mobile_apps"
mkdir -p "$DIR/leopardo_employee/lib/features/home" \
         "$DIR/leopardo_manager/lib/features/home"

# Contenu avec mojibake volontaire (Ã© = é mal ré-encodé) dans les 2 copies.
cat > "$DIR/leopardo_employee/lib/features/home/home_screen.dart" <<'DART'
class HomeScreen {
  final String title = 'Bienvenue sur l\'application employÃ©';
}
DART
cp "$DIR/leopardo_employee/lib/features/home/home_screen.dart" \
   "$DIR/leopardo_manager/lib/features/home/home_screen.dart"

git -C "$TMP" add -A
git -C "$TMP" commit -q -m "base: copies dupliquées avec mojibake"
BASE="$(git -C "$TMP" rev-parse HEAD)"

# --- Cas 1 : correctif appliqué à UNE seule copie → rouge
sed -i 's/employÃ©/employé/' "$DIR/leopardo_employee/lib/features/home/home_screen.dart"
git -C "$TMP" add -A
git -C "$TMP" commit -q -m "fix: encodage corrigé dans employee seulement"
HEAD="$(git -C "$TMP" rev-parse HEAD)"

if (cd "$TMP" && bash "$GUARD" "$BASE" "$HEAD" >/dev/null 2>&1); then
  echo "FAIL : correctif non propagé non détecté (cas 1)" >&2
  exit 1
fi
echo "ok: correctif non propagé détecté (mojibake restant chez la sœur)"

# --- Cas 2 : correctif propagé aux deux copies → vert
sed -i 's/employÃ©/employé/' "$DIR/leopardo_manager/lib/features/home/home_screen.dart"
git -C "$TMP" add -A
git -C "$TMP" commit -q -m "fix: encodage propagé à manager"
HEAD="$(git -C "$TMP" rev-parse HEAD)"

(cd "$TMP" && bash "$GUARD" "$BASE" "$HEAD" >/dev/null)
echo "ok: correctif propagé accepté"

echo "PASS : check-mobile-duplicated-drift-test.sh"
