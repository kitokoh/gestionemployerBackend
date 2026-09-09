# Testing Strategy & Guidelines — Leopardo RH

> Document vivant. Référence de cadrage : `docs/PROTOCOLES/01_VALIDATION_TESTS_MARCHE.md`
> (règles VT-*). Mis à jour le 2026-09-09 : chemins alignés sur le monorepo actuel
> (les anciens chemins `front/mobile/`, `cd mobile` ont été retirés du dépôt).

## 🧪 Pyramide de tests

| Layer | Surface | Outil | Objectif | Référence |
|-------|---------|-------|----------|-----------|
| Unit | API backend | Pest PHP | 80 %+ (logique & calculs) | `api/`, `docs/GESTION_PROJET/CONVENTIONS_TESTS.md` |
| Feature | API backend | Pest PHP | 100 % des endpoints (contrat + RBAC) | `api/tests/` |
| E2E | Web vitrine + SaaS (`front/web`) | Playwright | Parcours critiques navigateur | `.github/workflows/e2e-*.yml` |
| E2E | Admin dashboard (`front/admin-dashboard`) | Playwright | Parcours critiques super-admin | `docs/admin/` |
| Widget/unit | Mobile & desktop (`front/mobile_apps/*`) | `flutter test` | Écrans clés | `docs/testing/GOLDEN_TESTS.md` |
| Golden | Mobile & desktop | `flutter test` (goldens) | Rendu visuel garanti (design system) | `docs/testing/GOLDEN_TESTS.md`, protocole 05 |
| Integration | Mobile & desktop | `integration_test` | Parcours critiques device/desktop | protocole 06 (desktop) |
| Contract | API ↔ consommateurs | OpenAPI + CI | `openapi.yaml` source canonique, miroir vérifié | `openapi-ci.yml` |

## 🚀 Exécuter les tests

### 1. Backend (API — Laravel/Pest)

```bash
cd api
# SQLite en mémoire pour la vitesse
DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/pest
# Postgres (CI) — via Makefile : voir api/README.md et RUNBOOK_LOCAL_TESTS.md
```

### 2. Web vitrine (Next.js)

```bash
cd front/web
npm install
npm run lint
npm run test   # si applicable (Playwright : voir e2e-*.yml)
```

### 3. Admin dashboard (Vue 3)

```bash
cd front/admin-dashboard
npm install
npm run lint
npm run test
```

### 4. Mobile & desktop (Flutter — monorepo melos)

```bash
# Depuis la racine du dépôt : tout le workspace melos
melos bootstrap
melos run analyze
melos run test          # unit + widget, tous les packages
melos run test:coverage # avec couverture
melos run gen           # build_runner, si nécessaire
melos run l10n          # regénère les ARB/localizations si clés modifiées
```

Une app seule (ex. `leopardo_employee`) :

```bash
cd front/mobile_apps/leopardo_employee
flutter pub get
flutter analyze
flutter test
flutter gen-l10n   # après modification des clés i18n partagées
```

> Détail des apps : `front/mobile_apps/README.md`. Détail outillage melos :
> `docs/MONOREPO_TOOLING.md`.

## 🛡️ Scénarios critiques (bloquants)

Toute contribution doit passer les scénarios critiques :

- **Isolation tenant** : la société A ne peut pas accéder aux données de la société B ;
- **RBAC** : un `employee` ne peut pas appeler d'endpoint manager ; un manager pas
  d'endpoint super-admin ;
- **Intégrité des données** : calculs de paie et de pointage vérifiés (règles
  versionnées, traçables) ;
- **Multi-tenant migrations** : toute migration respecte le pattern
  `resolveTableSchema()` (`docs/GESTION_PROJET/CONVENTIONS_TESTS.md`, AGENTS.md).

Registre détaillé : `docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md`.
Suite de régression : `docs/testing/REGRESSION_SUITE.md`.

## 📊 Intégration continue

La CI complète s'exécute sur chaque PR vers `main` (`.github/workflows/`). Les gates
qui priment (détaillées dans `BRANCH_PROTECTION_REQUIRED.md`) :

- **Gate 1 — Lint & statique** : PHPStan (Strict level 8 sur le delta + Modules
  Architecture), ESLint + TypeScript (front), actionlint, Module Structure Validator ;
- **Gate 2 — Tests fonctionnels** : suite backend (couverture ≥ seuil),
  mobile apps CI (guard structurel inconditionnel, analyse lourde si
  `front/mobile_apps/**` modifié), web/admin, edge/kiosk selon chemins ;
- **Gate 3 — Build & contrats** : build APK debug, OpenAPI cohérent, migrations sans
  collision, secrets scan.

> PR `docs:`/`chore:` : les jobs lourds sont généralement inutiles mais les checks
> **requis** (Backend Coverage, PHPStan Strict, Module Structure Validator,
> Frontend ESLint+TS, actionlint) tournent quand même par conception (voir
> commentaires dans `coverage-gate.yml` / `architecture-check.yml`) — c'est
> volontaire : un check requis gaté par `paths:` ne posterait jamais de statut et
> bloquerait le merge.

## 🏠 Local (Docker)

Pour l'environnement local Docker complet : `docker-compose.yml` + 
`docs/GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md`.

## Conventions d'écriture des tests

`docs/GESTION_PROJET/CONVENTIONS_TESTS.md` est **opposable** (pièges connus :
`PendingCommand` lazy, résolution de schéma, migrations idempotentes). Toute PR de
test qui les viole est refusée en revue (règle VT-1 du protocole 01).
