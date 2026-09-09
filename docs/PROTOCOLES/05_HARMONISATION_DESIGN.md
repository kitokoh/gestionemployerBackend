# Protocole 05 — Harmonisation du design

> **Statut : v1.0 — révisé le 2026-09-09 — propriétaire : PM**
> Code des règles : `DSG-*`.
> Objectif : **toutes les surfaces (vitrine web, dashboard admin, apps Flutter, desktop,
> kiosk) parlent le même langage visuel** — mêmes tokens, mêmes composants, mêmes règles —
> sans que l'harmonisation dépende de la mémoire ou du goût du moment.

## 1. Sources de vérité design (hiérarchie)

| Rang | Source | Portée | Statut constaté (2026-09-09) |
|---|---|---|---|
| 1 | `docs/REFERENTIEL_PRODUIT/APV.md` | manifeste produit + lois d'architecture (dont design) | canonique |
| 2 | `docs/REFERENTIEL_PRODUIT/COULEURS.md` | **tokens partagés Flutter ↔ Tailwind** (couleurs) | canonique |
| 3 | `docs/vision/02_design_system/Leopardo_RH_Design_System_v3.pdf` | référence design complète | canonique, mais PDF = difficile à diffuser → à décliner en tokens/code |
| 4 | `front/mobile_apps/leopardo_core/` | tokens et widgets Flutter (`AppColors`, `AppTypography`, widgets de base) | canonique pour mobile/desktop |
| 5 | `front/admin-dashboard/` | tokens Tailwind admin (`glass-*`, `premium-text`, `shadow-glass-*`) | canonique pour admin |
| 6 | `front/web/` | tokens Tailwind vitrine | canonique pour vitrine |

Règle générale existante, rappelée : en cas de contradiction entre un PDF de
`docs/vision/` et `docs/REFERENTIEL_PRODUIT/`, **le référentiel prime** (règle
documentaire `docs/REFERENTIEL_PRODUIT/README.md`).

## 2. Règles

### DSG-1 — Pas de valeur de style en dur
Couleur, typographie, espacement, rayon de bordure, ombre, durée d'animation :
**toute valeur visuelle provient d'un token** (leopardo_core pour Flutter ;
Tailwind + variables partagées pour web/admin ; extension des tokens COULEURS.md pour
toute nouvelle couleur). Une valeur en dur constatée en revue = PR refusée (label
`design`).

### DSG-2 — Les trois familles de tokens restent synchronisées
`COULEURS.md` est le **pont** : toute nouvelle couleur/typo passe d'abord par ce fichier
(décision tracée), puis est propagée : leopardo_core (Flutter), Tailwind (vitrine),
Tailwind (admin), PDF v3 si impact design system. Une PR qui ajoute une couleur sans
mettre à jour COULEURS.md est refusée. À terme, générer les tokens des 3 familles depuis
COULEURS.md (chantier d'outillage recommandé — issue `design`).

### DSG-3 — Composants partagés plutôt que copier-coller
- Mobile/desktop : tout composant réutilisable va dans `leopardo_core` (règle déjà en
  vigueur dans `front/mobile_apps/README.md`) ; le chantier #2661 (13 fichiers
  byte-identiques dupliqués entre apps) est la dette de référence à résorber.
- Web/admin : composants UI partagés par surface, tokens communs.
- Un écran « copié-collé avec une variante » est un signal de dette : ouvrir une issue
  `design`/`tech-debt` (flux RETEX, protocole 04) au lieu de propager la copie.

### DSG-4 — Golden tests pour le rendu garanti
Tout écran mobile/desktop significatif (et toute évolution du design system) est couvert
par un **golden test** (`docs/testing/GOLDEN_TESTS.md`) intégré à `mobile-apps-ci.yml` :
le rendu ne peut pas régresser silencieusement. Même principe à étendre côté web/admin
là où un outil de snapshot visuel est disponible (Playwright).

### DSG-5 — Le design se revoit avant de coder (gros chantiers)
Pour toute refonte ou nouvel écran « premium » : produire une **maquette validée avant
implémentation** (flux du prompt `dev-hub/prompts/15_DESIGN_AUDIT_UI.md` : mockup Stitch
→ validation PM → implémentation → vérification de conformité à la maquette). Une
refonte non maquettée est refusée.

### DSG-6 — Checklist de revue design obligatoire (toute PR avec UI)
- [ ] Valeurs issues de tokens (DSG-1), aucune couleur hors palette
- [ ] Typographie du design system (tailles, graisses, casse) — pas de `fontSize` sauvage
- [ ] Espacements réguliers (grille) ; états : hover, focus, pressed, disabled, erreur
- [ ] Contraste AA minimum (texte) ; texte sur image vérifié
- [ ] Responsive / redimensionnement (web) ; densité & taille de cible tactile (mobile) ;
      scaling et multi-écrans (desktop)
- [ ] Mode sombre si la surface le supporte (cohérent avec le thème)
- [ ] i18n : pas de texte en dur, pas de rupture de mise en page en FR/EN/AR/TR
      (attention aux langues RTL)
- [ ] Icônes/assets du système (pas d'asset « venu d'ailleurs »)
- [ ] Écran couvert par golden test si significatif (DSG-4)
- [ ] Capture avant/après jointe à la PR pour les changements visuels

### DSG-7 — Gardiens design
Par surface (web vitrine, admin, mobile/desktop), un agent **gardien design** est
identifié : il connaît les tokens de sa surface, applique DSG-6 en revue, et tient la
liste des écarts (issues `design`). Le PM arbitre les choix structurants (nouvelle
palette, refonte) — jamais un gardien seul.

### DSG-8 — Audit design mensuel (fin de mois, avec la revue vitrine)
1. Parcours visuel des surfaces clés (vitrine, admin, 1-2 apps, un desktop si actif).
2. Vérification : tokens à jour (COULEURS.md ↔ code), golden tests verts, zéro valeur
   en dur détectée par échantillonnage, écrans copiés identifiés.
3. Sorties : issues `design` (preuves à l'appui) + mise à jour de l'état
   design (vert/orange/rouge) dans le rapport mensuel (modèle VIT-10).

## 3. Sources & liens

| Sujet | Où |
|---|---|
| Design system (référence) | `docs/vision/02_design_system/` (PDF v3 + README) |
| Manifeste produit | `docs/REFERENTIEL_PRODUIT/APV.md` |
| Tokens couleurs partagés | `docs/REFERENTIEL_PRODUIT/COULEURS.md` |
| Golden tests | `docs/testing/GOLDEN_TESTS.md` |
| Split & règles des apps | `front/mobile_apps/README.md`, garde `validate-mobile-apps-split.ps1` |
| Refonte premium (chantier passé) | `docs/design/REFONTE_PREMIUM_STATUT.md` |
| Prompt audit UI | `dev-hub/prompts/15_DESIGN_AUDIT_UI.md` |

## 4. Écarts constatés le 2026-09-09 (rattrapage)

1. `COULEURS.md` est le pont déclaré mais rien ne **vérifie** la synchronisation réelle
   des tokens (Flutter ↔ Tailwind admin ↔ Tailwind web) → ajouter un check de cohérence
   en CI (chantier d'outillage).
2. Le design system vit dans un **PDF** (v3) : difficile à faire respecter par des
   agents ; priorité = décliner le PDF en tokens/`leopardo_core` versionnés.
3. La checklist DSG-6 n'existe nulle part comme telle → l'ajouter comme modèle de revue
   (PR template) et l'appliquer sur les PR UI.
4. Pas de golden tests pour le web/admin (seulement Flutter) → à étendre.
