# Audit hebdomadaire de parité live-vs-dépôt (protocole P07 §4)

## Quand
Chaque lundi ~10 min — vérifier que les surfaces live reflètent main.

## Commandes
```bash
# API DEV / PROD : version (SHA honnête depuis #6835)
curl -s https://gestionemployerbackend.onrender.com/api/v1/health | jq '{v:.version}'
curl -s https://leopardo-prod.onrender.com/api/v1/health | jq '{v:.version}'
# Marqueurs web (routes ajoutées récemment sur main — adapter à la dernière route connue)
for u in https://gestionemployer-backend.vercel.app/auth/2fa/challenge https://gestionemployer-backend.vercel.app/restaurateur; do curl -s -o /dev/null -w "%{http_code} $u\n" $u; done
# Admin
curl -s -o /dev/null -w "%{http_code} leo-admin.pages.dev\n" https://leo-admin.pages.dev
```
## Critère
- API DEV/PROD : version == SHA attendu (dernier main / dernier tag).
- Web Vercel DEV : routes récentes 200 (sinon → famille #6923, quota Vercel).
- Écart constaté → issue `[P#][ops]` avec preuves (cette doc + captures).

## Gabarit d'audit mensuel
Issues : `[AUDIT MENSUEL] <mois> — parité live-vs-dépôt` listant tableau surface/version attendue/version live/écart.
