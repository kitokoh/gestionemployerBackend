# Design — point d'entrée & sources canoniques

> Créé le 2026-09-09 (moisson — issue #7109). Ce dossier centralise le design ;
> les règles d'harmonisation vivent dans le corpus `docs/PROTOCOLES/P05_DESIGN_HARMONISE.md`.

## Sources canoniques par type

| Type | Source canonique | Surfaces |
|---|---|---|
| Couleurs (tokens) | `docs/REFERENTIEL_PRODUIT/COULEURS.md` (hex ↔ `AppColors.*` ↔ Tailwind) | Flutter, web, admin |
| Tokens Flutter | `front/mobile_apps/leopardo_core/lib/core/theme/` (`app_colors.dart`, `app_theme.dart`, `app_typography.dart`) | Apps mobiles + desktop |
| Composants partagés | `front/mobile_apps/leopardo_core/lib/core/widgets/` | Apps Flutter (zéro copie locale, convergence F-27) |
| Design system historique | `docs/vision/02_design_system/Leopardo_RH_Design_System_v3.pdf` — **référence visuelle**, pas source des tokens | — |
| Refonte visuelle | `docs/specifications/REFONTE_VISUELLE_GLOBALE.md` + `docs/design/REFONTE_PREMIUM_STATUT.md` (état) | — |

## Règles d'or (rappel P05)

1. **Aucun hex hardcodé** dans un écran/vue — toujours via les tokens.
2. **Une couleur = un domaine** (L.05) — pas de vert RH pour de la finance.
3. **Règle du même commit (L.07)** : modifier `COULEURS.md` + `AppColors.dart` + `tailwind.config.*` dans la même PR.
4. **Dark par surface** : mobile = dark par défaut (PA2-MOB-012) ; web/admin = light par défaut (voir COULEURS.md).
5. Toute PR touchant une UI passe la **checklist de revue design DSG-6** (`.github/PULL_REQUEST_TEMPLATE.md`) avec captures avant/après.

## Gardes & outils

- `dev-hub/tools/check-design-token-sync.py` — synchronisation COULEURS.md ↔ Flutter ↔ Tailwind (vert sur ce dépôt au 2026-09-09).
- `dev-hub/tools/validate-mobile-color-tokens.ps1` — garde mobile (hex hors palette).
- `dev-hub/tools/check-web-design-tokens.sh` — garde web/admin (voir issue #7062).

## Audits & rituel

- Audit visuel mensuel : protocole P05 §5 (dérives → issues `design`).
- Le dossier accueille les rapports d'audit design datés (convention `docs/design/AUDIT_YYYY-MM.md`).
