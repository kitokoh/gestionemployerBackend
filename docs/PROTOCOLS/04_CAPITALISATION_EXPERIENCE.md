# PROTOCOLE 04 — Capitalisation de l'expérience en tâches & issues

> **Statut** : Actif v1.0 — 2026-09-09
> **Porteur** : chaque agent (constat, issue, implémentation directe) ; agent PM (triage, moisson mensuelle, arbitrage des cas limites)
> **Revue** : mensuelle, fin de mois — cf. docs/PROTOCOLS/00_INDEX.md
>
> **Pourquoi** : les enseignements du travail (succès, échecs, temps perdu, optimisations tentées) sont aujourd'hui bien *documentés* (AGENTS.md, CHANGELOG.md, rétros, rapports datés) mais ne deviennent pas systématiquement *actionnables* : pas de gabarit d'issue de retour d'expérience, pas de label dédié, pas de seuil d'arbitrage « je corrige / je délègue », pas de moisson des rapports figés. Une leçon reste donc là où elle est née — dans la tête ou le rapport de celui qui l'a vécue — et un autre agent la revivra. Ce protocole ferme la boucle.

## 1. Objectif & périmètre

Tout agent doit pouvoir, depuis ce qu'il vient d'apprendre :

1. créer sans friction une issue ou une tâche exploitable ;
2. l'implémenter lui-même si elle est petite, ou la laisser à un autre agent ;
3. garantir que l'enseignement servira réellement : l'issue doit être « agent-ready », réalisable par un autre agent sans le contexte de son auteur.

**Périmètre** : toutes les leçons issues du travail sur le dépôt — récidives et bugs évitables, causes de temps perdu, dette découverte, optimisations tentées (validées ou non), succès reproductibles à généraliser.

**Hors périmètre** (flux dédiés existants, non modifiés par ce protocole) :

- bug bloquant pilote → template `pilot_blocker.yml`, SLA < 24 h (docs/ops/SLA_PILOTES.md) ;
- vulnérabilité de sécurité → template `security_vulnerability.md` ;
- demande de fonctionnalité → template `feature.yml` (critères d'acceptation obligatoires).

Ce protocole renvoie vers ces flux au lieu de les dupliquer.

## 2. La boucle expérience → issue → leçon

**État actuel (sens unique)** : l'expérience est capitalisée dans des documents — AGENTS.md (règle « mis à jour dès qu'une leçon opérationnelle peut éviter de perdre du temps plus tard », sections de leçons datées), CHANGELOG.md, docs/JOURNAL_RACINE.md, rétros et carnets pilotes (docs/pilotes/), rapports datés figés (docs/validation/). Le sens manquant : documents et notes → issues → correction, puis retour de la correction vers la leçon consolidée.

**Boucle cible en 5 étapes** :

1. **Constat** — au fil de l'eau, un agent identifie une leçon. Il la note immédiatement et brièvement (entrée du jour, docs/JOURNAL_RACINE.md, doc datée existante) pour ne rien perdre.
2. **Qualification** — arbitrage à seuils (§4) : l'agent décide seul. Zone verte → implémentation directe. Sinon → issue « retour d'expérience » (§3) dans le backlog.
3. **Exécution** — directe : branche `fix/<issue>-<slug>` (ou `bc/<code>-<slug>` en lot), PR avec `Closes #N` — mêmes règles que toute PR. Déléguée : l'issue étiquetée et priorisée (§6) attend l'affectation par BC ou la moisson.
4. **Consolidation** — la leçon validée par l'implémentation est consignée : AGENTS.md si leçon opérationnelle (règle existante du fichier), CHANGELOG.md si livraison ; l'issue REX est clôturée en commentaire avec le numéro de PR et ce qui a été confirmé ou infirmé.
5. **Moisson mensuelle** (§7) — l'agent PM convertit en issues les rapports figés du mois et les notes, trie, déduplique et priorise.

```
 [1] Constat ──note courte──▶ [2] Qualification (seuils §4)
                                   │ zone verte          │ sinon
                                   ▼                     ▼
                      [3] Implémentation directe   [3'] Issue « REX / dette » (§3)
                          PR fix/#N (Closes #N)        ▶ backlog, labels, priorité
                                   │                     │
                                   ▼                     ▼
                      [4] Leçon consolidée        [4'] Un AUTRE agent l'implémente
                          AGENTS.md / CHANGELOG          (issue agent-ready §5)
                                   │                     │
                                   └──────────▶ [5] Moisson mensuelle (retour backlog)
```

