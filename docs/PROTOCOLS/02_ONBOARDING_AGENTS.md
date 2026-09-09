# PROTOCOLE 02 — Intégration d'un nouvel agent

Statut : Actif v1.0 — 2026-09-09 | Porteur : fondateur/PM | Revue : mensuelle fin de mois, cf. docs/PROTOCOLS/00_INDEX.md

> Pourquoi : chaque nouvel entrant (agent IA ou humain) se perd aujourd'hui entre 8 documents sans ordre
> (AGENTS.md, quick card, prompt 14, AGENT-START-HERE, CONTEXT 01-04, constitution) et retombe dans des
> pièges déjà payés par les devs précédents (doublons de PRs #2400, migrations en collision #1962, issues
> jamais fermées #2512). Ce protocole capitalise ce temps perdu : un parcours unique, des réflexes d'entrée,
> un critère de sortie mesurable.

## 1. Objectif & périmètre

Ce protocole s'applique à toute personne — développeur humain ou agent IA — qui démarre une session de
travail sur le dépôt `leopardo-hr` (code, QA, ops, contenu). Il s'applique aussi, en rappel, à tout agent
déjà actif qui revient après une interruption : la section 2 reste le chemin d'entrée obligatoire.

Objectifs :

- Rendre un nouvel entrant capable de livrer une première contribution sans casser une garde CI ni dupliquer
  le travail d'un autre agent (douleurs sourcées : #2333 x3 PRs, #2329 x2 PRs — issue #2400 ; main rouge par
  collision de migrations — issue #1962 ; issues restées ouvertes après merge — issue #2512).
- Distinguer clairement ce qui relève de l'intégration d'un agent (ce protocole) de l'onboarding d'un client
  pilote (`docs/pilotes/ONBOARDING_PILOTE.md`, parcours produit < 30 min) : deux chemins différents, ne pas
  les confondre.

Périmètre hors sujet : le contexte produit/vitrine pour les profils non techniques est traité par le
protocole 03 (voir section 5).

## 2. Parcours d'entrée unique — checklist J1

Parcours chronométré, à suivre dans l'ordre. Ne lis que ce qui est listé : tout autre document (y compris
`docs/README.md`) peut référencer des fichiers obsolètes (voir section 7). Durée totale cible : 75-90 min,
environnement local déjà fonctionnel inclus.

| # | Action | Durée | Validation de l'étape |
|---|--------|-------|----------------------|
| 1 | Cloner le dépôt et vérifier l'outillage : `gh auth status`, `git lfs install` (les assets sont en vrai Git LFS, #4124). Si l'environnement local n'est pas encore monté, suivre `docs/QUICKSTART.md` (setup Docker) — et uniquement lui, pas `docs/DEMARRAGE_RAPIDE.md` (obsolète). | 15 min | `git lfs ls-files` ne montre pas de pointeurs ~130 o pour `assets/**` ; `gh issue list --limit 1` répond. |
| 2 | Lire `AGENTS.md` (racine, dernière MAJ 2026-09-05) : guide complet, règles actives et leçons récentes. C'est la référence de travail, pas un document de contexte. | 20 min | Tu peux citer la règle anti-doublon #2400, l'affectation par BC et le freeze scope #5147 sans les relire. |
| 3 | Lire `dev-hub/prompts/00_AGENT_QUICK_CARD.md` : carte de référence rapide (interdits + obligatoires). Garde-la ouverte pendant les premiers commits. | 2 min | Tu connais les 4 interdits mobile et la règle `Closes #N`. |
| 4 | Lire `dev-hub/prompts/14_ONBOARDING_AGENT.md` puis exécuter ses commandes de synchronisation : `git fetch origin main`, `git checkout main`, `git pull`, `git stash list`, `gh pr list --state open`, `gh run list --branch main --limit 3`. | 10 min | `main` local aligné sur `origin/main` ; tu sais si la CI de main est verte. |
| 5 | Lire `docs/architecture/AGENT-START-HERE.md` (entrée par bounded context) puis ouvrir `dev-hub/governance/bounded-context-registry.json` : repérer ton BC (ex. BC-15 FUEL) et ses labels d'issues. | 10 min | Tu peux nommer ton BC et le préfixe de ses issues (DEP-BCxx, MAT-*, etc.). |
| 6 | Lire, dans l'ordre indiqué par `docs/CONTEXT/README.md` : `docs/CONTEXT/01_PRODUCT_CONTEXT.md`, `docs/CONTEXT/02_TECHNICAL_CONTEXT.md`, `docs/CONTEXT/03_OPERATIONAL_CONTEXT.md`, `docs/CONTEXT/04_CURRENT_PRIORITIES.md`. | 15 min | Tu sais quelles surfaces tu touches (mobile/web/admin/api) et où est le backlog prioritaire. |
| 7 | Lire `.specify/constitution.md` : la loi fondamentale du projet. Spec-first non négociable : toute feature/module significatif commence par une spec, pas par du code. | 10 min | Tu cites les sections I à III (spec-first, multi-tenant, conformité paie). |
| 8 | Avant la première PR : lire `BRANCH_PROTECTION_REQUIRED.md` (les checks requis au merge) et `CONVENTIONS.md` (conventions de code). Ne merger jamais avec un check requis rouge. | 10 min | Tu listes les 5 checks requis : Backend Coverage >= 65 %, PHPStan Strict (niveau 8), Module Structure Validator, Frontend ESLint + TypeScript, actionlint. |
| 9 | Déclarer la fin du J1 à ton porteur (fondateur/PM) avec le BC choisi et la première issue ciblée (section 4). | 2 min | Critère de sortie de la section 4 validé ou planifié sur ta première issue. |

