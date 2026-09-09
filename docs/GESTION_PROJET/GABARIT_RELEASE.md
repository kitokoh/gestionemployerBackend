# Gabarit d'issue Release + synthèse de preuves (protocole P01 §?)

## Gabarit d'issue Release (`[RELEASE] vX.Y.Z`)
- **Scope** : liste des PRs mergées depuis la dernière release (liens) regroupées par BC.
- **Preuves** : lien du run Tests vert sur le SHA taggé ; healthcheck prod (`/api/v1/health` → version) ; smoke E2E prod ; captures vitrine si UI changée.
- **Gate** : tag `vX.Y.Z` sur HEAD de main vert → `release.yml` → `deploy-prod.yml` (3 surfaces) — chaîne à valider de bout en bout à chaque release.
- **Rollback** : procédure `docs/GESTION_PROJET/RUNBOOK_ROLLBACK.md`.

## Workflow de synthèse de preuves (à exécuter avant d'ouvrir la release)
1. Collecter les conclusions des runs GitHub Actions du SHA (API checks) ;
2. Vérifier la parité live : `curl <api>/api/v1/health` (version == SHA) sur DEV et PROD ;
3. Rejouer le smoke post-deploy : `bash dev-hub/tools/smoke-post-deploy.sh <url>` ;
4. Coller le tout dans l'issue Release (bloc Preuves).
