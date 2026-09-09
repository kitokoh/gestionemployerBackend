# DESIGN TOKENS — Inventaire & trajectoire (issue #7071, protocole P05)

> Créé le 2026-09-09. Cadrage v1 : inventaire des familles de tokens, de leur niveau de
> centralisation et de la trajectoire pour décliner le design system
> (`docs/vision/02_design_system/Leopardo_RH_Design_System_v3.pdf`) en **tokens
> versionnés et vérifiés par CI**. Les valeurs restantes sont extraites à la revue
> design mensuelle (protocole `docs/PROTOCOLES/P05_DESIGN_HARMONISE.md`) — ce fichier en est le tableau de bord.

## Règle maîtresse

APV **L.07** : `AppColors.dart` + `tailwind.config.{js,ts}` + `COULEURS.md` bougent
ensemble **dans la même PR**. Toute nouvelle valeur visuelle commence par
`docs/REFERENTIEL_PRODUIT/COULEURS.md` (décision tracée), puis est propagée aux
surfaces. Garde CI associée : `dev-hub/tools/check-design-token-sync.py`
(`design-token-sync.yml`).

## Inventaire par famille (état au 2026-09-09)

| Famille | Centralisation | Fichiers cibles | Statut | Vérifié CI |
|---|---|---|---|---|
| Couleurs | ✅ centralisé | `COULEURS.md` ↔ `leopardo_core/lib/core/theme/app_colors.dart` ↔ `front/web/tailwind.config.ts` + `front/admin-dashboard/tailwind.config.js` | ✅ ok (dérive détectée le 2026-09-09 : échelle `emerald` du config web ≠ COULEURS.md — suivi issue dédiée) | `check-design-token-sync.py` (nouveau) + `validate-mobile-color-tokens.ps1` (anti-hardcode mobile) |
| Typographie | ⚠️ partiel | `leopardo_core/lib/core/theme/app_typography.dart` (mobile) ; pas d'équivalent partagé documenté pour web/admin | famille mobile centralisée ; mapping Tailwind (`fontFamily`, tailles) à documenter | à vérifier (revue mensuelle) |
| Espacements / grille | ⚠️ partiel | conventions visuelles (design system PDF) ; pas de tokens partagés versionnés | à extraire | non |
| Radius / ombres / élévation | ⚠️ partiel | tokens `glass-*`, `shadow-glass-*` côté admin (Vue/Tailwind) ; Flutter : défauts theme | à unifier + documenter | non |
| Durées / animations | ❌ absent | — | à définir (mobile/web) | non |
| Icônes / assets | ⚠️ partiel | `leopardo_core` (icônes), assets par surface | inventaire à tenir | non |
| Mode sombre | ✅ partiel | tokens `*Dark` (couleurs) + `theme_mode_provider.dart` | couleurs ok ; composants à vérifier | partiel |

## Trajectoire (étapes recommandées, revue design mensuelle)

1. **Typographie** : créer une section « Typographie » dans COULEURS.md (renommable à
   terme en `TOKENS.md`) ou un fichier jumeau `TYPOGRAPHIE.md`, et propager dans
   `app_typography.dart` + configs Tailwind (web/admin).
2. **Espacements/radius/ombres/durées** : définir les tokens de référence (grille 8pt,
   radius, ombres) dans `leopardo_core` + variables Tailwind, documentés au même endroit
   que les couleurs.
3. **Étendre la garde CI** : ajouter les familles typo/espacements au script
   `check-design-token-sync.py` une fois les fichiers cibles stabilisés.
4. **Vérification finale** : échantillon visuel par surface à la revue mensuelle
   — toute divergence constatée devient une issue `design`.

## Liens

- Couleurs : `docs/REFERENTIEL_PRODUIT/COULEURS.md` · Design system PDF :
  `docs/vision/02_design_system/` · Protocole design : `docs/PROTOCOLES/P05_DESIGN_HARMONISE.md`
  · Spec tokens : `docs/specifications/DESIGN_SYSTEM_TOKENS.md`