Règle de fermeture : une issue REX fermée sans que la leçon ait été consolidée (étape 4) = boucle non fermée.

## 3. Gabarit « retour d'expérience / dette découverte »

**Titre** : `[REX] <BC ou surface> : <verbe à l'infinitif> <objet> — <leçon en 1 ligne>`

Exemples :

- `[REX] BC payroll : verrouiller le nommage des migrations — collisions de préfixes récurrentes`
- `[REX] .github/workflows : cibler l'instance déployée au healthcheck — gate aveugle sur le tier dev`

**Bloc à copier dans le corps de l'issue** :

```
## Contexte
D'où vient la leçon (tâche, incident, rapport daté, rétro) : source + date.
Pourquoi c'est important pour la suite.

## Symptôme (ou succès reproductible)
Ce qui a été observé. Pour une leçon positive : ce qui a bien fonctionné
et à quelles conditions.

## Cause
Pour un échec : pourquoi c'est arrivé. Pour un succès : ce qui l'a rendu possible.

## Impact
Coût si on ne fait rien (temps perdu, récidive attendue, risque pilote/produit).
Justifie la priorité (§6).

## Correction proposée
Option retenue et pourquoi. Optimisations déjà tentées et leurs résultats
(évite de re-tester un cul-de-sac).

## Portée
BC / fichiers / surfaces concernés (chemins réels vérifiés).

## Critères d'acceptation
- [ ] condition vérifiable 1
- [ ] condition vérifiable 2

## Définition of Done (issue)
- [ ] PR mergée avec `Closes #<cette issue>` dans le body (garde #2512)
- [ ] entrée CHANGELOG.md si livraison
- [ ] AGENTS.md mis à jour si leçon opérationnelle
- [ ] tests et lints requis verts (constitution §IV)
```

Règle de bascule : une leçon décrivant un bug **bloquant** actif en prod passe par `pilot_blocker.yml` (SLA 24 h) ; un besoin de sécurité par `security_vulnerability.md`. Le gabarit ci-dessus couvre la dette et la prévention, pas l'urgence.

## 4. Implémenter directement ou déléguer ?

Principe : l'agent arbitre **seul** grâce aux seuils ci-dessous ; l'agent PM n'arbitre que les cas limites. En cas de doute → issue : une issue coûte cinq minutes, une leçon perdue coûte sa récidive.

| Critère | Implémentation directe | Issue (délégation) |
|---|---|---|
| Portée | ≤ 1 fichier (ou ensemble homogène minuscule) | plusieurs fichiers / modules |
| Effort estimé | ≤ 1 h | > 1 h |
| BC impacté | confié à l'agent (affectation par BC) | BC d'un autre agent — jamais toucher |
| Freeze 60 j | hors périmètre gelé | périmètre gelé → issue + `[FREEZE-EXCEPTION]` décidée par le fondateur |
| Risque | faible (cosmétique, doc, outillage) | donnée, paie, tenant, sécurité, migration → jamais direct |
| Spec requise | non | oui → spec spec kit d'abord (constitution §I), puis issue |
| Tests | couverts ou ajout trivial | tests à écrire (logique métier) |

Règles d'exécution :

- Le direct **ne dispense d'aucune règle existante** : branche + claim marker (protocole #2400), `Closes #N` dans le body (#2512), entrée CHANGELOG.md, CI verte (constitution §IV/VII). Le « direct » dispense du ticket, pas des règles.
- **Auto-limitation** : si l'effort dépasse l'estimation, s'arrêter, ouvrir l'issue REX avec l'analyse déjà produite et laisser la suite au backlog.
- **Délégation immédiate** : créer l'issue dans la foulée du constat, jamais « plus tard » — une leçon non écrite le jour même est perdue.
- Le PM ne pilote pas le flux au ticket près : il arbitre (a) les issues au BC/label incertain, (b) les demandes d'exception au freeze (décision du fondateur, jamais de l'agent), (c) la priorité à la moisson. L'agent ops garde son flux dédié (pilot-blocker, hotfix — docs/ops/SLA_PILOTES.md) ; le gardien du merge reste l'agent PM (docs/ops/RUNBOOK_MERGE.md).

