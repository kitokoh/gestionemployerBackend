# Protocole 04 — Capitalisation : l'expérience de chaque agent devient des issues exploitables

> **Statut : v1.0 — révisé le 2026-09-09 — propriétaire : PM**
> Code des règles : `RET-*`.
> Objectif : que **l'enseignement tiré d'une expérience serve réellement** — à l'agent qui
> l'a vécue (il peut l'implémenter lui-même) ou à d'autres (issue claire, priorisée,
> prenable). Le projet a déjà payé plusieurs fois les mêmes erreurs (PR doublons,
> ghost-close, migrations, tests faux-verts, docs obsolètes) : la capitalisation doit
> devenir un **réflexe encadré**, pas un vœu.

## 1. Déclencheurs — quand un RETEX est dû (RET-1)

| Déclencheur | Délai | Exemple |
|---|---|---|
| Fin de mission / fin de lot d'issues | à la PR de clôture | toute mission OB-2/OB-6 |
| Incident ou bug P1/P0 résolu | dans la PR de correctif | incident prod, régression |
| Douleur constatée **2 fois** | immédiat (2e occurrence) | 2e migration en collision, 2e test faux-vert |
| Optimisation/tentative imaginée et non retenue | au moment de la décision | « on avait pensé à X, finalement Y car … » |
| Session d'audit / QA | en fin de session | sessions `docs/qa/` |
| Contournement manuel d'une garde | immédiat | désactiver un check « pour avancer » |

## 2. Le template RETEX (RET-2) — modèle à poser dans `.github/ISSUE_TEMPLATE/retex.yml`

```yaml
name: RETEX — Retour d'expérience
description: Capitaliser une leçon opérationnelle (douleur, optimisation, tentative) pour qu'elle devienne une action.
title: "RETEX: <sujet court>"
labels: [retex]
body:
  - type: input
    id: domaine
    attributes:
      label: Domaine / BC
      description: BC-XX (registre bounded-context-registry.json) ou surface (CI, docs, infra, design, vitrine).
    validations: { required: true }
  - type: input
    id: date
    attributes: { label: Date de l'expérience }
    validations: { required: true }
  - type: dropdown
    id: type
    attributes:
      label: Type
      options: [correction, optimisation, dette, processus, design, vitrine, infra]
    validations: { required: true }
  - type: textarea
    id: symptome
    attributes:
      label: Ce qui s'est passé (symptôme concret)
      description: Faits, pas jugements. Combien de temps cela a coûté ?
    validations: { required: true }
  - type: textarea
    id: cause
    attributes:
      label: Cause racine
      description: Pourquoi cela est arrivé ? (règle manquante, doc ambiguë, garde absente…)
    validations: { required: true }
  - type: input
    id: preuve
    attributes:
      label: Preuve (PR / commit / issue / capture)
      description: Lien vers l'élément qui prouve l'expérience.
  - type: textarea
    id: action
    attributes:
      label: Action recommandée
      description: Ce qu'il faudrait faire pour que cela ne se reproduise pas (garde, doc, refactor, processus).
    validations: { required: true }
  - type: dropdown
    id: prise
    attributes:
      label: Prise en charge
      options:
        - Je l'implémente moi-même (si BC confié et portée courte)
        - À mettre au backlog (tri PM)
```

## 3. Règles de traitement

### RET-3 — Qualité d'un RETEX
Une issue RETEX est exploitable si : symptôme factuel + cause racine + preuve + action
recommandée. Sans ces 4 éléments, elle est renvoyée à l'auteur (commentaire), pas
triée. Un RETEX sans action possible est refusé : soit c'est un constat (→ doc, pas
issue), soit l'action n'est pas encore claire (→ discussion, pas backlog).