Règles de lecture : ne pas ouvrir `docs/archive/`, les dossiers de planification historiques
(`PLAN_ACTION`, `docs/PLAN_ACTION2/`) ni `PILOTAGE.md` pour chercher du travail (archivés — le backlog vit
sur GitHub Issues). Si un document listé ci-dessus te renvoie vers un fichier inconnu, signale-le (section 6)
plutôt que de le chercher longtemps.

## 3. Réflexes anti-pièges

Chaque ligne = une perte de temps déjà vécue, transformée en réflexe d'entrée. À appliquer dès la première
issue.

| Piège (vécu) | Réflexe d'entrée | Garde existante |
|---|---|---|
| Doublons de PRs/branches sur la même issue (#2400 : #2333 x3 PRs, #2329 x2, #2326 x2 branches) | Avant de coder : se self-assigner (`gh issue edit <N> --add-assignee @me`), vérifier TOUTES les branches contenant le numéro d'issue + les PRs ouvertes, puis pousser immédiatement la branche `fix/<issue>-<slug>` avec un commit vide « claim marker #N ». Le nom de branche EST le verrou : une seule branche par issue. | `dev-hub/tools/check-issue-claim-unique.sh` (check « PR Issue Guard » ; signalé cassé au 2026-09-08, non bloquant — cf. section 7) ; `dev-hub/tools/check-crm-branch-protocol.sh` pour le programme CRM |
| Collision de préfixes de migrations (#1962 : main rouge pour TOUTES les PRs) | Avant tout push d'une PR touchant `api/database/migrations/*` : `bash dev-hub/tools/check-migration-basename-collisions.sh`. Préfixe pris = renuméroter (`000006` -> `000007`) en gardant l'ordre chronologique. | Garde Hygiene Guards (CI) ; `dev-hub/tools/check-migration-prefixes.mjs`, `dev-hub/tools/check-migrations-tenant-schema.sh` |
| Issue jamais fermée au merge (#2512) | Mettre `Closes #N` (ou `Fixes`/`Resolves`) dans le BODY de la PR, jamais seulement dans le titre ni entre parenthèses. 1 issue = 1 PR. | `dev-hub/tools/check-pr-closes-issue.sh` (bloquant, PA2-OPS-008) ; `dev-hub/tools/check-issues-left-open-by-merged-prs.sh` (rapport non bloquant) |
| PR « Part of #N » sans issue close | Pour une tranche de campagne : créer une sous-issue de tranche et mettre `Closes #<sous-issue>` dans le body (précédent : sous-issue #7004). | `dev-hub/tools/check-pr-closes-issue.sh` (bloquant) |
| Issue travaillée hors de son BC, sans label | Ne choisir QUE des issues du BC confié, non assignées, avec critères d'acceptation clairs (labels `Agent-Ready` / `good first issue` idéalement). Une issue sans label BC : ajouter `BC-xx` depuis `dev-hub/governance/bounded-context-registry.json` avant de commencer. Un seul agent par BC à la fois. | Registre machine + `dev-hub/tools/check-bounded-context-registry.sh` (garde CI) ; Module Structure Validator (pas d'import cross-BC) |
| Feature hors périmètre (freeze scope 60 j, #5147) | Toute feature hors liste de `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` est refusée en revue. Exception : issue `[FREEZE-EXCEPTION]` décidée par le fondateur, jamais par l'agent. | Revue PR avec renvoi vers `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` |
| « Tester en staging » alors qu'il n'existe pas (#1485) | Il n'y a pas de staging réel : le tier dev/continu reçoit chaque push sur `main`, la prod n'est déployée que par GitHub Release publiée. Connaître la topologie : `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`. Ne pas traiter un rouge externe non requis (Vercel, « Workers Builds ») comme bloquant. | `BRANCH_PROTECTION_REQUIRED.md` (liste des checks requis) ; healthcheck deploy ciblé sur l'instance déployée (#6834/#6973) |

## 4. Critère de sortie d'onboarding & première mission

Un agent est considéré intégré quand il peut, en autonomie et sans assistance :

1. Choisir une issue non assignée de son BC avec critères d'acceptation clairs (`gh issue list --state open`).
2. Appliquer le cycle complet sans casser une garde : self-assign -> claim marker `fix/<issue>-<slug>` ->
   implémentation -> PR avec `Closes #N` dans le body et label BC -> 5 checks requis verts (`gh pr checks`) ->
   merge (`gh pr merge <N> --merge --delete-branch`) ou passage en review.
3. Expliquer, pour sa PR, pourquoi elle ne déclenche aucun des pièges de la section 3 (migrations, doublons,
   freeze scope, staging).

Première mission conseillée : une issue `good first issue` (ou à défaut une issue `docs:`/`chore:` simple)
du BC confié, pour valider le cycle complet sur un petit diff avant de prendre une issue `Agent-Ready`.
Exercice de validation : réaliser le cycle de bout en bout (étapes 1-9 de la section 2 + une PR mergée) en
présence du porteur, ou lui remonter la PR en review avec le récap des gardes vérifiées.

## 5. Parcours par rôle

Tous les rôles suivent d'abord la section 2, puis ajoutent leur spécialisation. Le socle de gouvernance
(section 3) est identique pour tous les rôles techniques.

| Rôle | Complément au parcours J1 | Références |
|---|---|---|
| Dev Flutter (front/mobile_apps) | Conventions mobile (interdits : `apiClient.dio.*`, `as List`, `await` avant `runApp()`, `.withOpacity()` ; obligatoires : `requestWithRetry`, `extractDataList`, `StartupGate`, tokens `glass-*`). La liste canonique des 7 apps est `front/mobile_apps/README.md`. | `dev-hub/prompts/00_AGENT_QUICK_CARD.md`, `CONVENTIONS.md` (§ mobile), audit mobile : prompt 08 |
| Dev backend Laravel (api/) | Conventions PHP (8.4, `strict_types`, modules DDD `App\Modules`, policies explicites, isolation tenant `company_id`). Réflexe migrations #1962 systématique. Tout endpoint nouveau = mise à jour `api/openapi.yaml` (CI OpenAPI sur chaque PR). | `CONVENTIONS.md` (§ PHP/Laravel), `.specify/constitution.md` (§ II-III), `dev-hub/tools/check-migration-basename-collisions.sh` |
| Agent IA | Mêmes règles qu'un dev + spec kit obligatoire : spec avant code (`.specify/constitution.md` § I), commandes `/speckit-*` (specify, clarify, plan, analyze, tasks, implement), presets actifs par zone (payroll, multitenancy, DZ/CEMAC/CEDEAO). Auto-assignation et claim marker encore plus critiques en parallèle (swarm). | `.specify/constitution.md`, `AGENTS.md` (Spec Kit), `docs/specifications/` (specs validées) |
| QA | Mêmes règles + savoir distinguer un rouge requis d'un rouge externe non requis (Vercel, Workers Builds) et les gardes signalées cassées (section 7). Savoir rejouer un parcours pilote chronométré. | Prompts 02 (audit gardien), 13 (anti-régression) ; audits 05-09 ; `docs/pilotes/ONBOARDING_PILOTE.md` ; `docs/qa/` |
| Ops / hotfix | Mêmes règles + topologie de déploiement et SLA bugs pilotes : bloquant = paie/pointage/login impossible, résolution < 24 h, hotfix `hotfix/<issue>-<slug>`, CI minimale, jamais d'auto-merge d'une PR rouge. | `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`, `docs/ops/SLA_PILOTES.md`, prompt 10 (checklist pré-déploiement), `docs/ops/RUNBOOK_MERGE.md`, template `.github/ISSUE_TEMPLATE/pilot_blocker.yml` |
| Commercial / marketing | Le parcours technique ne s'applique pas. Contexte produit minimal : `docs/CONTEXT/01_PRODUCT_CONTEXT.md` (promesse, personas, surfaces). Pour tout besoin vitrine/GTM, suivre le protocole dédié. | Renvoi : `docs/PROTOCOLS/03_VITRINE_PRESENTATION.md` |

## 6. Rituel mensuel & boucle d'amélioration

Rituel mensuel (fin de mois, porteur fondateur/PM) : relire la section 7, intégrer les remontées du mois,
mettre à jour ce protocole (nouveau piège dans la section 3, chemin corrigé dans la section 2) et l'état des
lieux. L'index des protocoles vit dans `docs/PROTOCOLS/00_INDEX.md`.

Boucle d'amélioration — toute perte de temps rencontrée par un nouvel entrant doit mettre à jour ce
protocole, via la capitalisation d'expérience :

1. Dès qu'un blocage ou une incohérence documentaire coûte du temps à un nouvel entrant, ouvrir une issue
   (ou une entrée dans le carnet de capitalisation) avec le format : « j'ai perdu X min à cause de Y ; le
   doc Z dit A mais la réalité est B ; correctif proposé ».
2. Les leçons confirmées sont ensuite reportées dans ce protocole (sections 2/3/7) et, si elles touchent
   les règles de travail, dans `AGENTS.md` + `CHANGELOG.md` (règle existante : toute leçon opérationnelle
   s'ajoute à `AGENTS.md` à chaque merge).
3. Un nouvel entrant qui suit ce protocole et tombe sur un document obsolète ne le contourne pas : il le
   signale avec l'issue de capitalisation, c'est sa contribution à la gouvernance.

Mécanique de capitalisation : voir `docs/PROTOCOLS/04_CAPITALISATION_EXPERIENCE.md`.

## 7. État des lieux au 2026-09-09

Incohérences connues et vérifiées à la date de rédaction — à corriger au fil de l'eau, signalées ici pour
ne pas piéger un nouvel entrant :

- Parcours d'entrée éclaté : les règles actives sont réparties entre `AGENTS.md`, la quick card, le prompt
  14, `docs/architecture/AGENT-START-HERE.md`, les quatre fichiers `docs/CONTEXT/01_PRODUCT_CONTEXT.md` à
  `04_CURRENT_PRIORITIES.md` et `.specify/constitution.md`, sans ordre ni durée. La section 2 de ce protocole
  est l'ordre unique ; les autres documents restent des références, pas des points d'entrée.
- `PILOTAGE.md` est ARCHIVÉ (issue #6698, 2026-09-02) : ne plus s'y référer pour une décision. La gestion
  de projet vit sur GitHub Issues/Projects ; roadmap dans `docs/REFERENTIEL_PRODUIT/ROADMAP.md`.
- `docs/DEMARRAGE_RAPIDE.md` est obsolète (en-tête : voir `docs/QUICKSTART.md`) mais `docs/README.md` le
  référence encore dans son tableau « Démarrage rapide ». `docs/README.md` peut donc référencer des
  documents obsolètes : vérifier un chemin avant de le suivre, et ne lire que la section 2 de ce protocole.
- `docs/CONTEXT/01_PRODUCT_CONTEXT.md` liste 4 apps mobiles (+ `leopardo_core`) alors qu'`AGENTS.md` en
  liste 7. La liste canonique : `AGENTS.md` + `front/mobile_apps/README.md` (aligné sur `melos.yaml`).
- Garde « PR Issue Guard » (`dev-hub/tools/check-issue-claim-unique.sh`) signalée cassée au 2026-09-08
  (HTTP 403 puis erreur de parsing) : elle rend le check rouge sur toutes les PRs mais n'est PAS bloquante
  pour le merge (seuls les 5 checks requis de `BRANCH_PROTECTION_REQUIRED.md` bloquent). À corriger dans
  l'outillage.
- `docs/pilotes/ONBOARDING_PILOTE.md` pointe vers `docs/pilotes/SLA_PILOTES.md`, qui n'existe pas : le
  fichier réel est `docs/ops/SLA_PILOTES.md`.
