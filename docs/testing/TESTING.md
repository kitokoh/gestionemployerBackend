# Testing Strategy & Guidelines

Leopardo RH maintains high reliability through a comprehensive multi-layered testing strategy.

## 🧪 Testing Pyramid

| Layer | Tool | Coverage Goal | Responsibility |
|-------|------|---------------|----------------|
| **Unit** | Pest PHP | 80%+ | Logic & Calculations |
| **Feature** | Pest PHP | 100% Endpoints | API Contracts & RBAC |
| **E2E** | Playwright | Critical Flows | Browser Integration |
| **Mobile (7 apps Flutter)** | Flutter Test (`melos run test`) | Core Screens | `front/mobile_apps/*` |
| **Kiosk ZKTeco** | Tests JS/i18n | Pointage local | `front/zkteco-kiosk` (web offline-first, PAS Flutter) |
| **Edge sync** | Tests unitaires | Sync offline | `edge/` |

## 🚀 Running Tests

### 1. Backend (API)
La suite backend mixe PHPUnit et Pest (PHPUnit majoritaire, cf. `api/phpunit.xml`).

```bash
cd api
# Run with SQLite in-memory for speed
DB_CONNECTION=sqlite DB_DATABASE=:memory: ./vendor/bin/pest
```

### 2. Frontend (Web)
```bash
cd front/web
npm run lint
npm run test  # if applicable
```

### 3. Mobile (apps Flutter — `front/mobile_apps/`)
Depuis la racine du monorepo (workspace melos, cf. `melos.yaml`) :

```bash
melos run test        # flutter test sur toutes les apps
melos run analyze     # flutter analyze
# ou par app :
cd front/mobile_apps/leopardo_employee && flutter test
```

Le package partagé `leopardo_core` est testé avec les mêmes commandes melos.
Le kiosk (`front/zkteco-kiosk`) n'est PAS une app Flutter : tests node (`node --check`) + i18n.`

## 🛡️ Critical Scenarios

All contributions must pass the following critical scenarios:
- **Tenant Isolation:** Ensure Company A cannot access Company B's data.
- **RBAC Enforcement:** Ensure Employees cannot access Manager-only endpoints.
- **Data Integrity:** Verify payroll and attendance calculations.

Detailed test registry: [REGISTRE_SCENARIOS_TESTS.md](../GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md).

## 📊 Continuous Integration

Our GitHub Actions workflows (`.github/workflows/tests.yml`) execute the full suite on every Pull Request.
- **Gate 1:** Linting & Static Analysis (PHPStan, ESLint).
- **Gate 2:** Functional Tests.
- **Gate 3:** Build Verification.

---

For local Docker-based testing, refer to [RUNBOOK_LOCAL_TESTS.md](../GESTION_PROJET/RUNBOOK_LOCAL_TESTS.md).
