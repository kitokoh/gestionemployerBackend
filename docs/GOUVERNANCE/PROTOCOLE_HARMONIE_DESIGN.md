# Protocole P5 — Harmonie du design : une seule vérité, toutes surfaces alignées

| | |
|---|---|
| **Statut** | PROPOSÉ (2026-09-09) — à valider par le PM |
| **Dernière revue** | 2026-09-09 |
| **Owner** | Design owner (à désigner) ; lead technique par surface |
| **Périmètre** | Toutes les surfaces rendues : Flutter (`front/mobile_apps/*`), web vitrine (`front/web`), admin (`front/admin-dashboard`), web-offline, kiosk ZKTeco, site gh-pages, assets branding |
| **Dépend de** | `docs/vision/02_design_system/Leopardo_RH_Design_System_v3.pdf`, `docs/specifications/DESIGN_SYSTEM_TOKENS.md`, `docs/REFERENTIEL_PRODUIT/COULEURS.md`, `docs/design/REFONTE_PREMIUM_STATUT.md`, `docs/testing/` (méthodologies tests), `lighthouse.yml`, P1 (preuves), P2 (conventions) |
| **Produit** | `docs/design/DESIGN_AUDIT_YYYY_MM.md` chaque fin de mois |

---

## 1. Objet et constat

Le design est aujourd'hui fragmenté :
- **Flutter** : tokens dans `leopardo_core/lib/core/theme/` (`app_colors.dart`, `app_typography.dart`,
  `app_theme.dart`) — source de vérité mobile, avec commentaire exigeant la répercussion web ;
- **Web vitrine** : palette inline dans `front/web/tailwind.config.ts` (brand/emerald/cyan/slate/surface) ;
- **Admin (Vue)** : `front/admin-dashboard/tailwind.config.js` **duplique** la palette avec **dérive déjà
  constatée** (ex. `emerald-50` `#f0fdf4` côté web vs `#ecfdf5` côté admin ; `primary`/`zinc`/`gray` présents
  seulement côté admin) ;
- **web-offline**, **kiosk ZKTeco**, **gh-pages** : valeurs en dur ou hors système ;
- aucun test de régression visuelle (pas de diff d'images), lighthouse limité à la vitrine web, non bloquant.

L'objectif : **un utilisateur ne doit pas pouvoir deviner sur quelle surface il se trouve.**

## 2. Sources de vérité (hiérarchie)

| Rang | Source | Contenu | Statut |
|------|--------|---------|--------|
| 1 | `docs/vision/02_design_system/Leopardo_RH_Design_System_v3.pdf` | Référence visuelle & principes (glassmorphism, Inter, grille 8 px) | Canonique |
| 1 | `docs/specifications/DESIGN_SYSTEM_TOKENS.md` | **Tokens machine** (couleurs, typo, rayons, ombres, espacements) — le fichier que les surfaces importent | Canonique — à consolider |
| 2 | `docs/REFERENTIEL_PRODUIT/COULEURS.md` + `STATUTS.md` | Sémantique métier des couleurs/statuts | Canonique |
| 3 | Implémentations par surface (table §3) | Tokens compilés | Doivent dériver de 1-2 |

Règle : **tout changement part de la source 1-2 et descend** ; jamais l'inverse (une couleur qui naît
dans un `tailwind.config.js` sans remonter est un écart).

## 3. Matrice d'alignement des surfaces (état initial constaté le 2026-09-09)

| Surface | Fichier tokens | État | Écarts connus |
|---------|----------------|------|---------------|
| Mobile Flutter (core → 7 apps) | `leopardo_core/lib/core/theme/app_colors.dart`, `app_typography.dart`, `app_theme.dart` | 🟢 | — (source de référence mobile) |
| Web vitrine | `front/web/tailwind.config.ts` | 🟡 | Inline, non importé d'une source commune |
| Admin dashboard | `front/admin-dashboard/tailwind.config.js` | 🔴 | Duplication + dérive (`emerald-50`, tokens admin-only) |
| Web-offline | Tailwind v4 sans tokens | 🔴 | Hors système (pile système par défaut, pas de palette) |
| Kiosk ZKTeco | CSS en dur (fond `#091425`, accents émeraude/cyan) | 🔴 | Hors système (refonte documentée mais non raccordée) |
| gh-pages (legacy) | `site/gh-pages/style.css` | 🟡 | Doublon de la vitrine web — à aligner ou retirer |
| Branding | `assets/branding/` | 🟢 | — |

