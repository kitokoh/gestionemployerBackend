# Protocole P7 — Gouvernance de l'architecture : le double volet dev/prod reste réfléchi, documenté, sans dérive silencieuse

| | |
|---|---|
| **Statut** | PROPOSÉ (2026-09-09) — à valider par le PM |
| **Dernière revue** | 2026-09-09 |
| **Owner** | PM + lead technique (architecture) |
| **Périmètre** | L'architecture « à deux volets » — **volet développeurs** (local, dev, staging) et **volet production** — et leur cohérence permanente |
| **Dépend de** | `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` (topologie canonique, vérifiée 2026-09-05), `docs/ARCHITECTURE_STATUS.md`, `ARCHITECTURE.md`, `render.yaml` (dev) & `render.prod.yaml` (prod), `docs/RELEASE_PROCESS.md`, `architecture-check.yml`, P1, P6 |
| **Produit** | `docs/ops/ARCHI_REVIEW_YYYY_MM.md` chaque fin de mois |

---

## 1. Objet et constat

L'architecture vit en deux volets : **dev** (Render `gestionemployerbackend` + workers
`leopardo-queue-worker`/`leopardo-scheduler`, Neon Postgres dev, docker-compose local) et **prod**
(Render `leopardo-prod` + `leopardo-redis-prod`, Neon branche production, Vercel = vitrine web,
Cloudflare Pages = `leo-admin-prod`, edge chez le client, Firebase App Distribution pour le mobile).

Elle est bien pensée mais elle **dérive dès qu'on ne la surveille pas** : workers/scheduler commentés
dans `render.prod.yaml` (plan gratuit), domaine `api.leopardo-rh.com` en NXDOMAIN (#3452/#3905),
« e2e staging » qui tourne en réalité contre la prod, `docs/RELEASE_PROCESS.md` qui ne couvre que l'API.
Ce protocole rend la dérive **visible, tracée et traitée** — jamais silencieuse.

## 2. Source de vérité unique (règle de fer)

1. `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` = **le document canonique** de topologie (dev vs prod) ;
   `docs/ARCHITECTURE_STATUS.md` = l'état courant (dettes, décisions, prochaines étapes) ;
   `ARCHITECTURE.md` = les principes (bounded contexts, multi-tenant).
2. **Toute modification d'infrastructure** (service, fournisseur, domaine, worker, base, secret,
   environnement) = PR qui modifie **le code OU le yaml ET la topologie/statut** dans le même ensemble
   de changements. Une infra non documentée le jour même est une infra inexistante pour le reste de l'équipe.
3. Un **écart volontaire dev/prod** (ex. workers absents en prod) est autorisé **s'il est écrit** :
   commentaire dans le yaml + ligne dans la topologie + issue de résolution référencée. Un écart sans
   issue n'est pas un choix, c'est une fuite.

## 3. Table de parité dev ↔ prod (état au 2026-09-09)

> Table **récapitulative** : la source de vérité reste `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`,
> à maintenir dans la même PR que tout changement d'infrastructure (§2).

