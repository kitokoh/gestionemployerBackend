#!/usr/bin/env bash
#
# check-design-token-sync.sh — Garde de synchronisation des tokens design (issue #7118, P05 §5 T3)
#
# Wrapper de dev-hub/tools/check-design-token-sync.py : vérifie que COULEURS.md,
# AppColors.dart (Flutter) et tailwind.config.* (web/admin) restent synchronisés
# (règle APV L.07 : même commit). Sortie : 0 = OK, 1 = écart.
#
# Usage : dev-hub/tools/check-design-token-sync.sh
set -uo pipefail
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"

PY="dev-hub/tools/check-design-token-sync.py"
[ -f "$PY" ] || { echo "Script introuvable : $PY"; exit 2; }

python3 "$PY" "$@"
