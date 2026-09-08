# QA/Ops — Session de vérification 2026-09-08

> Trace de session (docs/notes — pas une source de vérité canonique). Rédigée par un agent
> ops/PM/QA après vérifications réelles (API + navigateur + plateformes). Les leçons durables
> sont reportées dans les issues/PR référencées ; ce fichier donne l'état vérifié + les trappes
> pour gagner du temps aux prochaines sessions.

## Périmètre vérifié (2026-09-08 ~17:00-18:15 UTC)

### Issues traitées avec preuve
| Issue | Verdict | Preuve |
|---|---|---|
| #6974 (super-admin démo DEV, hash NULL) | **Résolue en données** — close avec preuve | `POST /api/v1/platform/auth/login` admin@leopardo-rh.com/password123 → **200 + token** (à chaud) ; UI `leo-admin.pages.dev` : bouton « Utiliser le compte demo super-admin » → **dashboard plateforme affiché** (build servi `v6df70f0`) |
| #6921 (Accès Démo DEV) | Résolue (fermée par PM) + commentaire preuve | `/api/v1/demo-users` → 200 (`data.companies[].users[]` + `super_admin`) ; page login Vercel DEV : section « Try a demo account » présente à chaud, modale 3 sociétés DZ/MA/TN, clic persona → pré-remplissage, Sign in → 200 + session ; PROD `/demo-users` → 404 inchangé |
| #6950 (P0 CI, registre AI dupliqué) | Résolue (fermée par PM) + commentaire preuve | Fix sur main : `AIToolDefinitionRegistry::reset()` en tearDown (TestCase.php:49, PR #6964) + garde idempotence boot (#6947/#6949) |
| #7008 (login démo « 1 clic ») | Résolue en code (fermée) + commentaire cause racine | `selectDemoUser()` sur main appelle déjà `performLogin()` (auto-submit) ; le build Vercel servi est en retard (quota gratuit épuisé) → symptôme de déploiement, pas de code |
| #6973/#6957/#6923/#6681 (tier DEV figé) | **Cause racine unique documentée** (en cours PM ops #6973) | Voir section « Tier DEV » |

### PR
- **#7018** (chore openapi, MANIFEST drift post-merge #6955) — créée, checks verts sauf 4 requis en file ; doublons **#7020/#7022 fermés** avec renvoi (protocole anti-doublon #2400). À merger quand les checks requis passent, puis clore #7019.

## État des environnements (identifiants réels, vérifiés API plateformes)

| Tier | API backend | Web | Admin | État 2026-09-08 18:15Z |
|---|---|---|---|---|
| **DEV** | Render `gestionemployerbackend` (srv-d7dro8u7r5hc73a395pg, francfort) | Vercel projet `leopardo` (prj_RcRVAujaFul8WNZXxgnNKTb9lXdr, domaines `leopardo.com` + gestionemployer-backend.vercel.app) | CF Pages `leo-admin.pages.dev` (à jour, v6df70f0) + worker CF `gestionemploye.africa-novatech.workers.dev` | API **figée sur 623ed31 (07/09 16:08)** ; web Vercel en retard (quota) ; admin CF à jour |
| **PROD** | Render `leopardo-prod` (srv-dacsr6gae00c73ddk150, version 4.24.0, dernier deploy 04/09 — cadence release) | Vercel `leopardo-prod` (leopardo-prod.vercel.app) | CF Pages `leo-admin-prod.pages.dev` | Sain (health OK, API OK, web 200) |

