# PROTOCOLE 05 — Harmonisation du design

> **Statut** : Actif v1.0 — 2026-09-09
> **Porteur** : fondateur/PM (revue design en PR, rituel mensuel) + devs (application des règles au fil de l'eau)
> **Revue** : mensuelle, fin de mois — cf. docs/PROTOCOLS/00_INDEX.md
>
> **Pourquoi** : l'écosystème compte aujourd'hui 7 apps Flutter (employee, manager, hr, platform_admin, marketing, accounting, travel_agent) adossées à un package partagé `leopardo_core`, deux surfaces web (vitrine `front/web`, admin `front/admin-dashboard`) et un futur desktop. Chaque surface a historiquement inventé ses propres valeurs visuelles : hex en dur dans les écrans de pointage, copies locales de fichiers « core » (accounting, travel_agent), tokens tailwind dupliqués entre admin et web. Sans sources canoniques désignées et gardes associées, la moindre nouveauté visuelle diverge et la cohérence perçue du produit se dégrade silencieusement.

## 1. Objectif & périmètre

Garantir qu'aucune surface ne diverge visuellement : une même valeur (couleur, typographie, composant, libellé) doit avoir une source unique, un chemin de modification balisé et une garde qui détecte la dérive.

**Périmètre** : toutes les surfaces de l'écosystème — apps Flutter de `front/mobile_apps/`, vitrine `front/web/`, admin `front/admin-dashboard/`, et, par anticipation, desktop (cf. `docs/PROTOCOLS/06_DISTRIBUTION_DESKTOP.md`).

**Hors périmètre** (flux dédiés existants, non modifiés) : kiosque ZKTeco (`front/zkteco-kiosk`, appareil tiers à thème propriétaire), maquettes et pipeline de refonte visuelle (docs/specifications/REFONTE_VISUELLE_GLOBALE.md). Ce protocole ne recrée ni ne remplace le design system de référence : il désigne les fichiers qui font autorité dans le code et les gardes qui empêchent la divergence.

## 2. Sources canoniques par type

Une source canonique est le SEUL fichier où une valeur se définit. Toute autre occurrence est une consommation, jamais une redéfinition.

| Type | Fichier source (canonique) | Surfaces concernées | Remarques / garde |
|---|---|---|---|
| Couleurs mobile | `front/mobile_apps/leopardo_core/lib/core/theme/app_colors.dart` (218 l.) | 7 apps Flutter via core | En-tête APV L.05/L.07 : toute modif → répercuter couche web + `docs/REFERENTIEL_PRODUIT/COULEURS.md`. Garde : `validate-mobile-color-tokens.ps1` (PA2-MOB-011). |
| Couleurs — pont cross-stack | `docs/REFERENTIEL_PRODUIT/COULEURS.md` | mobile ↔ web ↔ admin | Table Hex ↔ `AppColors.*` ↔ classes Tailwind. Règle L.07 : même commit. |
| Couleurs / ombres web | `front/admin-dashboard/tailwind.config.js` et `front/web/tailwind.config.ts` | admin-dashboard, vitrine | Échelles déclarées dans les deux configs (emerald, slate, brand, cyan…) et tokens d'ombre `glass-*`/`premium` ; admin : `darkMode: 'class'`. Couleurs de domaine consommées via la palette (ex. `emerald-500`) — cf. `COULEURS.md`. |
| Typographie | `front/mobile_apps/leopardo_core/lib/core/theme/app_typography.dart` (72 l.) | apps Flutter | Inter, body 16, title 20/24, poids 400/600 ; aligné web via Fonts.bunny/Google Fonts. Référence visuelle : PDF v3 (§2, ligne « Design de référence »). |
| Thème dark/light | `front/mobile_apps/leopardo_core/lib/core/theme/app_theme.dart` (196 l.) | apps Flutter | Dark = expérience principale des 4 apps historiques (PA2-MOB-012, décision `docs/PLAN_ACTION2/15_DECISION_THEME_MOBILE.md`) ; `lightTheme` maintenu pour previews/tests. |
| Composants partagés | `front/mobile_apps/leopardo_core/lib/core/widgets/` (14 widgets : glass_card, glass_tile, mobile_surface, pulse_button, leopardo_bottom_nav, empty_state, shimmer_loading, leopardo_badge…) | apps Flutter | Toute brique réutilisable vit ici, jamais dans une app. |
| Branding tenant | `front/mobile_apps/leopardo_core/lib/core/branding/` (tenant_theme.dart, tenant_branding.dart, tenant_brand_mark.dart, tenant_branding_repository.dart) | apps Flutter multi-tenant | Garde : `validate-mobile-tenant-branding.ps1`. |
| Textes (i18n) | `shared/i18n/locales/{fr,en,tr,ar}.json` (+ `shared/i18n/README.md`) | toutes surfaces | `fr` = source ; `en/tr/ar` = traductions synchronisées via `shared/i18n/sync/*.js`. Ne jamais éditer de fichiers générés hors locales. |
| Design de référence (visuel) | `docs/vision/02_design_system/Leopardo_RH_Design_System_v3.pdf` (+ README) ; maquettes `assets/design/mockups/` (REFONTE_VISUELLE_GLOBALE.md) | toutes | README du sous-dossier : pilotage par `docs/REFERENTIEL_PRODUIT/APV.md` + `COULEURS.md`. |
| Espacements / radius / densité | Aucune source centralisée à ce jour — valeurs dispersées dans `app_theme.dart`, widgets core, configs tailwind | toutes | Chantier ouvert : échelle à créer dans le core avant toute nouvelle surface (R2). Ne pas en inventer dans une app. |
| Icônes | Pas de catalogue unifié : Material Icons (Flutter, `uses-material-design`), `lucide-react` (`front/web`) | mobile, web | À trancher ; aucune nouvelle bibliothèque d'icônes sans décision. |
| Desktop (futur) | Aucune — héritera des sources ci-dessus | — | Tokens fenêtre/largeur/densité à définir plus tard (cf. `docs/PROTOCOLS/06_DISTRIBUTION_DESKTOP.md`) ; aucune valeur « desktop » à inventer maintenant. |

## 3. Règles d'or

Chaque règle : condition → action → garde.

**R1 — Aucun hex hardcodé dans les écrans.** *Condition* : un écran ou widget a besoin d'une couleur. *Action* : mobile → `AppColors.*` ; web/admin → classes Tailwind ou tokens des configs ; jamais `Color(0x…)` ni hex inline dans une vue. *Garde* : `dev-hub/tools/validate-mobile-color-tokens.ps1` — échec CI si un littéral `Color(0x…)` apparaît hors `app_colors.dart` (PA2-MOB-011).

**R2 — Toute nouveauté visuelle partagée va dans le core.** *Condition* : une valeur de style, un widget ou un comportement visuel est utile à plus d'un écran, ou à une autre app. *Action* : l'extraire dans `leopardo_core` (`core/theme/` ou `core/widgets/`), jamais dans l'app puis copié. *Garde* : règle « toute modification partagée va dans `leopardo_core` » (`front/mobile_apps/README.md`, `docs/mobile/CONVERGENCE_F27.md`) + revue PR (§4) ; séparation verrouillée par `validate-mobile-apps-split.ps1` (zéro import inter-apps).

**R3 — Nouvelle app → dépend de `leopardo_core`, zéro copie locale.** *Condition* : création ou refonte d'une app mobile. *Action* : dépendance `path: ../leopardo_core` dans le pubspec, réutilisation du theme, des widgets et du branding core ; interdiction de répliquer un fichier core dans l'app (contre-exemples : `app_strings.dart` local d'accounting/travel_agent) ou d'embarquer des assets de marque non alignés. *Garde* : checklist §4 + inventaire de l'audit mensuel §5.

**R4 — Pont web : règle du même commit, étendue (L.07+).** *Condition* : modification d'une couleur ou d'un token visuel partagé. *Action* : `docs/REFERENTIEL_PRODUIT/COULEURS.md` + `app_colors.dart` + `tailwind.config.js` (admin) + `tailwind.config.ts` (web) modifiés dans le MÊME commit — aucune valeur intermédiaire laissée en attente. *Garde* : revue PR §4 (case C7) ; le diff d'une PR UI ne doit contenir aucun hex nouveau hors de ces fichiers.

**R5 — i18n via `shared/i18n`, interdiction des `app_strings` locaux.** *Condition* : un texte visible utilisateur est ajouté ou modifié. *Action* : clé dans `shared/i18n/locales/fr.json`, traductions `en/tr/ar` synchronisées, consommation via le mécanisme i18n de la surface. *Garde* : revue PR (C5) + audit mensuel (I7) ; côté API, gardes existantes `check-*-i18n.py` (ex. `check-accounting-i18n.py`).

**R6 — Dark cohérent partout.** *Condition* : un écran mobile des 4 apps historiques est modifié, ou un composant web sombre est créé. *Action* : mobile → rester sur `ThemeMode.dark` (PA2-MOB-012) et valider le rendu sombre avec les tokens dédiés ; web/admin → variants `dark:` systématiques. *Garde* : capture dark exigée en PR (C2).

**R7 — Branding tenant par le core uniquement.** *Condition* : personnalisation visuelle d'un tenant (couleur, logo, marque). *Action* : passer par `core/branding/` (`TenantTheme.apply`, `TenantBrandMark`) ; jamais de couleur tenant en dur dans un écran. *Garde* : `validate-mobile-tenant-branding.ps1` + revue PR.

**R8 — Desktop : hériter, ne pas inventer.** *Condition* : un futur écran desktop a besoin d'une valeur visuelle. *Action* : consommer les sources canoniques du §2 ; les tokens spécifiques fenêtre/largeur/densité seront définis lors du chantier desktop. *Garde* : renvoi vers `docs/PROTOCOLS/06_DISTRIBUTION_DESKTOP.md` — aucune valeur « desktop » nouvelle dans le code avant cette décision.

## 4. Porte de revue design (checklist PR)

**Déclencheur** : toute PR cochant une surface UI du périmètre dans « Surfaces touchées » du gabarit `.github/PULL_REQUEST_TEMPLATE.md` (Mobile, Admin dashboard, Web ; le Kiosk reste sur son flux dédié, §1). Le template impose déjà des captures avant/après ; la porte ci-dessous l'étend aux critères visuels.

Checklist « revue visuelle » — à cocher par l'auteur, vérifiée par le relecteur :

- **C1 — Cohérence tokens** : aucune couleur, typographie, radius ou ombre nouvelle hors des sources du §2 ; pas de `Color(0x…)` hors `app_colors.dart` (R1).
- **C2 — Dark** : capture du rendu sombre jointe ; écran cohérent avec les tokens sombres (mobile historique : dark principal, R6).
- **C3 — États** : les états vide / chargement / erreur / hors-ligne de tout écran modifié sont traités (widgets core : `empty_state`, `shimmer_loading`…).
- **C4 — Accessibilité** : contraste WCAG AA — ratio ≥ 4.5 texte normal, ≥ 3 texte large (cf. `COULEURS.md`) ; la couleur n'est jamais le seul vecteur d'information ; cibles tactiles correctes.
- **C5 — i18n** : textes vérifiés en `fr` et `en`, aucune chaîne brute, aucun import de `app_strings` local (R5) ; impact layout RTL (`ar`) examiné si le composant touche la direction.
- **C6 — Captures avant/après** : jointes pour chaque écran modifié (obligation du template PR).
- **C7 — Impact cross-stack** : couleur ou token partagé modifié → `COULEURS.md` + `AppColors.dart` + configs Tailwind dans le même commit (R4).
- **C8 — Pas de copie** : toute brique visuelle réutilisée provient du core (R2, R3).

Règles de blocage : une PR UI sans captures, ou violant C1/C4/C5/C7, est refusée. Toute refonte visuelle d'ampleur exige en plus une maquette de référence (pipeline maquette → code de `docs/specifications/REFONTE_VISUELLE_GLOBALE.md`, mockups sous `assets/design/mockups/`).

## 5. Rituel mensuel d'audit visuel

**Quand** : dernière semaine du mois (aligné sur la revue des protocoles). **Qui** : fondateur/PM + un dev par surface (mobile, web, admin).

1. **Détection automatisée des dérives** — commandes à rejouer :
   - littéraux hex hors source : logique de `validate-mobile-color-tokens.ps1` sur `front/mobile_apps/` (grep `Color\(0x…` hors `app_colors.dart`) + recherche d'hex inline dans `front/web` et `front/admin-dashboard` hors configs tailwind ;
   - copies locales de fichiers core : `find front/mobile_apps -path '*/core/*' -name '*.dart' -not -path '*leopardo_core*'` (ex. `app_strings.dart`) ;
   - duplications de fichiers entre apps (référence : chantier #2661 des 13 repositories byte-identiques) ;
   - parité des couleurs : diff `COULEURS.md` ↔ `AppColors.dart` ↔ configs Tailwind (aucun hex divergent).
2. **Captures de référence** — rejouer les parcours clés de chaque surface (connexion, accueil, pointage/facture, navigation), screenshotter, verser dans `docs/design/audits/YYYY-MM/` et comparer aux mois précédents.
3. **Comparaison surface à surface** — nav, cartes, boutons, états vides : une même action doit avoir le même rendu partout. Objectif : détecter les divergences de type accounting/travel_agent avant qu'elles ne s'installent.
4. **Rapport** — synthèse courte dans `docs/design/` : conformité par surface, écarts constatés, issues créées. La revue mensuelle des protocoles (00_INDEX.md) arbitre les suites.

**Procédure de remise à niveau d'une app en dérive** : (1) ouvrir des issues « [REX] harmonisation design » listant chaque écart (copies locales, assets, tokens) ; (2) migrer les fichiers répliqués vers `leopardo_core` ou `shared/i18n` et faire consommer la source ; (3) aligner assets et branding sur les références §2 ; (4) re-capturer l'app et clore les issues avec le rapport d'audit suivant.

## 6. Indicateurs de santé

| Indicateur | Seuil sain | Mesure |
|---|---|---|
| I1 — Littéraux hex hors `app_colors.dart` / configs tailwind | 0 | garde CI `validate-mobile-color-tokens.ps1` + grep mensuel web/admin |
| I2 — Copies locales de fichiers core hors `leopardo_core` | 0 | audit §5 (aujourd'hui : 2 — accounting, travel_agent) |
| I3 — Apps mobiles dépendant de `leopardo_core` | 7/7 | pubspecs de `front/mobile_apps/` |
| I4 — Parité couleurs COULEURS.md ↔ AppColors ↔ Tailwind | 0 écart | audit §5, étape 1 |
| I5 — Rapport d'audit visuel du mois présent dans `docs/design/` | 1/mois | revue mensuelle |
| I6 — PR UI avec captures avant/après | 100 % | porte §4 (C6) |
| I7 — Fichiers de textes locaux (`app_strings` hors shared/i18n) | 0 | audit §5 (aujourd'hui : 2) |

## 7. État des lieux au 2026-09-09

Dérives constatées, à solder via les règles ci-dessus :

- **accounting & travel_agent** : dépendent bien de `leopardo_core` (pubspec `path: ../leopardo_core`) mais répliquent chacune un `core/i18n/app_strings.dart` LOCAL (296 et 580 lignes, importé par les écrans) au lieu de `shared/i18n` ; ni l'une ni l'autre n'a de dossier `assets/` ni de branding aligné sur les références. Violation R3/R5 — chantier de remise à niveau (§5).
- **docs/design/ quasi vide** : un seul fichier (`REFONTE_PREMIUM_STATUT.md`) alors que la refonte premium (issues #1625-1628) est en cours ; les comptes-rendus d'audit visuel et statuts de maquettes n'ont pas de domicile stable (§5 étape 4).
- **Contradiction documentaire** : `docs/REFERENTIEL_PRODUIT/COULEURS.md` indique « le dark mode n'est pas le défaut », contredit par PA2-MOB-012 (`app_theme.dart` : dark = expérience principale des 4 apps historiques) — à corriger dans COULEURS.md.
- **Duplication de même famille** : 13 fichiers repositories byte-identiques entre employee/manager/hr (chantier QA 2026-08-15, #2661, cf. `front/mobile_apps/README.md`) — copies à migrer vers le core.
- **Trous de sources** : pas d'échelle centralisée d'espacements/radius, pas de catalogue d'icônes unifié (R2 / §2) ; pas de garde CI dédiée aux `app_strings` locaux (seule la revue PR et l'audit mensuel les détectent aujourd'hui).