### RET-4 — Le RETEX de fin de mission est obligatoire (boucle fermée)
Toute mission (protocole 02, OB-6) se termine par :
1. Création de l'issue RETEX (template ci-dessus) **ou** mention explicite
   « aucun enseignement » (commentaire sur l'issue de mission),
2. Si la leçon est généralisable → mise à jour de `AGENTS.md` / doc concernée **dans la
   même PR** que le correctif (règle d'en-tête d'AGENTS.md),
3. L'issue RETEX **ne ferme pas** la mission : elle est traitée par RET-5/6.

### RET-5 — L'agent peut implémenter sa propre leçon, sous conditions
« Je l'implémente moi-même » est autorisé **si et seulement si** :
- le BC est confié à cet agent (affectation par BC, AGENTS.md),
- la portée est courte (≤ ~1 jour, pas de chantier transverse),
- le périmètre n'est pas gelé (`FREEZE_SCOPE_60J.md`) sans dérogation PM,
- le flux normal s'applique : branche `fix/<issue>-<slug>` ou lot BC, PR avec
  `Closes #<issue RETEX>`.
Tout le reste part au **backlog** (label `retex` conservé) pour le tri du PM.

### RET-6 — Tri hebdomadaire/mensuel par le PM
- **Hebdo (si volume)** : survol des nouvelles issues `retex` non étiquetées BC.
- **Mensuel (fin de mois, avec la revue des protocoles)** : les RETEX en attente sont
  priorisés (impact × fréquence vs coût), complétés d'un label de nature (`process`,
  `tech-debt`, `design`, `vitrine`, `desktop`, `infra`…), d'un BC, d'une priorité, et
  d'un verdict : `à faire` / `à documenter` / `wontfix motivé` / `à fondre dans un
  protocole`. Le verdict est un commentaire sur l'issue.
- Une issue `retex` ne reste **jamais** ouverte plus de 2 cycles mensuels sans verdict.

### RET-7 — Les « tentatives d'amélioration non retenues » sont documentées
Quand une optimisation est imaginée puis écartée (coût, risque, timing), l'agent crée
une issue RETEX type `process`/`optimisation` avec le motif d'abandon **ou** ajoute une
note dans la doc de conception concernée (ADR dans `docs/architecture/`). Objectif :
éviter que chaque nouvel agent redécouvre et re-propose la même chose (temps perdu
constaté à plusieurs reprises).

### RET-8 — Les gardes sont le débouché naturel
La forme la plus efficace de capitalisation est la **garde automatisée** (script
`dev-hub/tools/`, workflow CI, validateur de structure) : une leçon qui peut être
vérifiée par la machine doit le devenir (voir la bibliothèque des erreurs, OB-3).
Toute nouvelle garde est documentée (où elle s'exécute, ce qu'elle bloque) dans
`AGENTS.md` ou la doc de la surface.

## 4. Labels & flux GitHub (état cible)

| Label | Usage |
|---|---|
| `retex` | issue issue d'un RETEX (template) |
| `process` / `tech-debt` / `design` / `vitrine` / `desktop` / `infra` / `docs` | nature de l'action (labels existants + nouveaux) |
| `protocole` | mise à jour d'un protocole (00_SOMMAIRE) |
| `vitrine` / `design` / `desktop` | domaines des protocoles 03/05/06 |
| BC-XX | rattachement bounded context (toujours ajouté avant implémentation) |
| `good first issue` | RETEX jugé accessible pour un nouvel agent (atterrissage OB-2) |

## 5. Rituels & responsabilités

| Qui | Fait |
|---|---|
| Tout agent | produit ses RETEX (RET-4), peut implémenter le sien si RET-5 |
| Gardien de domaine (tests/CI, design, vitrine, infra, desktop) | transforme les RETEX de son domaine en gardes ou mises à jour de protocole |
| PM | tri (RET-6), verdicts, arbitrage des conflits, revue mensuelle |

## 6. Sources & alignement

- Règles anti-doublon / BC : `AGENTS.md`
- Conventions de tests : `docs/GESTION_PROJET/CONVENTIONS_TESTS.md` (exemple type :
  leçon `PendingCommand` → convention + audit)
- Registre des corrections : `docs/GESTION_PROJET/CORRECTIONS.md`
- Sessions QA (bibliothèque d'expériences) : `docs/qa/`
- Modèle de PR par lot BC : `docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md`

## 7. Écart constaté le 2026-09-09 (rattrapage)

Aucun flux RETEX n'existe : les leçons finissent en texte dans AGENTS.md (quand elles y
arrivent) ou se perdent. Actions : (1) ajouter le template `retex.yml` dans
`.github/ISSUE_TEMPLATE/` ; (2) créer les labels ci-dessus ; (3) relire les ~70 sessions
QA de `docs/qa/` lors des prochains tris pour convertir les leçons récurrentes en
issues (chantier de rattrapage à dimensionner avec le PM).
