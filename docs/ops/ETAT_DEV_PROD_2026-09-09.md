# État vérifié des volets dev/prod — 2026-09-09 (protocole P07)

Vérifié en live par la campagne QA A→Z (rapport `projects/qa-campaign` de l'agent PM).

| Surface | URL | État 2026-09-09 |
|---|---|---|
| API DEV (continu) | `https://gestionemployerbackend.onrender.com` | ✅ suit main (health `version` = SHA) — pipeline réparé (gate #6984) |
| Vitrine/portail web DEV | `https://gestionemployer-backend.vercel.app` | ⚠️ **build en retard sur main** (#6923 — `/auth/2fa/challenge`, `/restaurateur`, `/kiosk` → 404) |
| Admin plateforme DEV | `https://leo-admin.pages.dev` | ✅ up (login démo fonctionnel) |
| API PROD (tag) | `https://leopardo-prod.onrender.com` | ✅ up (déploiement tag ; version à re-vérifier à chaque release) |
| Vitrine PROD | `https://leopardo-prod.vercel.app` | ⚠️ tag 09-04 : `/auth/2fa/challenge` 200, `/restaurateur`+`/kiosk` 404 (post-tag) |
| Admin PROD | `https://leo-admin-prod.pages.dev` | ✅ up |
| Docs API /api/v1/docs | DEV | ⚠️ 403 (à trancher : voulu ? #6957) |
| Mode démo DEV | `GET /api/v1/demo-users` | ✅ actif (`password123`) |
| Kiosk/edge | — | client local / hors cloud (docs edge) |

**Actions en cours** : #6923 (Vercel DEV), #7044 (métrique totalCompanies), #7041 (worker queue DEV).
**À chaque session** : mettre à jour ce fichier (date + état) plutôt que de recréer des constats épars.