## 5. Format « agent-ready »

Toute issue issue d'une leçon doit être réalisable par un **autre** agent sans échange : c'est la condition pour que l'enseignement serve réellement. Exigences minimales, toutes dans le corps de l'issue :

1. **Contexte autonome** — tout ce que l'auteur sait est écrit ; la source de la leçon est citée (fichier + date, ou issue). Jamais de « comme je l'ai dit ».
2. **Critères d'acceptation vérifiables** — checklist de conditions observables (obligatoire, même exigence que `feature.yml`) : l'agent qui prend l'issue sait quand il a fini.
3. **DoD explicite** — bloc §3 (PR + `Closes #N` + CHANGELOG + AGENTS.md + tests).
4. **Portée et fichiers** — chemins réels vérifiés + label BC (registre `dev-hub/governance/bounded-context-registry.json`), pas de « module concerné » vague.
5. **Pièges connus et tentatives déjà faites** — résultats des optimisations essayées, positifs comme négatifs.
6. **Découpage** — une issue = un enseignement ; les tickets fourre-tout sont interdits (règle de `dev-hub/prompts/04_CREATE_ISSUES.md`).
7. **Dédoublonnage préalable** — `gh issue list --search "<mots clés>"` avant création (idem prompt 04).

Auto-test de l'auteur avant de lâcher l'issue : « un agent qui n'a jamais travaillé avec moi peut-il la réaliser sans me poser de question ? » Si non → compléter. Si oui → poser le label `Agent-Ready` : l'issue devient prioritaire pour les agents en recherche de tâche (AGENTS.md, `dev-hub/prompts/01_DRAIN_BACKLOG.md`).

## 6. Labels & triage

**Constat** : aucune catégorie ne distingue aujourd'hui les issues issues d'un retour d'expérience. Les labels existants couvrent le type de travail (bug, enhancement, pilot-blocker, security), le BC (BC-XX du registre), la priorité (`P1` dans `pilot_blocker.yml` ; famille `P0-critical`/`P1-high`/`P2-medium`/`P3-low` préconisée par `dev-hub/prompts/04_CREATE_ISSUES.md`), la préparation (`Agent-Ready`) et le préfixe `[FREEZE-EXCEPTION]` (docs/GOUVERNANCE/FREEZE_SCOPE_60J.md). Quelques tickets historiques portaient la mention `tech-debt` dans leur intitulé (ex. #4508 web/tech-debt — docs/qa/QA_SESSION_2026-08-16-swe-qa-merge-drain.md) sans label ni gabarit canoniques.

**Décisions** :

1. Créer le label **`tech-debt`** (recommandé : catégorie de travail standard pour dette découverte et re-travail évitable) :
   `gh label create tech-debt --description "Retour d'experience / dette decouverte — protocole 04" --color B60205`
   Alternative acceptable si l'équipe préfère marquer la source : **`lecon`**. Un seul des deux suffit au tri ; ne pas les empiler.
2. Labels systématiques d'une issue REX : `BC-XX` (registre), `tech-debt` (ou `lecon`), priorité, et `Agent-Ready` si le format §5 est complet.
3. Priorité : `P1` seulement pour un re-travail récurrent avéré ou un risque de parcours pilote ; `P2` pour la dette d'hygiène ; les urgences passent par les flux bug/security/pilot-blocker, jamais par `tech-debt`.
4. Triage : l'auteur pose les labels à la création ; l'agent PM re-trie à la moisson et tranche les BC incertains. Le drain du backlog continue de privilégier `Agent-Ready` et `P1`.

## 7. Moisson mensuelle (checklist)

Rituel de fin de mois — porteur : agent PM, le même jour que la revue des protocoles (cf. docs/PROTOCOLS/00_INDEX.md). Convertir les enseignements figés en issues, trier, prioriser :

