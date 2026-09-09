# Protocole 07 — Gouvernance de l'architecture à deux volets (développement / production)

> **Statut : v1.0 — révisé le 2026-09-09 — propriétaire : PM**
> Code des règles : `ENV-*`.
> Objet : garantir **à tout moment** que l'architecture système — organisée en deux
> volets, un pour le développement/intégration (dev), un pour la production (prod) —
> reste cohérente : mêmes capacités, secrets séparés, données isolées, versions
> alignées, documentation exacte.

## 1. Registre des environnements (constaté le 2026-09-09 — à faire vivre)

> Ce tableau est la **carte de référence** des deux volets. Il doit être tenu à jour à
> chaque évolution d'infra (règle ENV-7). Les valeurs « constaté » proviennent d'une
> revue API des plateformes le 2026-09-09 ; les trous sont marqués `?` (à confirmer par
> le PM/ops).

| Service | Volet dev | Volet prod | Source / liens |
|---|---|---|---|
| API Laravel (backend) | Render — service `gestionemployerBackend` (repo `leopardo-hr`) | Render — service `leopardo-prod` (repo `leopardo-hr`, `render.prod.yaml`) | `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`, `docs/GESTION_PROJET/RENDER_SETUP.md`, `docs/ops/DEPLOYMENT_URLS.md` |
| Base PostgreSQL / queues / workers | instance dev + workers dev | instance prod + workers prod | `docker-compose.yml` (local), runbooks Render workers |
| Web vitrine + SaaS (Next.js) | Vercel — projet `leopardo` (lié à `kitokoh/leopardo-hr`) | Vercel — projet `leopardo-prod` | `docs/web/`, workflows `web-ci.yml`, `deploy-prod.yml` |
| Portails/vitrines par module | Vercel — `leopardo-delivery`, `leopardo-edu`, `leopardo-fuel`, `leopardo-travel`, `leopardo-resto` | Vercel — `*-prod` correspondants | protocole 03 (V4), à rattacher aux repos/branches réels |
| Admin dashboard (Vue) | Cloudflare Pages — `leo-admin` | Cloudflare Pages — `leo-admin-prod` | `deploy-admin-dashboard.yml`, `docs/admin/` |
| Site produit (GitHub Pages) | branche `main` → `site/` (workflow `pages-deploy.yml`) | idem (pages = public) | V2 du protocole 03 |
| Distribution mobile | Firebase App Distribution (APK dev) | Firebase App Distribution (release) / stores | `MOBILE_FIREBASE_DISTRIBUTION.md` |
| Distribution desktop | GitHub Releases canal dev (protocole 06) | GitHub Releases canal prod (signé) | protocole 06 |
| Email transactionnel | Mailgun (domaine/sandbox dev) | Mailgun (domaine prod) | **à configurer** (le PM a indiqué « plus tard ») |
| Observabilité | Sentry dev / logs dev | Sentry prod, uptime, alertes | `docs/MONITORING_SETUP.md`, runbooks ops |
| DNS/domaines | sous-domaines dev (`?` — voir `docs/ops/DOMAINS.md`) | domaines racine | `docs/ops/DOMAINS.md`, `docs/ops/DEPLOYMENT_URLS.md` |

Règles de lecture : « volet dev » = environnement d'intégration/recette (souvent
déployé automatiquement depuis `main`), « volet prod » = environnement des clients
réels (déclenché par release). Le local (docker-compose) est un troisième espace,
**hors volet dev** : il ne reçoit jamais de secrets prod.

## 2. Principes (ENV-1)

1. **Toute capacité livrée en prod a d'abord existé en dev** (sauf hotfix de crise,
   tracé dans l'issue + CHANGELOG).
2. **Isolation totale** des secrets, clés et données entre volets (jamais de clé prod
   dans le volet dev, jamais de données réelles dans dev — fixtures uniquement,
   `docs/ops/SEEDS_PILOTES.md`).
3. **Le code est la seule vérité** : configs et déploiements versionnés
   (`render.prod.yaml`, workflows, `docker-compose.yml`), pas de configuration
   « à la main » non tracée (une config manuelle = une issue).
