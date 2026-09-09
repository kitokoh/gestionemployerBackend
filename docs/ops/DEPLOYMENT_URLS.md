# DEPLOYMENT_URLS.md — URLs de déploiement actives (source : registre DOMAINS.md)

> 📌 **Registre P07 — état constaté le 2026-09-09** (protocole
> `docs/PROTOCOLES/P07_ARCHITECTURE_DEV_PROD.md`, issue #7073). Complète le tableau
> ci-dessous avec les faits vérifiés en session QA/ops :

| Volet | Tier dev (continu) | Tier prod (tags validés) |
|---|---|---|
| API Laravel | `gestionemployerbackend.onrender.com` — service `gestionemployerBackend` (compte africanovatech) | `leopardo-prod.onrender.com` |
| Web (Next.js) | `gestionemployer-backend.vercel.app` — ⚠️ **build en retard sur main** (#6923, à redéployer) | `leopardo-prod.vercel.app` |
| Admin (Vue) | `leo-admin.pages.dev` — build récent (#6927 inclus) | `leo-admin-prod.pages.dev` |
| Site produit | `kitokoh.github.io/leopardo-hr` (gh-pages, depuis main) | — (même artefact) |

**Faits ops (09-09) :**
- Dev API alignée sur main (`015f16c0`, deploy live 09-09 01:04Z) ; `/api/v1/health` expose le SHA git réel (honnêteté #6905).
- Pipeline dev : `deploy-main.yml` (gate Tests + filtre api/**) + **catchup horaire** `deploy-main-catchup.yml` (cron `:23`) + dispatch manuel.
- Variables critiques portées par les **env groups Render** liés au service (« ok » : REDIS_URL/SESSION_DRIVER/CACHE_STORE/QUEUE_CONNECTION… ; « mail » : DB_URL/RUN_MIGRATIONS/SUPER_ADMIN_PASSWORD/MAILGUN…) — ne pas les recréer en vars « service ».
- `healthCheckPath` du service Render dev = **`/api/v1/health`** (fixé 09-08 — un healthcheck vide faisait échouer tous les deploys, sonde `/` → 500).
- Worker queue : mono-conteneur (driver database) ; respawn après `--max-time` en cours (#7041). Drain GitHub Actions toutes les 5 min (fallback).
- Web dev Vercel en retard → les parcours login UI (#6923) restent bloqués côté navigateur tant que le projet Vercel dev n'est pas redéployé sur main.


> Livrable de l'épic #3765 (stabilisation production). Documente les URLs
> **réellement actives** (services gratuits Render/Vercel/Cloudflare Pages),
> les endpoints de santé et la procédure de déploiement.
> Source de vérité des domaines : `docs/ops/DOMAINS.md` (garde
> `check-canonical-domains.sh`).

## Surfaces actives (live)

| Surface | URL | Service | Vérifié |
|---|---|---|---|
| API Laravel (base `/api/v1`) | `https://gestionemployerbackend.onrender.com` | Render (Starter) | HTTP 200 2026-09-05 |
| Santé API | `https://gestionemployerbackend.onrender.com/api/v1/health` | Render | 200, DB ok, redis pong, queue database — 2026-09-05 |
| Vitrine / portail web | `https://gestionemployer-backend.vercel.app` | Vercel | HTTP 200 2026-09-05 |
| Admin plateforme (super-admin) | `https://leo-admin.pages.dev` | Cloudflare Pages | HTTP 200 2026-09-05 |
| Site marketing | `https://kitokoh.github.io/leopardo-hr/` | GitHub Pages (depuis main, #6827) | HTTP 200 2026-09-05 |

> ⚠️ `https://leopardo.vercel.app` répond aussi HTTP 200 (2026-09-05) —
> projet Vercel distinct à clarifier/rationaliser avec
> `gestionemployer-backend.vercel.app` (la variable `PROD_WEB_URL` désigne
> ce dernier comme web dev de référence).

## Services Render (backend)

- **API + Web** : `gestionemployerbackend` — déployé via webhook
  `RENDER_DEPLOY_HOOK_URL` (secret GitHub, workflow `deploy-main.yml`).
- **Queue worker** : mono-conteneur — le worker tourne en arrière-plan du conteneur web
  (entrypoint, driver `database`) ; **drain de secours GitHub Actions** toutes les 5 min
  (`queue-worker-fallback.yml`, #5205). #4948 résolu — plus de service séparé attendu.
- **Drivers (boot)** : `QUEUE_CONNECTION=database` fixe ; `CACHE_STORE`/`SESSION_DRIVER`
  automatiques (Redis si Upstash répond, sinon `file`) via `infra:probe-availability` (#5206/#5207).
- **Staging** : `RENDER_STAGING_DEPLOY_HOOK_URL` (fallback hook prod si absent) ;
  URL d'health-check staging : `STAGING_API_URL`
  (défaut `https://gestionemployerbackend.onrender.com`).
- **Rollback** : `RENDER_ROLLBACK_HOOK_URL` (déclenché en cas d'échec de deploy prod).

## Surfaces prod (topologie tag `vX.Y.Z` — `deploy-prod.yml`)

Vraie production, promue uniquement par un tag git validé (checks requis
verts, tag ancêtre de `main`). Registre : `docs/ops/DOMAINS.md`.

| Surface | URL | Service | Déployé par |
|---|---|---|---|
| API Laravel prod (base `/api/v1`) | `https://leopardo-prod.onrender.com` | Render prod (web service `leopardo-prod`, `srv-dacsr6gae00c73ddk150`) + Neon (projet `LEOPARDO`, branche `production`) | HTTP 200, DB ok, redis pong, queue database (0 job, 0 failed) — 2026-09-05 |
| Vitrine / portail web prod | `https://leopardo-prod.vercel.app` | Vercel prod (projet `leopardo-prod`, équipe ibrahimkoubaye-6514) — `front/web` buildé avec `NEXT_PUBLIC_API_URL` → API prod | HTTP 200 2026-09-05 |
| Admin plateforme prod | `https://leo-admin-prod.pages.dev` | Cloudflare Pages prod (projet `leo-admin-prod`, compte prod) — `front/admin-dashboard` buildé avec `VITE_API_URL` → API prod | HTTP 200 2026-09-05 |

Dernière livraison prod des 3 surfaces : workflow_dispatch `deploy-prod.yml`
du 2026-09-04 06:05 (recette). La chaîne automatique par Release (tag
vX.Y.Z sur HEAD de main) reste à valider de bout en bout (run release.yml
du tag v4.27.2 annulé). Voir `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`.

Secrets GitHub requis (fail-closed) : `RENDER_PROD_API_KEY`,
`RENDER_PROD_SERVICE_ID`, `PROD_RENDER_API_BASE_URL`,
`VERCEL_PROD_TOKEN`, `VERCEL_PROD_ORG_ID`, `VERCEL_PROD_PROJECT_ID`,
`CLOUDFLARE_PROD_API_TOKEN`, `CLOUDFLARE_PROD_ACCOUNT_ID`.
Variables GitHub : `RENDER_PROD_SERVICE_ID`, `PROD_WEB_PROD_URL`,
`PROD_ADMIN_PROD_URL` (liens `environment.url`, cosmétique).

Côté API prod, `CORS_EXTRA_ORIGIN`/`ADMIN_DASHBOARD_URL` =
`https://leo-admin-prod.pages.dev`, `FRONTEND_URL` =
`https://leopardo-prod.vercel.app` (posés sur le service Render prod).

## Déclenchement d'un déploiement

**Prod (3 surfaces)** : pousser le tag `vX.Y.Z` sur HEAD de `main` →
`release.yml` crée la GitHub Release → `deploy-prod.yml` (événement
`release: published`) déploie API Render + web Vercel + admin CF Pages avec
healthchecks. Alternative : `workflow_dispatch` de `deploy-prod.yml` avec un
tag existant (gate : checks verts ; tag ≠ HEAD de main → avertissement).


```bash
# Déploiement API/Web (Render) — via le hook secret (depuis GitHub Actions)
curl -X POST "$RENDER_DEPLOY_HOOK_URL"

# Vérification post-déploiement
curl -s https://gestionemployerbackend.onrender.com/api/v1/health
curl -s https://gestionemployerbackend.onrender.com/api/v1/health/ready
```

La version API attendue sur main : `APP_VERSION` = **4.24.0+**
(`api/config/app.php`, défaut `env('APP_VERSION', '4.24.0')`).
Critère de succès #3765 : `/api/v1/health` retourne v4.24.0+.

## Variables d'environnement liées au déploiement

| Variable | Usage | Valeur de référence |
|---|---|---|
| `RENDER_DEPLOY_HOOK_URL` | Webhook deploy prod | secret GitHub |
| `RENDER_STAGING_DEPLOY_HOOK_URL` | Webhook deploy staging | secret GitHub (fallback prod) |
| `RENDER_ROLLBACK_HOOK_URL` | Webhook rollback prod | secret GitHub |
| `STAGING_API_URL` | Health-check staging / lien `environment.url` | `https://gestionemployerbackend.onrender.com` |
| `LEOPARDO_API_URL` | Base API utilisée par la vitrine Next.js | résolue via `resolveBackendBaseUrl()` (défaut Render) |
| `FRONTEND_URL` / `APP_URL` | URLs canoniques CORS/Sanctum | `https://gestionemployer-backend.vercel.app` / `https://gestionemployerbackend.onrender.com` |
| `NEXT_PUBLIC_ENABLE_BLOG` | Activation du blog vitrine (#2906) | `true` (contenu prêt, activé 2026-08-18) |

## Domaines réservés (NXDOMAIN — NE PAS utiliser en default de build)

`leopardo-rh.com`, `www.leopardo-rh.com`, `app.leopardo-rh.com`,
`admin.leopardo-rh.com`, `api.leopardo-rh.com`, `docs.leopardo-rh.com`,
`api.leopardo.app`, `proxy.leopardo-rh.com`, `demo.leopardo-rh.com`…
→ voir `docs/ops/DOMAINS.md` (registre complet) et #3452 (provisionnement DNS).
