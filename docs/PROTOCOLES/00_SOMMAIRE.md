# 🧭 Corpus des protocoles — Leopardo (kitokoh/leopardo-hr)

> **Statut : v1.0 — créé le 2026-09-09 — propriétaire : PM (validation finale)**
> Cible d'intégration dans le dépôt : `docs/PROTOCOLES/` (à valider par le PM avant PR).
> Chaque protocole **consolide et rend opposables** les règles déjà éparpillées dans le
> dépôt (AGENTS.md, docs/GOUVERNANCE/, docs/validation/, docs/ops/, …) et **comble les
> trous** identifiés le 2026-09-09. Il ne crée pas de droit nouveau quand une source
> canonique existe déjà — il la désigne et l'encadre.

---

## Pourquoi ce corpus existe

Le projet Leopardo a accumulé une expérience riche (gardes CI, sessions QA, audits,
protocoles de branche, registre de bounded contexts) mais cette expérience est **éparse** :
un nouvel agent peut se perdre, une vitrine peut devenir inexacte sans que personne ne
s'en aperçoive, un design peut dériver, une tranche desktop peut être livrée sans
signature ni tests, et le volet dev/prod peut diverger silencieusement.

Ces 7 protocoles répondent aux 7 engagements permanents suivants :

| # | Protocole | Engagement garanti |
|---|-----------|--------------------|
| 01 | Validation des tests & mise sur le marché | On ne dit « prêt pour le marché » que sur preuves, via des gates explicites. |
| 02 | Onboarding d'un agent | Un agent qui intègre le projet devient opérationnel sans répéter les erreurs passées. |
| 03 | Vitrine & présentation | Le projet est présentable à tout moment, avec les bons termes, sur toutes ses surfaces. |
| 04 | Capitalisation RETEX → issues | Chaque expérience d'un agent devient une issue/tâche exploitable par lui ou par d'autres. |
| 05 | Harmonisation du design | Aucune surface ne dérive du design system sans décision tracée. |
| 06 | Distribution desktop (tranches verticales) | Un `.exe` / une app macOS ne sort que d'une tranche verticale activée, testée et signée. |
| 07 | Architecture à deux volets (dev/prod) | Les volets développement et production restent cohérents, vérifiés à tout moment. |

## Les documents

| Fichier | Protocole | Code règles |
|---|---|---|
| [`01_VALIDATION_TESTS_MARCHE.md`](01_VALIDATION_TESTS_MARCHE.md) | Tests & « prêt marché » | `VT-*` |
| [`02_ONBOARDING_AGENT.md`](02_ONBOARDING_AGENT.md) | Onboarding | `OB-*` |
| [`03_VITRINE_PRESENTATION.md`](03_VITRINE_PRESENTATION.md) | Vitrine & bons termes | `VIT-*` |
| [`04_CAPITALISATION_RETEX.md`](04_CAPITALISATION_RETEX.md) | RETEX → issues | `RET-*` |
| [`05_HARMONISATION_DESIGN.md`](05_HARMONISATION_DESIGN.md) | Design | `DSG-*` |
| [`06_DESKTOP_TRANCHES_VERTICALES.md`](06_DESKTOP_TRANCHES_VERTICALES.md) | Desktop (Windows/macOS) | `DSK-*` |
| [`07_ARCHITECTURE_DEV_PROD.md`](07_ARCHITECTURE_DEV_PROD.md) | Architecture 2 volets | `ENV-*` |

## Règles de vie du corpus (le protocole des protocoles)

1. **Un seul document fait foi par sujet.** En cas de contradiction entre un protocole et
   une autre doc, la source **la plus récemment auditée** prime, et le code sur `main`
   prime toujours pour décrire le comportement réel. Les conflits sont signalés par une
   issue labellisée `protocole`.
2. **Toute leçon opérationnelle met à jour un protocole** (via le flux RET-4/RET-5) ou
   `AGENTS.md`, jamais les deux pour le même contenu. Un protocole qui n'est plus suivi
   est **retiré ou réécrit**, pas laissé à l'abandon.
3. **En-tête obligatoire** de chaque protocole : statut, date de dernière révision,
   propriétaire. Toute PR modifiant un protocole met à jour cette date.
4. **Toute règle citée est vérifiable** : soit un script de garde (`dev-hub/tools/`,
   workflow CI), soit une checklist humaine avec preuve attendue (lien PR, capture,
   rapport). Une règle sans moyen de vérification n'est pas un protocole, c'est un vœu.
5. **Les règles sont codées** (ex. `VT-3`, `VIT-2`) pour être référencées sans ambiguïté
   dans les issues, PR et revues (« violation VT-3 »).

## Calendrier des rituels (cadence)

| Quand | Rituel | Protocole(s) |
|---|---|---|
| À chaque mission d'un agent | RETEX de fin de mission → issue | RET-4 |
| À chaque merge vers `main` | Mise à jour CHANGELOG.md + AGENTS.md si leçon | VT-12, OB-9 |
| À chaque release / mise en prod | Gate release + GO/NO-GO + smoke prod | VT-8 → VT-10 |
| À chaque activation de tranche desktop | Gate desktop | DSK-2 → DSK-8 |
| **Chaque fin de mois** | **Revue « vitrine » (VIT-10), revue design (DSG-8), revue architecture 2 volets (ENV-9), tri des RETEX en attente (RET-6), revue des protocoles eux-mêmes** | tous |

> La revue de fin de mois est **le rendez-vous où les protocoles se redéfinissent** :
> chaque fin de mois, le PM reçoit un rapport d'une page (modèle en §8 de
> `03_VITRINE_PRESENTATION.md`) et tranche les mises à jour proposées. Le corpus est
> ainsi vivant, pas un musée.

## Mise en œuvre initiale recommandée (décisions PM)

1. Valider ce corpus (ou le modifier) → PR `docs/protocoles-v1` dans `leopardo-hr`
   (sous `docs/PROTOCOLES/`).
2. Ajouter les labels GitHub manquants : `retex`, `vitrine`, `desktop`, `protocole`
   (les autres labels existent déjà : `process`, `tech-debt`, `design`, `infra`, `docs`…).
3. Ajouter le template d'issue `RETEX` (modèle fourni en annexe de
   `04_CAPITALISATION_RETEX.md`) dans `.github/ISSUE_TEMPLATE/retex.yml`.
4. Créer l'issue « rituel fin de mois » récurrente et les 7 premières issues de
   rattrapage (écarts signalés dans chaque protocole, section « Écarts constatés »).
5. Nommer les propriétaires : PM (décision), 1 agent « gardien » par domaine
   (tests / vitrine / design / desktop / infra) — cf. §Rôles de chaque protocole.