DB : Neon prod+dev (tokens fournis mais **API api.neon.tech injoignable depuis la sandbox** — DNS bloqué, proxy 403). Backups : `DATABASE_URL` posée en secret repo (17:09Z) ; manquent `BACKUP_S3_BUCKET`, `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY` → le job échoue bruyamment (voulu #6836) tant que le fondateur ne les pose pas.

## Tier DEV — anatomie du blocage (alimente #6973/#6957/#6923/#6681/#6958)

Faits (API Render + HTTP) :
1. Dernier déploiement réussi : **623ed31, 07/09 16:08** (`dep-dafe2ru1egvs739p9i20`). Depuis, **tous** les re-deploys échouent en `update_failed` (events API : build OK, boot `nonZeroExit:1`, ~75-90 s), **y compris le même commit** → cause environnementale, pas code.
2. Env du service (API env-vars) : **20 variables seulement — les `DB_*`/`REDIS_URL` sont absents** (vars dashboard `sync:false` supprimées, probable sync blueprint) → le boot ne peut pas joindre la DB → exit 1. Valeur `DB_URL` (Neon dev) à re-saisir dans le dashboard Render.
3. `healthCheckPath` du service était **vide** (corrigé → `/api/v1/health` par l'agent ops, commentaire #6973 17:01Z).
4. Instance live saine : `/api/v1/health` → DB ok (~215 ms), Redis pong, queue ok. Mais **routes web 500** (`checks.web`: RuntimeException round-trip Crypt) sur l'instance live (env APP_KEY de l'époque du deploy) — l'APP_KEY dashboard actuelle est valide (base64, 32 octets) → probablement résolu au prochain deploy réussi.
5. Web DEV Vercel : déploiements `CANCELED`/rate-limited (« Deployment rate limited — retry in 24 hours ») → build en retard sur main (#6923/#7008).

**Actions requises (propriétaire dashboard Render)** : (a) re-saisir `DB_URL` (+ `REDIS_URL`/`REDIS_PASSWORD` si Redis souhaité) sur `gestionemployerbackend` ; (b) redéployer (deploy-main ou force_deploy #7000) ; (c) vérifier routes web puis fermer #6957/#6681/#6923/#6958 avec preuve.

## Trappes pour les prochaines sessions

1. **Cold start Render gratuit (~50-60 s)** : au 1er chargement, les fetch du front (ex. `/demo-users`) échouent silencieusement → parcours démo invisible. Toujours re-tester après warm-up avant de déclarer un bug front (#6921/#7008 : faux positifs évités ainsi).
2. **Drift OpenAPI post-merge** : une PR qui régénère le SDK sur SA branche peut laisser `MANIFEST.json` périmé dans l'arbre final de main (résolution de merge). Après tout merge touchant `api/openapi.yaml`, rejouer `node dev-hub/tools/generate-openapi-sdk.mjs` (sinon OpenAPI CI rouge sur main ET toutes les PR — workflow sans filtre paths).
3. **Clone blobless + push** : `git push` échoue en « missing object » répété (objets non rapatriés). Utiliser un clone complet (ou `--depth 300 --single-branch`) pour pousser.
4. **Quota Vercel gratuit (~100 déploiements/jour)** : le web DEV reste en retard ; ne pas créer d'issue front sur la base du build servi sans vérifier le SHA servi (les fixes peuvent déjà être sur main).
5. **`rg` peut déformer l'affichage** dans certaines sandbox (mots remplacés) : recouper avec `grep`/`sed` pour la vérité terrain.
6. **Checks « Vercel » / « Workers Builds » fail = quota, non requis** (leçon existante confirmée) : merger sur les 4 checks requis (PHPStan Strict, Module Structure Validator, Frontend ESLint+TS, actionlint) quand ils sont verts.
7. **Neon** : API non résolvable depuis la sandbox (DNS/proxy) — les opérations DB directes nécessitent un autre canal (dashboard Neon ou SQL via Render).

## Commandes utiles (re-vérification rapide)

```bash
# SHA live du tier dev + santé
curl -s https://gestionemployerbackend.onrender.com/api/v1/health | jq .version,.checks
# deploys récents Render dev
curl -s -H "Authorization: Bearer $RENDER_DEV_TOKEN" "https://api.render.com/v1/services/srv-d7dro8u7r5hc73a395pg/deploys?limit=5" | jq -r '.[].deploy | "\(.status) \(.commit.id[0:7])"'
# env vars service Render (noms)
curl -s -H "Authorization: Bearer $RENDER_DEV_TOKEN" "https://api.render.com/v1/services/srv-d7dro8u7r5hc73a395pg/env-vars" | jq -r '.[].envVar.key'
# régénération SDK OpenAPI
node dev-hub/tools/generate-openapi-sdk.mjs
```