| Composant | Dev | Prod | Parité | Écart & issue |
|-----------|-----|------|--------|----------------|
| Web API (Render) | `gestionemployerbackend` | `leopardo-prod` | ✅ | — |
| Queue worker | `leopardo-queue-worker` | commenté (`render.prod.yaml`) | 🔴 | Prod mono-conteneur assumée — issue de résolution requise avant charge réelle |
| Scheduler | `leopardo-scheduler` | commenté | 🔴 | Idem |
| Redis | optionnel | `leopardo-redis-prod` | 🟡 | Parité à documenter |
| PostgreSQL | Neon dev | Neon branche « production » | ✅ | Migrations au boot + search_path runtime (#6916/#6924) |
| Domaine API | api onrender | `api.leopardo-rh.com` **NXDOMAIN** | 🔴 | #3452/#3905 — à corriger avant lancement commercial (P1) |
| Vitrine web | Vercel `leopardo` (front/web, push main) | Vercel `leopardo` (front/web, tag `v*`) + gh-pages | 🟡 | Doublons à arbitrer (P3) |
| Admin | Cloudflare Pages `leo-admin` | Cloudflare Pages `leo-admin-prod` | ✅ | — |
| Mobile | Firebase App Distribution (staging) | tags `v*` → stores/Firebase prod | 🟡 | Diffusion prod formelle non documentée (P6) |
| Environnement de validation | dev (sur main) | « staging » = **prod réelle** | 🔴 | Incohérence : les e2e « staging » frappent la prod — créer un vrai staging ou assumer + protéger |
| Edge/kiosk | docker-compose | Docker client (edge) | 🟡 | Packaging installateur à définir |

Objectif permanent : **aucun 🔴 sans issue ouverte datée et sans propriétaire.**

## 4. Rituel mensuel — revue d'architecture (dernier jour ouvré, fenêtre P3/P4/P5)

`docs/ops/ARCHI_REVIEW_YYYY_MM.md` :
- [ ] Re-vérifier la table de parité §3 (état réel des services, pas l'état déclaré — leçon #6834/#6973)
      **et sa cohérence avec la topologie canonique** (la table résume ; la topologie fait foi).
- [ ] Fraîcheur de la topologie : dernière modification < 30 j ? sinon, pourquoi ?
- [ ] Dérives silencieuses détectées (🔴 sans issue) → issues immédiates (label `architecture`, P4).
- [ ] Dettes & coûts : `docs/ARCHITECTURE_STATUS.md`, budget infra (cf. `docs/ops/BUDGET_AGENTS.md`),
      consommation Render/Vercel/CF du mois.
- [ ] Avancement des chantiers en cours (ex. staging réel, domaine API, P6 desktop, edge).
- [ ] Décisions d'architecture du mois consignées (ADR léger : contexte → décision → conséquence),
      annexées à la topologie ou à `docs/ARCHITECTURE_STATUS.md`.
- [ ] Décision : l'architecture dev/prod est-elle « réfléchie et à jour » ce mois-ci ? Oui/Non + priorités.

## 5. Règles de vie des environnements

1. **Dev** = vérité quotidienne : tout push `main` → dev (Render), tests complets, déploiement automatique.
2. **Prod** = vérité du marché : uniquement par **tag `vX.Y.Z`** → `release.yml`/`deploy-prod.yml`
   (re-vérification des 5 checks sur le commit du tag). Pas de déploiement prod hors tag (P1).
3. **Staging** : état actuel insuffisant (voir §3) → décision PM sous 90 j : (a) provisionner un vrai
   environnement staging (recommandé avant multi-clients, cf. « suite recommandée » de la topologie),
   ou (b) acter que « dev = staging » avec garde-fous et **cesser de nommer « staging » des tests qui
   frappent la prod**. En attendant : tout e2e contre la prod est identifié comme tel dans son rapport.
4. **Secrets** : inventaire `docs/CI_CD_SECRETS.md` tenu à jour ; un secret prod n'est jamais identique
   ni partagé avec dev ; rotation selon politique ; jamais en clair dans le repo (secret scan actif).
5. **Local** : `docker-compose.yml` reste l'image fidèle du volet dev (api, postgres, redis, queue,
   scheduler, mailpit, dashboard, web). Si dev change, le compose change dans la même PR.

## 6. Gouvernance des changements d'architecture

Un changement de topologie (nouveau service, migration de fournisseur, nouveau domaine, nouveau type
de déploiement — desktop P6, edge, kiosk) passe par :
1. Issue/spec (Spec Kit si module ; `constat interne` si dette — P4) décrivant l'impact **dev ET prod** ;
2. Revue architecture (humaine) ; `architecture-check.yml` vert ;
3. PR unique : code/yaml + topologie + statut (§2) ;
4. Post-déploiement : vérification réelle de l'état (leçon gate aveugle #6834/#6973) et mise à jour de la table §3.
Tout changement qui ne respecte pas ce chemin est réversible de droit (revert) — c'est la règle qui
protège le « bien réfléchi » contre l'empilement.

## 7. Rôles

| Rôle | Responsabilité |
|------|----------------|
| PM | Arbitre les choix structurants (fournisseurs, staging, coûts), décide les 🔴 prioritaires |
| Lead technique (architecture) | Topologie à jour, revue des PR infra, ADR, rituel mensuel |
| Agents | Ne modifient jamais l'infra sans issue + PR documentée (§6) ; signalent toute dérive observée |

## 8. Indicateurs

- Nombre de 🔴 de parité sans issue (cible : 0) ; âge de la topologie (cible : < 30 j).
- Nombre de déploiements prod hors process (cible : 0).
- Écarts dev/prod « volontaires » documentés vs silencieux (cible : 100 % documentés).
- Coût mensuel par environnement suivi (alerte si dérive > seuil défini par le PM).