4. **Ce qui est sur `main` est déployable** ; ce qui est tagué est en prod ; tout écart
   à cette règle est un incident de processus (issue).

## 3. Règles

### ENV-2 — Cartographie des flux de déploiement
| Événement | Dev | Prod |
|---|---|---|
| Merge sur `main` | déploiement auto dev des surfaces impactées (`deploy-main.yml`, staging…) | rien (attente release) |
| Tag semver / workflow `release.yml` | — | gate VT-8 puis déploiement prod (`deploy-prod.yml`) |
| Hotfix | branche `fix/…` → PR | après gate allégée documentée (P0/P1) |
| Nouveau module / service | issue + registre ENV mis à jour + volet dev d'abord | après pilote/UAT |

### ENV-3 — Cohérence des schémas & contrats
- Migrations versionnées dans le repo ; **jamais de modification de schéma directe** sur
  une base (dev ou prod) — toute évolution passe par migration + CI (gardes #1962).
- Contrat API : `api/openapi.yaml` source canonique → miroir `dev-hub/openapi/v1.yaml`
  vérifié en CI (`openapi-ci.yml`) ; client mobile/web généré depuis le miroir.
- Le **numéro de version** applicatif est exposé (health endpoint, CHANGELOG) et
  comparé dev/prod lors de l'audit (ENV-9).

### ENV-4 — Secrets & accès
- Stockage : GitHub Actions secrets / variables d'environnement des plateformes ;
  jamais de secret en clair dans le repo (`.secrets.baseline`, `secret-scan.yml`,
  `secret-history-scan.yml` en CI). Référence : `docs/CI_CD_SECRETS.md`,
  `docs/ops/GOOGLE_OAUTH_ENV.md`, `docs/deployment/KEYS_A_CONFIGURER.md`.
- **Rotation** : tout secret partagé hors d'un coffre (chat, doc, ticket) est
  **révoqué et régénéré**, puis stocké au coffre. Le 2026-09-09, des jetons
  (GitHub/Vercel/Render/Cloudflare) ont transité en clair : à faire tourner (issue
  sécurité prioritaire, cf. §5).
- Accès nominatifs, moindre privilège ; revue trimestrielle des accès aux plateformes.

### ENV-5 — Données
- Dev : données de démonstration/pilotes (`SEEDS_PILOTES.md`) ; pas de copie brute de la
  prod (si besoin métier, anonymisation + autorisation + traçage).
- Prod : sauvegardes vérifiées (workflow `database-backup.yml`, runbook
  backup/restore), environnement client pilote isolé par tenant (multi-tenant).

### ENV-6 — Feature flags & kill switches
Toute bascule de capacité risquée passe par un feature flag (registre
`docs/ops/RUNBOOK_FEATURE_KILL_SWITCHES.md`) : désactivation possible sans
déploiement. Un flag activé différemment entre dev et prod est **documenté** (état
attendu dans le registre).

### ENV-7 — Toute évolution d'infra met à jour ce protocole
Ajout/retrait/déplacement d'un service, d'un domaine, d'un canal ou d'un volet :
1. issue d'infra (labels `infra` + BC-XX) avec la modification du **registre** (§1) et des
   docs concernées (`docs/ops/DOMAINS.md`, `DEPLOYMENT_URLS.md`, runbooks) ;
2. PR dédiée (jamais de modif d'infra « en passant » dans une PR fonctionnelle) ;
3. mention dans CHANGELOG.md si impact visible.
Le registre §1 et `docs/ops/DEPLOYMENT_URLS.md` sont **la** référence ; toute autre doc
d'infra datée (ex. `docs/infra/01_etat_courant/…2026-04-25.md`) est une archive, pas
l'état courant (règle documentaire de `docs/infra/README.md`).

### ENV-8 — Définition de « cohérent » (critères de l'audit)
Un système à deux volets est cohérent quand :
- chaque service du registre §1 a ses 2 volets documentés, déployés, accessibles ;
- les versions dev/prod du backend et des surfaces sont connues et l'écart est
  expliqué (dev peut être en avance ; prod ne doit **jamais** être en avance sur dev
  d'une capacité non tracée) ;
- les schémas de base dev/prod sont issus des mêmes migrations (hash/état comparé) ;
- les secrets prod n'existent pas en dev ; les données dev ne sont pas réelles ;
- la CI est verte sur `main` et le dernier tag est déployé en prod (ou écart tracé) ;
- la doc d'infra (registre §1 + DEPLOYMENT_URLS + runbooks) reflète le réel.

### ENV-9 — Audit mensuel « architecture 2 volets » (fin de mois)
1. Rejouer les critères ENV-8 (checklist ci-dessous), preuves à l'appui (URLs, versions,
   captures, logs).
2. Vérifier le registre §1 (aucun service orphelin, aucun doublon dev/prod inconnu).
3. Vérifier `docs/ops/DOMAINS.md` + `DEPLOYMENT_URLS.md` vs le réel (résolution DNS,
   certificats, redirections).
4. Vérifier l'état des secrets (présence, rotation du mois si prévue) et des backups.
5. Sorties : issues `infra` (écarts), mise à jour du registre, état
   vert/orange/rouge dans le rapport mensuel (modèle VIT-10).
6. Le PM tranche les dérives constatées (dette acceptée ou plan de correction).

### Checklist d'audit (à exécuter à chaque ENV-9)
- [ ] Registre §1 à jour et conforme au réel (services, volets, URLs)
- [ ] CI `main` verte ; dernier tag déployé en prod (ou écart tracé)
- [ ] Versions exposées (health/CHANGELOG) relevées dev vs prod
- [ ] Aucun secret prod détecté en dev / aucun secret en clair (scan CI vert)
- [ ] Backups vérifiés (prod), restauration testée ≤ 3 mois
- [ ] Monitoring/alertes actifs sur les 2 volets (Sentry, uptime)
- [ ] Docs d'infra vivantes à jour ; archives datées non utilisées pour décider
- [ ] Feature flags documentés (écarts dev/prod expliqués)
- [ ] Issues d'écart ouvertes avec preuve + responsable

## 4. Sources canoniques

| Sujet | Où |
|---|---|
| Topologie Render dev/prod | `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` |
| URLs & domaines | `docs/ops/DEPLOYMENT_URLS.md`, `docs/ops/DOMAINS.md` |
| Secrets CI | `docs/CI_CD_SECRETS.md`, `docs/ops/GOOGLE_OAUTH_ENV.md`, `docs/deployment/KEYS_A_CONFIGURER.md` |
| Déploiements | `docs/DEPLOYMENT_PRODUCTION.md`, `docs/DEPLOYMENT_STAGING.md`, `docs/GESTION_PROJET/RUNBOOK_DEPLOY.md`, `RUNBOOK_ROLLBACK.md` |
| Monitoring | `docs/MONITORING_SETUP.md`, `docs/GESTION_PROJET/RUNBOOK_OBSERVABILITY.md`, `RUNBOOK_ALERTING.md` |
| État courant (archive) | `docs/infra/01_etat_courant/ARCHITECTURE_ACTUELLE_RENDER_2026-04-25.md` |
| CI/CD globale | `docs/ARCHITECTURE_CICD.md`, `.github/workflows/README.md` |

## 5. Écarts constatés le 2026-09-09 (rattrapage)

1. **Secrets en clair dans un canal de discussion** (jetons GitHub/Vercel/Render/
   Cloudflare transmis le 2026-09-09) → **révoquer et régénérer immédiatement**, ranger
   au coffre, ne jamais redonner en clair (ENV-4). Les accès aux plateformes ont été
   utilisés en lecture seule pour établir ce registre ; aucun secret n'a été écrit dans
   ce corpus ni dans le dépôt.
2. `docs/infra/01_etat_courant/…2026-04-25.md` date d'avril : l'état courant réel est à
   re-documenter (cet audit du 2026-09-09 en est la première brique via le registre §1).
3. La topologie Vercel par module (portails dev/prod) et Cloudflare Pages n'est
   documentée nulle part comme registre : intégrer le §1 dans `docs/ops/`.
4. Mailgun dev/prod : non configuré (« plus tard ») — créer l'issue de configuration
   avec ce protocole en référence (ENV-2 : le volet dev d'abord, domaine sandbox).
