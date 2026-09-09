# Protocole 02 — Onboarding d'un agent qui intègre le projet

> **Statut : v1.0 — révisé le 2026-09-09 — propriétaire : PM**
> Code des règles : `OB-*`. « Agent » désigne ici **tout intervenant** (humain ou IA) qui
> commence à travailler sur `leopardo-hr`. Ce protocole capitalise les heures perdues
> constatées depuis le début du projet (doublons de PR, issues fermées sans correctif,
> collisions de migrations, tests « verts » qui ne vérifient rien, agents désorientés par
> un corpus documentaire immense).

## 1. Objet

Un agent qui intègre le projet doit :
1. savoir **où sont les règles** et **ne pas avoir à tout lire** pour démarrer ;
2. ne **pas répéter les erreurs historiques** (elles sont documentées, pas à redécouvrir) ;
3. être **opérationnel sur une vraie tâche** en ≤ 1 journée ;
4. **rendre sa propre leçon** avant de partir (boucle de capitalisation, protocole 04).

## 2. Principe : l'ordre de lecture est imposé, court, et suffisant

L'erreur passée : les nouveaux agents lisaient tout (ou rien), puis cassaient les gardes.
La règle : **on lit dans l'ordre, on s'arrête au niveau nécessaire à sa mission**, et les
documents d'entrée sont **des cartes, pas des encyclopédies**.

## 3. Règles

### OB-1 — Parcours d'entrée (jour 1, ~30-45 min) — ordre imposé
1. `dev-hub/prompts/00_AGENT_QUICK_CARD.md` — carte de référence rapide (2 min).
2. `dev-hub/prompts/14_ONBOARDING_AGENT.md` — premières vérifications git/gh (5-10 min).
3. `.specify/constitution.md` — loi fondamentale du projet (spec-driven development).
4. `AGENTS.md` — guide de travail complet (garde anti-doublon, BC, migrations, issues).
5. `docs/PROTOCOLES/00_SOMMAIRE.md` (ce corpus) — les 7 engagements + rituels.
6. Si mission sur une surface : `ARCHITECTURE.md` (structure monorepo), puis la doc de la
   surface (`docs/mobile/`, `docs/web/`, `docs/admin/`, `api/ARCHITECTURE.md`).
7. Vérifications machine : `git fetch && git status`, `gh pr list`, `gh issue list`,
   état CI (`gh run list --branch main`), environnement local démarré
   (`docker-compose.yml`, `Makefile`, `melos bootstrap` — cf. `docs/MONOREPO_TOOLING.md`).

### OB-2 — Mission d'atterrissage obligatoire
Avant toute tâche conséquente, l'agent réalise **une** issue étiquetée
`good first issue` (ou un lot guidé par le PM). Critère de sortie : PR mergée, propre,
avec `Closes #N` dans le body. L'atterrissage valide la compréhension des flux (branche,
CI, merge) à faible risque.

### OB-3 — La bibliothèque des erreurs est opposable (les lire AVANT de coder)
Les pièges historiques suivants sont **consultés avant la première PR** (résumés dans
`AGENTS.md`, détails dans les docs citées) :