**Cible mensuelle** : chaque surface passe 🟡 → 🟢 ; aucune nouvelle surface ne démarre hors système.

## 4. Règles de contribution design (à inscrire dans `CONTRIBUTING.md` / `CONVENTIONS.md`)

1. **Pas de valeur de design en dur** : couleur, rayon, ombre, espacement, typo → token ou classe du
   design system. Exception documentée dans la PR.
2. **Changement de token = PR unique multi-surfaces** : le même commit (ou la même PR) modifie
   `DESIGN_SYSTEM_TOKENS.md`/`COULEURS.md` + Flutter + tailwind web + tailwind admin + surfaces
   concernées (déjà exigé ad hoc par l'en-tête de `app_colors.dart` — le protocole le généralise).
3. **Changement visuel = captures avant/après** dans la PR (template PR existant).
4. **Composant utilisé par ≥ 2 surfaces** → mutualisé : Flutter dans `leopardo_core/lib/core/widgets/`
   (glass_card, leopardo_badge, empty_state…), web dans `front/web/src/components/ui/` — sinon rester local.
5. **Dark mode / branding tenant** : suivre les mécanismes existants (admin : `src/stores/theme.js` ;
   Flutter : `lib/core/branding/tenant_theme.dart`) — pas de nouveau mécanisme parallèle.
6. Les nouvelles surfaces (ex. clients desktop, P6) **héritent** des tokens Flutter du core ; un client
   desktop ne réinvente pas son thème.

## 5. Tests de non-régression visuelle (plan en 3 temps)

- **T1 (court terme)** : activer le diff d'images Playwright (`toHaveScreenshot`) sur les parcours clés
  web vitrine + admin (les specs `e2e/*.spec.ts` n'attachent aujourd'hui des captures qu'en échec) ;
  étendre `lighthouse.yml` (aujourd'hui vitrine web seule, non bloquant) à admin-dashboard.
- **T2 (moyen terme)** : golden tests d'images Flutter (widget) sur les widgets partagés du core
  (référence par plateforme). ⚠️ Le `docs/testing/GOLDEN_TESTS.md` existant couvre les golden tests
  **backend** (invariants Payroll/Accounting) — la méthodologie **images Flutter** est à documenter dans
  `docs/testing/` (section à créer) avant implémentation ; intégration dans `mobile-apps-ci.yml`.
- **T3 (moyen terme)** : garde CI de **synchronisation des tokens** (script `dev-hub/tools/` comparant
  `app_colors.dart`, `tailwind.config.ts`, `tailwind.config.js` et `DESIGN_SYSTEM_TOKENS.md` ; échec si dérive).
  C'est la version exécutable de la règle L.07 aujourd'hui non automatisée.
- Chaque écart visuel constaté en recette (P1) ou en audit → issue `capitalisation` + label `design` (P4).

## 6. Audit design mensuel (dernier jour ouvré, fenêtre P3/P4/P7)

`docs/design/DESIGN_AUDIT_YYYY_MM.md` :
- [ ] Mettre à jour la matrice §3 (statuts par surface, écarts ouverts/fermés).
- [ ] Comparer les tokens réels des surfaces à la source canonique (manuel tant que T3 n'existe pas).
- [ ] Vérifier l'avancement des piliers de la refonte premium (`docs/design/REFONTE_PREMIUM_STATUT.md`, piliers A-D).
- [ ] Contrôler que les captures de la vitrine (P3) respectent le design system.
- [ ] Lister les composants dupliqués détectés → issues de mutualisation.
- [ ] Décision : le design est-il « harmonisé » ce mois-ci ? Oui/Non + écarts prioritaires.

## 7. Rôles

| Rôle | Responsabilité |
|------|----------------|
| Design owner (à désigner) | Sources 1-2, arbitrage des écarts, audit mensuel, approbation des PR design |
| Lead par surface | Alignement de sa surface, captures avant/après |
| Agents | Appliquent les règles §4 ; signalent tout écart dans une issue `design` |

## 8. Indicateurs

- Nombre de surfaces alignées / total (cible : 7/7 à fin de consolidation).
- Écarts de tokens ouverts (cible décroissante → 0) ; composants dupliqués (cible : 0).
- Couverture de régression visuelle (parcours screenshotés / parcours clés).
