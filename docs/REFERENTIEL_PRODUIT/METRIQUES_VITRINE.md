# METRIQUES_VITRINE — Registre des métriques datées

> Règle (protocole P03) : **aucun chiffre public sans une ligne datée dans ce
> registre** (README.md l'interdit déjà pour le marketing ; ce registre rend la
> règle exécutable). Toute métrique affichée sur une surface publique doit être
> régénérée à la date indiquée, jamais recopiée d'un mois sur l'autre.
> Établi le 2026-09-09.

## Registre (mesures du 2026-09-09)

| Métrique | Valeur mesurée | Date | Source / méthode de mesure |
|---|---|---|---|
| Modules métier (dossiers `api/app/Modules/`) | 27 | 2026-09-09 | `ls api/app/Modules \| wc -l` |
| Bounded contexts (registre) | BC-01…BC-28 | 2026-09-09 | `dev-hub/governance/bounded-context-registry.json` |
| Chemins API (OpenAPI) | 738 | 2026-09-09 | `grep -c "^  /" api/openapi.yaml` |
| Apps Flutter (packages Melos) | 7 apps + `leopardo_core` | 2026-09-09 | `front/mobile_apps/` (employee, hr, manager, platform_admin, marketing, accounting, travel_agent) |
| Fichiers de tests Flutter (`*_test.dart`) | 58 | 2026-09-09 | `find front/mobile_apps -name "*_test.dart" \| wc -l` |
| Tests backend (Pest/PHPUnit) | ~4 010 (17 165 assertions) | 2026-09-09 | `api/tests/Feature` + `api/tests/Unit` (session CI ~60 min) |
| Specs E2E Playwright | 59 | 2026-09-09 | `front/web/e2e` + `front/admin-dashboard/e2e` |
| Pays couverts (vitrine) | voir `front/web/src/modules/vitrine/data/supported-countries.ts` | 2026-09-09 | comptage du fichier `supported-countries.ts` |
| Langues (i18n) | fr, en, ar, tr | 2026-09-09 | `shared/i18n/` (dossiers de locales) |
| URLs live (vérifiées HTTP 200) | 9/9 | 2026-09-09 | curl santé/200 : API dev+prod, web dev+prod, admin dev+prod, verticales resto/travel-prod, gh-pages |

## Mesures affichées sur les surfaces et leur vérité au 2026-09-09

| Surface | Chiffre affiché | Constat | Action |
|---|---|---|---|
| `site/gh-pages/index.html` | « 18 modules, 700+ endpoints, 1 900+ tests, 5 apps Flutter, 21 pays » | ❌ non daté ; modules 27 (ou BC 28), apps 7, tests >4 000 backend | Régénérer depuis ce registre avec date de mesure |
| README « Project status » | métriques diverses | ⚠️ déjà soumis à la règle « date obligatoire » | Régénérer à chaque release |

## Rituel

- Régénération : au **rituel mensuel** (P03 §7) et avant toute mise à jour d'une
  surface publique (PR concernée → régénérer + dater la ligne).
- Toute surface affichant un chiffre absent de ce registre = bug vitrine (issue).
