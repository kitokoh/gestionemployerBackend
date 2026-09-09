# Protocole 01 — Validation des tests & « prêt à mettre sur le marché »

> **Statut : v1.0 — révisé le 2026-09-09 — propriétaire : PM**
> Code des règles : `VT-*`. Complète et rend opposable `docs/validation/RELEASE_READINESS_GATE.md`,
> `docs/testing/*`, `docs/GESTION_PROJET/CONVENTIONS_TESTS.md` et le gate `GO_NO_GO_MVP.md`.

## 1. Objet

Garantir qu'**aucune décision « c'est prêt » n'est prononcée sans preuve**, et distinguer
deux décisions que le projet confondait jusqu'ici :

- **« Livrable »** : un lot peut être mergé et déployé (qualité technique verte).
- **« Prêt pour le marché »** : le produit peut être **vendu / mis en service chez un
  client réel** (qualité produit, parcours client, exploitation, conformité).

Une release peut être « livrable » sans être « prête pour le marché ». La décision
« prêt marché » appartient au PM et n'est prise que sur le dossier de preuves VT-10.

## 2. Périmètre & surfaces contrôlées

| Surface | Stack | Contrôles | Sources canoniques |
|---|---|---|---|
| API backend | Laravel/PHP 8.4, PostgreSQL | Pest unit + feature, PHPStan (max / delta), migrations, OpenAPI | `docs/testing/TESTING.md`, `CONVENTIONS_TESTS.md`, `api/ARCHITECTURE.md` |
| Web vitrine & SaaS | Next.js/TS | lint, tests, E2E Playwright, Lighthouse | `docs/web/`, workflows `web-ci.yml`, `e2e-*.yml` |
| Admin dashboard | Vue 3 | lint, tests, E2E Playwright | `docs/admin/`, `admin-pages-deploy-guard.yml` |
| Mobile & desktop | Flutter (melos) | `dart analyze`, `flutter test`, golden tests | `docs/testing/GOLDEN_TESTS.md`, `mobile-apps-ci.yml` |
| Kiosk / edge | HTML/JS, bridge | CI dédiée | `kiosk-ci.yml`, `edge-ci.yml` |
| Sécurité | transversal | secret scan, CodeQL, ZAP, dépendances | `SECURITY.md`, workflows `secret-scan.yml`, `codeql.yml`, `owasp-zap.yml` |

## 3. Règles

### VT-1 — Pyramide de tests par défaut (objectifs)
- Backend : unit + feature Pest ; **100 % des endpoints exposés couverts** par un test
  feature (contrat + RBAC), couverture globale ≥ 80 % (seuil actuel CI : 71 % et
  croissant — `coverage-gate.yml`). Toute baisse de couverture bloque.
- Web/Admin : scénarios critiques Playwright ; Lighthouse ≥ seuil configuré pour la vitrine.
- Flutter : tests unit/widget sur les écrans clés + **golden tests** pour tout écran
  visé par le design system (cf. protocole 05) ; `integration_test` pour les parcours
  critiques (et desktop, cf. protocole 06).
- Les conventions d'écriture des tests backend de `CONVENTIONS_TESTS.md` (pièges
  `PendingCommand` lazy, résolution de schéma, migrations idempotentes) sont **opposables** :
  toute PR de test qui les viole est refusée en revue.

### VT-2 — Definition of Ready (DoR) — une tâche ne se commence que si
1. Issue avec label BC (`BC-XX`, registre `dev-hub/governance/bounded-context-registry.json`),
2. Critère d'acceptation explicite (spec `.specify/` ou description d'issue),
3. Pas de doublon (branche/PR existante — règle anti-doublon AGENTS.md),
4. Pas dans le périmètre gelé sans dérogation (`docs/GOUVERNANCE/FREEZE_SCOPE_60J.md`).

### VT-3 — Definition of Done (DoD) — une PR n'est mergeable que si
1. Tests verts : jobs requis du `merge-health-ci.yml` + jobs du lot (backend, web, admin,
   mobile, edge selon surfaces touchées),
