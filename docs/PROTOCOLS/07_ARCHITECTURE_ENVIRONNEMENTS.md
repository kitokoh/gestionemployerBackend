# PROTOCOLE 07 — Architecture à deux volets (dev ↔ prod) : gouvernance

> **Statut** : Actif v1.0 — 2026-09-09
> **Porteur** : fondateur/PM + ops
> **Revue** : mensuelle, fin de mois — cf. docs/PROTOCOLS/00_INDEX.md
>
> **Pourquoi** : le système est piloté par deux volets — développement continu (chaque merge sur `main` atteint les surfaces dev) et production réelle (promue uniquement par tag `vX.Y.Z` validé). Cette séparation est récente et fragile : elle a été construite en requalifiant l'existant (le service `gestionemployerbackend` s'appelait « production » et porte toujours `APP_ENV=production`), et plusieurs documents ont déjà dérivé de la réalité (blueprint non aligné corrigé en PR #6831, seuil de coverage 60 vs 65, inventaire CI obsolète). Sans gouvernance, chaque changement d'infrastructure creuse un écart silencieux entre le déclaratif, la documentation et les services réellement en ligne. Ce protocole rend la dérive visible, tracée en issues et corrigée dans un délai borné.

## 1. Objectif & périmètre

**Objectif** : garantir à tout moment que l'architecture des deux volets reste saine, documentée et sans dérive, c'est-à-dire :

1. Une source de vérité unique de la topologie (§4), actualisée dans la même PR que tout changement d'infrastructure.
2. Chaque écart doc ↔ réalité détecté (au fil de l'eau ou au rituel mensuel, §5) converti en issue traçable — jamais laissé dans un document figé.
3. Aucune modification de l'environnement de production hors de la chaîne de promotion par tag (§3).
4. Aucune décision structurante implémentée sans ADR datée (§6).
5. Une santé de l'architecture mesurée par des indicateurs à seuils (§7), et un état des lieux de la dette connu à tout instant (§8).

**Périmètre** : les deux volets « dev/continu » et « production (phase 1) » — blueprints et services Render, bases Neon, surfaces Vercel et Cloudflare Pages, workflows de déploiement GitHub Actions, registres d'URLs/domaines, runbooks de déploiement/rollback/incident/observabilité/alerting, ADR d'infrastructure.

**Hors périmètre** (flux dédiés existants, non dupliqués) : la qualité applicative et la CI des PR (protocoles 01 à 04), les incidents pilotes (SLA dédié), la sécurité applicative (SECURITY.md). Ce protocole renvoie vers eux au lieu de les remplacer.

## 2. Topologie de référence (tableau : volet, service, rôle, URL, source de config, état)

Volets vérifiés par lecture du 2026-09-05 (audit HTTP des surfaces) et des fichiers sources cités ; état au 2026-09-09.

| Volet | Service | Rôle | URL | Source de configuration | État |
|---|---|---|---|---|---|
| A — dev (continu, push `main`) | API `gestionemployerbackend` (Render web, Starter) | API Laravel dev/test | `https://gestionemployerbackend.onrender.com/api/v1/health` | `render.yaml` (blueprint déclaratif NON auto-sync) + `deploy-main.yml` (hook) | En ligne — HTTP 200, version servie 4.24.0 (retard sur `main`) |
| A — dev | Queue worker | Drain des files (driver `database`, #5578) | interne | `api/docker-entrypoint.sh` (mono-conteneur web) ; `leopardo-queue-worker` déclaré dans `render.yaml`, non provisionné | Actif via le conteneur web ; pas de service dédié |
| A — dev | Scheduler | Tâches planifiées (`schedule:run`) | interne | `render.yaml` (`leopardo-scheduler`, non provisionné) | Absent — tâches planifiées non exécutées |
| A — dev | Données | Base PostgreSQL + cache/session | Neon projet `leopardorh` (`*.eu-west-2.aws.neon.tech`), Redis KV `leopardoai` | `DB_URL`/`REDIS_URL` en `sync: false` (dashboard Render, hors blueprint) | En ligne ; aucun Postgres Render (supprimé, PR #6831) |
| A — dev | Web Vercel | Portail/vitrine web | `https://gestionemployer-backend.vercel.app` | Intégration Git Vercel | En ligne — doublon `https://leopardo.vercel.app` à clarifier |
| A — dev | Admin Cloudflare Pages | Back-office super-admin | `https://leo-admin.pages.dev` | `deploy-admin-dashboard.yml` | En ligne |
| B — prod (phase 1, tag `vX.Y.Z`) | API `leopardo-prod` (Render web, plan free) | API de production | `https://leopardo-prod.onrender.com/api/v1/health` | `render.prod.yaml` + `deploy-prod.yml` (Release publiée) | En ligne — recette 2026-09-04 validée ; chaîne auto Release non testée bout en bout |
| B — prod | Queue worker | Drain des files | interne | Mono-conteneur web ; `leopardo-queue-worker-prod` commenté (inéligible tier gratuit) | Actif via le conteneur web |
| B — prod | Scheduler | Tâches planifiées | interne | `leopardo-scheduler-prod` commenté dans `render.prod.yaml` | Absent — bloquant avant trafic client réel |
| B — prod | Données | Base PostgreSQL + cache/session | Neon projet `LEOPARDO`, branche `production` (`eu-central-1`) ; Redis KV `leopardo-redis-prod` (gratuit, en mémoire) | `render.prod.yaml` (KV) ; `DB_URL` `sync: false` (dashboard + Pulumi ESC `solarnyxss/leopardo-hr/prod`) | En ligne |
| B — prod | Web Vercel | Portail/web prod | `https://leopardo-prod.vercel.app` | `deploy-prod.yml` (CLI Vercel, pas d'intégration Git) | En ligne |
| B — prod | Admin Cloudflare Pages | Admin prod | `https://leo-admin-prod.pages.dev` | `deploy-prod.yml` (wrangler) | En ligne |
| B — prod | Domaine | Nom de marque | `https://leopardo-rh.com` | `docs/ops/DOMAINS.md` (#3452) | Non acheté/pointé (NXDOMAIN, attendu phase 1) |
| — | Staging | Pré-production | — | `docs/DEPLOYMENT_STAGING.md` = guide cible ; `deploy-staging.yml` rouge volontaire sur `main` | N'existe pas (issue #1485) |

## 3. Principes non négociables (P1…Pn)

- **P1 — Parité de configuration entre volets.** Les deux volets partagent le même schéma de configuration (mêmes clés d'environnement, mêmes drivers de queue/cache, mêmes règles de migration au boot, même `DB_SEARCH_PATH`, même entrypoint). Seules les valeurs (URLs, secrets, budgets, plans) diffèrent. Toute divergence de schéma entre `render.yaml` et `render.prod.yaml` doit être motivée dans une issue ; elle signale une dérive tant qu'elle n'est pas tracée.
- **P2 — Promotion en production par tag uniquement.** La prod n'évolue que par la chaîne : tag `vX.Y.Z` sur HEAD de `main` (checks requis verts) → `release.yml` crée la Release → `deploy-prod.yml` (`release: published`) déploie les trois surfaces. Règle API Render (limite vérifiée) : le tag doit être HEAD de `main`, sinon l'API recevrait le code de `main` sans garantie ; en `workflow_dispatch` sur un tag plus ancien, avertissement explicite.
- **P3 — Aucune modification de prod hors `deploy-prod.yml`.** Interdit : sync manuelle du blueprint `render.prod.yaml`, clic « Deploy » dans le dashboard Render sur `leopardo-prod`, intégration Git Vercel/Cloudflare pour la prod, hook de déploiement alternatif ciblant la prod. Toute modification passée hors pipeline est un incident de gouvernance : constat, rollback si besoin, issue.
- **P4 — Aucune dépendance croisée dev ↔ prod.** Pas de partage de bases, de Redis, de secrets ni d'URLs entre volets. Les secrets/variables de déploiement portent des noms disjoints (`RENDER_PROD_*`, `VERCEL_PROD_*`, `CLOUDFLARE_PROD_*` vs hooks dev) ; le fallback d'un environnement vers l'autre est interdit (#1485 a retiré les fallbacks staging → prod).
- **P5 — Secrets jamais en clair.** Aucune valeur réelle de secret dans les fichiers versionnés : `sync: false` (saisie dashboard), `generateValue`, ou Pulumi ESC (`solarnyxss/leopardo-hr/prod`) comme référence. Toute clé partagée en clair (chat, ticket) déclenche rotation + issue P0.

## 4. Registre topologie canonique & règle du même commit

- **Source de vérité unique** : `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` (état vérifié 2026-09-05). Il décrit la réalité des services live, pas le souhaité ; son en-tête porte la date de dernière vérification, mise à jour à chaque audit.
- **Registres satellites** : `docs/ops/DOMAINS.md` (canonique, machine-checkable) et `docs/ops/DEPLOYMENT_URLS.md` (miroir exécutable des URLs + santé). En cas de doute entre les deux, `DOMAINS.md` fait foi.
- **Règle du même commit** : toute PR qui modifie l'infrastructure — `render.yaml`, `render.prod.yaml`, les workflows de déploiement (`deploy-main.yml`, `deploy-prod.yml`, `deploy-staging.yml`, `release.yml`, `deploy-admin-dashboard.yml`), les secrets/variables GitHub Actions, le provisionnement Neon/Vercel/Cloudflare — DOIT mettre à jour la topologie (et les registres satellites concernés) dans la même PR. Une PR d'infra sans mise à jour de topologie est refusée en review.
- **Vérification croisée** : `.github/workflows/README.md` (cartographie des workflows, MAJ 2026-08-29) et `docs/ARCHITECTURE_CICD.md` (MAJ 2026-08-29) doivent rester cohérents entre eux et avec la topologie ; `docs/CI_CD_SECRETS.md` est ré-audité à chaque ajout/suppression/renommage de secret ou variable (règle déjà portée par ce fichier).
- **Règle de précédence en cas de conflit** : la réalité observable (service live, healthcheck) prime sur la documentation ; parmi les fichiers, le workflow exécutable (`.github/workflows/**`) prime sur sa description (`docs/CI_CD_SECRETS.md` le dit explicitement) ; la description prime sur la topologie si celle-ci n'a pas été vérifiée depuis plus de 45 jours. On corrige toujours le document, jamais l'inverse.
- **Blueprints non auto-sync** : `render.yaml`/`render.prod.yaml` décrivent ce qui DOIT exister, sans sync automatique. Toute sync manuelle (dashboard → Blueprint) est prudente : Render mappe par `name:` et un renommage recrée un service (doublon/orphelin) — préférer documenter puis agir via le workflow.

## 5. Rituel mensuel de contrôle d'écart (drift) — checklist

Exécuté par ops + fondateur/PM en fin de mois (même fenêtre que la revue des protocoles). Durée cible : 1 h. Résultat : une issue de rituel (checklist cochée + liste des écarts convertis) référencée dans l'en-tête de la topologie.

1. **Surfaces** : `curl -fsS` chaque URL du tableau §2 (`/api/v1/health` pour les API, HTTP 200 pour web/admin). Noter statut HTTP et, pour les API, la version servie par le healthcheck.
2. **Version servie vs références** : comparer la version servie par l'API dev à `git tag` (dernier tag = code attendu sur `main`). Un retard de l'API dev sur `main` est un écart (rattrapage par `workflow_dispatch` de `deploy-main.yml`).
3. **Services réels vs déclaratifs** : dans les dashboards Render des deux workspaces, vérifier que les services listés correspondent aux blueprints (noms, plans, `autoDeploy`, base Neon et non Postgres Render, instance KV unique) ; aucun service orphelin/facturé inconnu.
4. **Workers & files** : contrôler la présence effective des workers/scheduler (dév ou prod), l'état des files (`queue`/`failed_jobs` au healthcheck) et l'absence de jobs bloqués.
5. **Cartographie workflows** : `ls .github/workflows/*.yml` vs la cartographie de `.github/workflows/README.md` (fichiers ajoutés/supprimés/renommés) ; vérifier que `docs/ARCHITECTURE_CICD.md` et `docs/CI_CD_SECRETS.md` n'annoncent ni workflow disparu, ni seuil faux (ex. coverage 60 vs 65, mobile 21 %), ni secret fantôme.
6. **Registres & runbooks** : `docs/ops/DOMAINS.md` et `docs/ops/DEPLOYMENT_URLS.md` à jour ; runbooks `RUNBOOK_DEPLOY.md`, `RUNBOOK_ROLLBACK.md`, `RUNBOOK_INCIDENT_P1.md`, `RUNBOOK_OBSERVABILITY.md`, `RUNBOOK_ALERTING.md` cohérents avec la topologie (déclencheurs, URLs, secrets).
7. **Secrets** : présence effective des secrets attendus (pas de valeur vide → workflow fail-closed) et cohérence avec Pulumi ESC `solarnyxss/leopardo-hr/prod`.
8. **Conversion en issues** : chaque écart constaté devient une issue selon le protocole 04 (gabarit `[REX]`, labels, priorité) — un écart qui n'a pas d'issue est un écart silencieux (indicateur I1 rouge). La date de vérification est mise à jour en en-tête de `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` dans la même PR que les correctifs documentaires.

## 6. Décisions d'architecture (ADR) — gabarit

Toute décision structurante touchant l'architecture des deux volets — déclencheur de déploiement, choix de fournisseur ou de base de données, modèle de secrets, création d'un environnement (staging), passage en plan payant, ajout de service — est précédée d'une ADR datée dans `docs/architecture/adr/` (registre existant, numéro séquentiel suivant : 0021), enregistrée dans le tableau du `README.md` du dossier. Une PR qui implémente une décision structurante sans ADR est refusée en review (ADR requise au plus tard dans la même PR).

Gabarit minimal (aligné sur le format des ADR existantes, ex. 0003) :

```markdown
# ADR 00NN — <titre court à l'impératif>

- Date : AAAA-MM-JJ (date de la décision, pas de la rédaction)
- Statut : Proposée | Acceptée | Dépréciée (remplacée par ADR 00MM)
- Issue liée : #<issue de pilotage, si existante>

## Contexte
Problème traité, contraintes, alternatives écartées et pourquoi.

## Décision
La position retenue, en une phrase si possible, avec son périmètre exact.

## Conséquences
Positives et négatives, risques assumés, coûts induits.

## Règles opérationnelles
Ce que la décision impose concrètement aux PR, workflows et runbooks.
```

## 7. Indicateurs de santé & seuils

| Indicateur | Définition | Cible | Alerte |
|---|---|---|---|
| I1 — Écarts doc ↔ réalité ouverts | Issues étiquetées « dette architecture »/REX non clôturées | 0 écart sans issue associée | Jaune ≥ 5 ouverts ; rouge ≥ 1 écart « bloquant » non traité |
| I2 — Âge de la dernière vérification de topologie | Date « état vérifié » en en-tête de `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` | < 45 jours | Rouge au-delà : vérification immédiate requise avant tout déploiement prod |
| I3 — Délai de correction d'un écart bloquant | Entre la constatation (issue) et la correction ou la décision explicite de report | ≤ 7 jours ouvrés | Rouge au-delà : escalade porteur ; l'écart reste tracé avec responsable |
| I4 — Dérive de version de l'API dev | Écart entre la version servie et le dernier tag sur `main` | ≤ 1 tag de retard | Jaune ≥ 2 tags : rattrapage `deploy-main.yml` en dispatch |
| I5 — Couverture des surfaces par la doc | % des services live listés au §2 présents dans `DOMAINS.md` + `DEPLOYMENT_URLS.md` | 100 % | Rouge : tout service live non documenté est un écart immédiat |

Lecture : le porteur consolide I1–I5 à chaque rituel (§5) et les reporte dans l'issue mensuelle ; les valeurs alimentent la revue de fin de mois (00_INDEX).

## 8. État des lieux au 2026-09-09 (dettes connues et issues associées si identifiées)

Règle de précédence : chaque dette ci-dessous DOIT être tracée comme issue (existante ou `[REX]` à créer selon le protocole 04) avant la revue de fin septembre 2026 ; une dette non tracée est un écart silencieux. Rien de ce tableau n'est implémenté par le présent protocole — il fixe la cible et la traçabilité.

| # | Dette constatée (preuve) | État cible | Traçage |
|---|---|---|---|
| D1 | `APP_ENV=production` sur le service de dev `gestionemployerbackend` ; variable `vars.PROD_API_BASE_URL` ciblant en réalité le dev ; doublon Vercel `leopardo.vercel.app` (`render.yaml` en tête, `deploy-prod.yml` en tête, topologie §contexte) | Aucun nommage « prod » sur le volet dev : renommage progressif des services et variables une fois la prod validée en continu | Issue à créer (renommage différé volontairement : le mapping Render par `name:` interdit le renommage à chaud) |
| D2 | API dev en retard sur `main` : sert v4.24.0 alors que des tags récents existent (v4.27.2) ; chaîne auto prod pas encore validée de bout en bout (topologie, §état vérifié 2026-09-05) | API dev ≤ 1 tag de retard (I4) ; chaîne Release → prod validée sur un prochain tag HEAD de `main` | Issue à créer ; rattrapage immédiat par dispatch `deploy-main.yml` |
| D3 | Staging inexistant (#1485) : `deploy-staging.yml` rouge volontaire sur `main` ; `docs/DEPLOYMENT_STAGING.md` = guide cible uniquement | Décision explicite : provisionner un vrai staging (ADR 0021+) ou acter son absence et neutraliser/documenter le workflow | Issue #1485 (existante) |
| D4 | Workers/scheduler absents en prod : queue en mono-conteneur web, tâches planifiées NON exécutées ; Neon sur plan gratuit (sans sauvegardes) ; KV Redis en mémoire (`render.prod.yaml` commenté, topologie §écarts) | Plan payant : `leopardo-scheduler-prod` (+ `leopardo-queue-worker-prod` si sortie du mono-conteneur) actifs, Neon payant — avant tout trafic client réel | Issue à créer (P0 conditionnel) |
| D5 | `docs/CI_CD_SECRETS.md` annonce un seuil de coverage backend à 60 alors que le code exécute 65 (`DEFAULT_BACKEND_COVERAGE_MIN: "65"` dans `tests.yml` et `coverage-gate.yml`) | Document aligné sur le code (65) | Issue de correction documentaire |
| D6 | `docs/qa/INVENTAIRE_CI_2026-08-19.md` obsolète : 43 workflows inventoriés, certains supprimés depuis (4 `plan-action2-*` nettoyés le 2026-08-29, ajouts postérieurs ; 55 fichiers `.yml` présents) | Inventaire régénéré ou archivé avec renvoi vers `.github/workflows/README.md` | Issue de correction documentaire |
| D7 | « Coverage mobile minimum 21 % » annoncé dans `docs/ARCHITECTURE_CICD.md` mais introuvable dans `mobile-apps-ci.yml` (aucune gate) | Annonce retirée ou gate réellement câblée (`MOBILE_COVERAGE_MIN`) | Issue à créer (décision à trancher) |
| D8 | `e2e-staging.yml` nommé « E2E - Playwright Prod Smoke » et visant la PROD (#3906) | Renommage du fichier ou documentation explicite de l'intention « smoke prod » dans la cartographie | Issue #3906 (existante) |
| D9 | Sauvegardes DB prod désactivées : `database-backup.yml` skippé (secrets `DATABASE_URL`/`BACKUP_S3_BUCKET`/`AWS_*` absents) ; drain GH et supervision queue inertes (topologie §écarts résiduels) | Secrets posés ou Neon payant (backups PITR) ; filets de secours actifs | Issue à créer (P0) |
| D10 | Rotation des clés Render/Neon/Stripe/Chargily partagées en clair ; vérification `SUPER_ADMIN_PASSWORD` posé sur dev et prod (topologie §écarts résiduels) | Clés régénérées, valeurs réelles dans Pulumi ESC uniquement | Issue à créer (P0 sécurité) |

Fichiers liés (tous vérifiés à la rédaction) : `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` (état vérifié 2026-09-05), `render.yaml`, `render.prod.yaml`, `.github/workflows/deploy-prod.yml`, `.github/workflows/deploy-main.yml`, `.github/workflows/release.yml`, `.github/workflows/deploy-staging.yml`, `.github/workflows/e2e-staging.yml`, `.github/workflows/mobile-apps-ci.yml`, `.github/workflows/README.md` (MAJ 2026-08-29), `docs/ARCHITECTURE_CICD.md` (MAJ 2026-08-29), `docs/CI_CD_SECRETS.md`, `docs/DEPLOYMENT_STAGING.md`, `docs/qa/INVENTAIRE_CI_2026-08-19.md`, `docs/ops/DOMAINS.md`, `docs/ops/DEPLOYMENT_URLS.md`, `docs/architecture/adr/` (registre + gabarit), `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md` et runbooks associés, `docs/PROTOCOLS/04_CAPITALISATION_EXPERIENCE.md`.