1. **Collecter** les sources du mois : rapports datés de `docs/validation/` (fichiers `*_YYYY_MM_DD*` et `*_YYYY-MM-DD*` — figés par nature, cf. docs/validation/README.md : jamais mis à jour rétroactivement, leur contenu doit donc être rejoué en issues) ; rétro pilotes du mois (`docs/pilotes/RETRO_<date>.md`) ; entrées du mois de `docs/JOURNAL_RACINE.md` ; carnets hebdo (`docs/pilotes/CARNET_*.md`) ; notes QA/ops datées.
2. **Dépouiller** : pour chaque enseignement actionnable, créer UNE issue au gabarit §3 en citant la source datée (fichier + date + n° de rétro/issue si présent).
3. **Dédupliquer** : recherche préalable `gh issue list --search` ; fusionner avec une issue équivalente ouverte (commentaire de renvoi) plutôt que créer un doublon.
4. **Étiqueter** : BC, `tech-debt` (ou `lecon`), priorité (§6) ; compléter le format §5 et poser `Agent-Ready` avant de clore la moisson.
5. **Confronter au freeze** (FREEZE_SCOPE_60J.md) : enseignement hors périmètre autorisé → proposer une issue `[FREEZE-EXCEPTION]` au fondateur (décision humaine), ne pas l'ouvrir en vrac.
6. **Prioriser** avec le backlog existant (impact x fréquence de récidive) ; les affectations suivent le principe « par BC, un agent par BC » (AGENTS.md).
7. **Boucler** : chaque leçon majeure du mois a une issue (ouverte, faite ou rejetée motivée) OU une entrée AGENTS.md — aucune leçon ne reste prisonnière d'un rapport figé.
8. **Tracer** : entrée `docs/JOURNAL_RACINE.md` + entrée CHANGELOG.md (`chore:` moisson) ; lister dans la revue mensuelle les leçons non transformées et pourquoi.

## 8. État des lieux au 2026-09-09

**Capitalisation existante** (sens documents ← expérience ; non modifiée par ce protocole) :

- `AGENTS.md` (racine) : règle de mise à jour continue (« dès qu'une leçon opérationnelle peut éviter de perdre du temps plus tard »), sections de leçons datées (ex. famine du pipeline #3545, gate de deploy #6834/#6973), gardes #2400 (verrou branche + claim marker), #2512 (`Closes #N`), #1962 (collisions de migrations), affectation par BC (registre #5859), freeze #5147.
- `CHANGELOG.md` (entrée par PR, règle #2417) ; `docs/JOURNAL_RACINE.md` (« qui a fait quoi, quand ») ; `docs/pilotes/RETRO_2026-08-24.md` (rétro baseline #5157) ; `docs/pilotes/CARNET_TEMPLATE.md` (carnets hebdo #5152) ; rapports datés figés `docs/validation/` (cf. README.md).
- Templates d'issues : `bug.yml`, `feature.yml` (critères d'acceptation obligatoires), `pilot_blocker.yml` (SLA < 24 h), `good_first_issue.md`, `security_vulnerability.md`. Aucun gabarit « retour d'expérience / dette » — trou comblé par §3.
- Labels existants : BC-XX (registre `dev-hub/governance/bounded-context-registry.json`), `pilot-blocker`, `Agent-Ready`, priorité P0/P1/P2, préfixe `[FREEZE-EXCEPTION]`. Aucun label « lecon » / « tech-debt » — trou comblé par §6.
- Règles de travail auxquelles ce protocole renvoie sans les réécrire : spec kit (`.specify/constitution.md`) ; 1 issue = 1 PR avec `Closes #N` (#2512) ; branche `fix/<issue>-<slug>` + claim (#2400) ; lots par BC sur `bc/<code>-<slug>` (`docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md`) ; affectation par BC, un agent par BC (AGENTS.md) ; freeze 60 j (`docs/GOUVERNANCE/FREEZE_SCOPE_60J.md`).

**Restes à faire** (hors champ de ce document) :

- créer le label `tech-debt` (ou `lecon`) — commande en §6 ;
- (fait avec la série) gabarit §3 porté en template `.github/ISSUE_TEMPLATE/retour_experience.yml` — ouvrable via le sélecteur GitHub ;
- référencer ce protocole dans `docs/PROTOCOLS/00_INDEX.md` (créé avec la série, v1.0 — 2026-09-09) ;
- première moisson mensuelle : fin septembre 2026 (convertir notamment la rétro pilotes J46 attendue au 2026-10-03 et les rapports datés du mois).