2. Analyse statique sans **nouvelle** erreur (baselines PHPStan gérées),
3. `Closes #N` dans le **body** de la PR (garde post-merge, issue #2512),
4. Migration sans collision de préfixe (garde `check-migration-basename-collisions.sh`),
5. CHANGELOG.md mis à jour si changement de comportement,
6. Aucune issue close « fantôme » (garde `check-issues-closed-without-merge.sh`),
7. Pour toute UI : revue design minimale (checklist DSG-6) et i18n (clés ajoutées,
   jamais de texte en dur).

### VT-4 — Une issue n'est fermée que par la preuve
Fermeture automatique par merge (`Closes #N` sur `main`) **ou** commentaire motivé
(`wontfix` / `superseded` / renvoi vers ticket canonique). Jamais de fermeture « pour
faire propre » (vague #4690/#4687/#4688/#4305/#4410 du 2026-08-17 — voir AGENTS.md).

### VT-5 — Sévérité des défauts
| Sévérité | Définition | Effet |
|---|---|---|
| P0 | Perte de données, faille sécurité, tenant cassé, prod indisponible | **Stop immédiat**, hotfix prioritaire |
| P1 | Parcours client bloqué, régression majeure, contrat API cassé | Bloque release / GO marché |
| P2 | Défaut contournable, impact partiel | Doit être documenté avec mitigation |
| P3 | Cosmétique, dette, amélioration | Peut passer en backlog (label `tech-debt`) |

### VT-6 — Branches & lots
Suivre le protocole de branche en vigueur : `fix/<issue>-<slug>` par issue isolée, ou
`bc/<code-bc>-<slug>` par lot de BC (voir `docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md`,
`CRM_BRANCH_PROTOCOL.md`). **Jamais deux branches pour la même issue.**

### VT-7 — Registre des scénarios
Tout scénario critique (isolation tenant, RBAC, calculs paie, pointage) est consigné
dans `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md` et rejoué par la suite de
régression (`docs/testing/REGRESSION_SUITE.md`) avant chaque gate de release.

### VT-8 — Gate release (« livrable »)
Déclenché à chaque release candidate (tag semver, workflow `release.yml`) :
1. CI verte sur `main` (tous les jobs du lot),
2. Aucun P0/P1 ouvert sur le périmètre de la release,
3. `RELEASE_READINESS_GATE.md` rejoué surface par surface,
4. Migration validée en staging **puis** en prod (backup avant),
5. Smoke post-déploiement vert (`e2e-staging.yml`, smoke API + surfaces).

Résultat : **Go / Go conditionnel / No-Go** (table de décision du
`RELEASE_READINESS_GATE.md`).

### VT-9 — Gate « prêt pour le marché » (décision PM)
La décision « on peut mettre sur le marché » exige le **dossier de preuves** ci-dessous,
archivé dans `docs/validation/` (rapport daté, figé — règle documentaire de
`docs/validation/README.md`).

### VT-10 — Dossier « prêt marché » (checklist du PM)
- [ ] VT-8 vert (dernière release livrée et en prod)
- [ ] Parcours client complet prouvé sur prod (login, parcours cœur du module vendu,
      données pilotes réelles) — captures + compte-rendu
- [ ] Recette UAT du module signée par un pilote métier (`docs/ops/RECETTE_UAT_*.md`)
- [ ] Aucun P0/P1 ouvert, P2 documentés avec mitigation acceptée
- [ ] Sécurité : secret scan, dépendances, RGPD (`docs/RGPD_REGISTRE_TRAITEMENTS.md`),
      registre des traitements à jour
- [ ] Exploitation : runbooks à jour (`docs/GESTION_PROJET/RUNBOOK_*.md`), monitoring et
      alertes actifs (Sentry, uptime), sauvegardes vérifiées
- [ ] Facturation/billing testé (trial → actif → paiement) si module commercial
- [ ] Support : canal défini, accès pilotes créés, conditions connues
- [ ] Vitrine & docs publiques alignées (protocole 03 — checklist VIT-9)
- [ ] i18n : langues contractualisées complètes sur les parcours vendus
- [ ] Décision tracée : issue/PR « GO marché » avec rapport daté dans `docs/validation/`

**Formalisation** : le PM ouvre une issue « GO/NO-GO marché — <module> — <date> », y
colle la checklist remplie, et la clôture par un commentaire GO ou NO-GO motivé
(référence `GO_NO_GO_MVP.md` et `PILOT_RELEASE_GO_NOGO_CHECKLIST.md`).

### VT-11 — Données de test & isolation
- Les tests et recettes utilisent des fixtures/seeds (`docs/ops/SEEDS_PILOTES.md`),
  jamais de données client réelles hors environnement client pilote explicitement autorisé.
- Toute manipulation en prod est tracée dans `docs/ops/INCIDENTS.md` ou une issue.

### VT-12 — Preuve & traçabilité
- Chaque gate produit un **rapport daté et figé** (jamais réécrit après coup) ;
- Les rapports vivent dans `docs/validation/` (vivants sans date : `RELEASE_READINESS_GATE.md`,
  `FRONTEND_API_CONTRACT_MATRIX.md`, …) ; les rapports datés sont des preuves historiques.

## 4. Rituels & cadence

| Rituel | Déclencheur | Responsable | Sortie |
|---|---|---|---|
| Exécution CI | chaque PR | agent | jobs verts |
| Gate release | tag / RC | agent + PM | décision Go/No-Go (issue) |
| Dossier prêt marché | avant commercialisation d'un module | PM | rapport daté `docs/validation/` |
| Revue mensuelle des tests | fin de mois | PM + gardien tests | issues d'écart, mise à jour VT |

## 5. Écarts constatés le 2026-09-09 (issues de rattrapage à créer)

1. `docs/testing/TESTING.md` référence l'ancien dossier `front/mobile/` (retiré) et le
   vieux chemin `cd mobile` — à corriger vers `front/mobile_apps/` + melos.
2. Aucune checklist « prêt marché » formalisée n'existe en un seul endroit — ce
   protocole la crée ; le rattrapage consiste à archiver le prochain dossier réel.
3. Les rapports datés foisonnent (`docs/qa/`, `docs/validation/`) sans index de
   « dernières preuves » : tenir à jour `docs/validation/README.md` comme seul index.
