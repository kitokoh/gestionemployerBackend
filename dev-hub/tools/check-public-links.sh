#!/usr/bin/env bash
#
# check-public-links.sh — Smoke des URLs publiques (issue #7122, protocole P03)
#
# Lit docs/ops/DOMAINS.md, extrait les domaines marqués `live`, curl chaque URL
# (health API inclus quand présent) et rapporte les statuts.
# Sortie : 0 = toutes les surfaces attendues répondent, 1 = au moins une KO.
#
# Usage : dev-hub/tools/check-public-links.sh [--timeout SEC] (défaut 15)
set -uo pipefail
REPO_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$REPO_ROOT"
TIMEOUT="${1:-15}"

REGISTRE="docs/ops/DOMAINS.md"
[ -f "$REGISTRE" ] || { echo "Registre introuvable : $REGISTRE"; exit 2; }

# Hôtes `live` : cellules backtick des lignes portant le marqueur `live`
LIVE_HOSTS=$(grep -F '`live`' "$REGISTRE" \
  | grep -oE '`[a-zA-Z0-9.-]+`' | tr -d '`' | sort -u)

if [ -z "$LIVE_HOSTS" ]; then
  echo "Aucun hôte live trouvé dans $REGISTRE"; exit 2
fi

# Candidats : https://<hôte> (+ health API pour les API connues)
URLS=""
for h in $LIVE_HOSTS; do
  case "$h" in
    *.onrender.com|*.vercel.app|*.pages.dev|*.github.io) URLS="$URLS https://$h" ;;
  esac
done
# Health API explicites (documentés dans DEPLOYMENT_URLS.md)
URLS="$URLS https://gestionemployerbackend.onrender.com/api/v1/health https://leopardo-prod.onrender.com/api/v1/health"

FAIL=0
echo "Smoke des URLs publiques (registre $REGISTRE) — $(date -u +%Y-%m-%dT%H:%MZ)"
for u in $URLS; do
  code=$(curl -s -o /dev/null -w "%{http_code}" --max-time "$TIMEOUT" "$u" 2>/dev/null || echo "000")
  if [ "$code" = "200" ] || [ "$code" = "301" ] || [ "$code" = "302" ]; then
    echo "  ✅ $code  $u"
  else
    echo "  ❌ $code  $u"; FAIL=1
  fi
done
[ "$FAIL" = "0" ] && echo "✅ Toutes les surfaces publiques répondent." || echo "❌ Au moins une surface publique est KO."
exit $FAIL