| Piège historique | Symptôme | Garde | Réf. |
|---|---|---|---|
| 2 agents sur la même issue | PRs doublons (#2333 ×3, #2329 ×2…) | Vérifier **toutes les branches** + claim marker | AGENTS.md « règle anti-doublon » |
| Issue fermée sans correctif | Backlog « vert » mensonger | `check-issues-closed-without-merge.sh` | AGENTS.md « ghost close » |
| `Closes #N` absent du body | Issue reste ouverte après merge | `check-issues-left-open-by-merged-prs.sh` | AGENTS.md issue #2512 |
| Collision de préfixe de migration | `main` rouge pour toutes les PR | `check-migration-basename-collisions.sh` | AGENTS.md issue #1962 |
| Test « vert » qui ne vérifie rien | `PendingCommand` lazy sans `run()` | Convention + revue | `CONVENTIONS_TESTS.md` |
| Migration tenant silencieusement sautée | backfill absent | pattern `resolveTableSchema()` | AGENTS.md issue #1613 |
| Code hors BC / cross-BC | imports interdits | Module Structure Validator | AGENTS.md, registre BC |
| Texte en dur / clés i18n manquantes | surfaces multilingues cassées | revue + workflows i18n | `shared/i18n/` |

### OB-4 — Une seule branche par issue, un seul agent par BC
Appliquer strictement : vérification des branches **avant** de coder, claim marker,
nommage `fix/<issue>-<slug>` ou `bc/<code>-<slug>`, une PR = une issue (ou un lot BC).
Détails : AGENTS.md + `docs/GOUVERNANCE/*`.

### OB-5 — Interdits permanents du nouvel agent
- Écrire dans `docs/PLAN_ACTION2/` (clos) ou se fier à `PILOTAGE.md` (archivé) ;
- Créer des issues manuellement pour contourner le flux Spec Kit (`.specify/`) sur du
  travail significatif ;
- Toucher à un BC qui n'est pas confié ;
- Fermer une issue sans preuve (VT-4) ;
- Pusher un secret (cf. `SECURITY.md`, `docs/CI_CD_SECRETS.md`, baseline
  `.secrets.baseline` + secret scan en CI).

### OB-6 — Sortie de mission : la leçon est due (boucle)
Avant de quitter une mission, l'agent exécute le **RETEX de fin de mission** (protocole
04, règle RET-4) : toute douleur rencontrée, tout contournement, toute optimisation
imaginée (retenue ou non) devient une issue `RETEX:` — c'est **sa contribution au projet**
au même titre que le code. Si la leçon est généralisable, `AGENTS.md` / la doc concernée
est mise à jour **dans la même PR** (règle déjà en vigueur, rappelée par l'en-tête
d'AGENTS.md).

### OB-7 — Critères « agent opérationnel » (validation par le PM ou le donneur d'ordre)
- [ ] Parcours OB-1 exécuté (docs lues, environnement OK)
- [ ] Mission d'atterrissage mergée (OB-2)
- [ ] Connaît son BC et son lot d'issues (labels BC)
- [ ] A produit son RETEX de fin de première mission (OB-6)
- [ ] Sait exécuter : tests locaux, CI, `gh` (PR, issues, runs)

### OB-8 — Onboarding d'un agent IA (spécificités)
- Lui fournir **explicitement** : le numéro de prompt (`dev-hub/prompts/NN_*.md`),
  l'issue/lot d'issues, ou le BC confié. Jamais « débrouille-toi » (constat de sessions
  QA passées : les agents « libres » partent dans des directions non priorisées).
- Lui rappeler les contraintes d'environnement : tokens/secrets via variables d'env,
  jamais en clair dans le code, jamais de sortie réseau non autorisée.
- Son RETEX est exigé comme pour un humain (OB-6).

### OB-9 — Mise à jour du matériel d'onboarding
Chaque leçon généralisable met à jour, **dans la même PR que le correctif** :
`AGENTS.md` (guide de travail), ou une carte (`dev-hub/prompts/00_AGENT_QUICK_CARD.md`),
ou ce protocole. Un matériel d'onboarding non à jour est un piège à lui seul : la revue
de fin de mois vérifie sa fraîcheur (VIT-10).

## 4. Sources canoniques & lectures complémentaires

| Sujet | Où |
|---|---|
| Guide de travail complet | `AGENTS.md` (racine) |
| Carte rapide | `dev-hub/prompts/00_AGENT_QUICK_CARD.md` |
| Premières commandes | `dev-hub/prompts/14_ONBOARDING_AGENT.md` |
| Structure du monorepo | `ARCHITECTURE.md`, `docs/architecture/AGENT-START-HERE.md` |
| Conventions de code | `CONVENTIONS.md`, `docs/CONTRIBUTING_DDD.md` |
| Démarrage rapide | `docs/QUICKSTART.md`, `docs/DEMARRAGE_RAPIDE.md` |
| Outillage monorepo | `docs/MONOREPO_TOOLING.md` (melos, Makefile, npm --prefix) |
| Contexte rapide | `docs/CONTEXT/` |
| Production/staging | `docs/DEPLOYMENT_PRODUCTION.md`, `docs/DEPLOYMENT_STAGING.md` |

## 5. Écarts constatés le 2026-09-09 (rattrapage)

1. Il n'existe pas de **parcours unique et obligatoire** reliant cartes + bibliothèque
   d'erreurs + atterrissage + RETEX : ce protocole le crée ; l'intégrer comme nouvelle
   entrée de `dev-hub/prompts/14_ONBOARDING_AGENT.md` (étape « lis aussi
   docs/PROTOCOLES/02_ONBOARDING_AGENT.md »).
2. La « bibliothèque des erreurs » (tableau OB-3) est dispersée dans AGENTS.md : à terme,
   la déplacer dans une doc unique `docs/LEÇONS.md` référencée par les deux.
