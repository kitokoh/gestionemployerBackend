# Protocole 06 — Distribution & tests desktop (Windows/macOS) par tranches verticales

> **Statut : v1.0 — révisé le 2026-09-09 — propriétaire : PM**
> Code des règles : `DSK-*`.
> Objet : extraire d'un module/BC justifié un **client desktop** (`.exe` Windows, app
> macOS) directement depuis les apps Flutter existantes, **sans créer une 6e surface
> générique** : chaque desktop est une **tranche verticale** activée, testée, signée et
> distribuée selon le présent protocole.

## 1. Constat (vérifié le 2026-09-09)

- Les apps `leopardo_employee`, `leopardo_manager`, `leopardo_hr`, `leopardo_marketing`
  et `leopardo_platform_admin` ont **déjà** le scaffolding desktop
  (`windows/`, `macos/`, `linux/`). `leopardo_accounting` et `leopardo_travel_agent` :
  Android seulement (accounting : « Android uniquement », cf. `front/mobile_apps/README.md`).
- **Aucune** chaîne de build/distribution desktop n'existe : `melos.yaml` n'a pas de
  script `build:windows`/`build:macos`, aucun workflow CI desktop, aucune signature,
  aucun canal de distribution. La distribution mobile actuelle = APK Android → Firebase
  App Distribution (`mobile-distribute.yml`).
- Le produit réserve le desktop aux « workflows desktop justifiés (comptabilité
  intensive, kiosk) » (README.md, product map — Desktop : « Future targeted clients /
  Planned »).

## 2. Qu'est-ce qu'une « tranche verticale » desktop (DSK-1)

Une tranche verticale desktop = **{ 1 BC métier + 1 app Flutter persona + un sous-ensemble
de workflows à usage intensif + les API contractuelles correspondantes }**, livrée
comme client Windows et/ou macOS.

Elle est justifiée si (au moins un) :
- usage **intensif clavier/écran** (saisie comptable, caisse, gestion multi-fenêtres) ;
- besoin **offline/hybride** ou périphérique local (imprimante, scan, badgeuse) ;
- environnement de travail **fixe** (guichet, bureau, site) où le mobile est inadapté.

Contre-exemples (pas desktop) : parcours mobiles (pointage GPS, approbations en
déplacement) → restent sur mobile ; simple « copie du site web » → ne pas lancer.

### Registre des tranches desktop (à valider par le PM)

| Tranche (nom) | BC | App Flutter | Cas d'usage justifié | Plateformes | État |
|---|---|---|---|---|---|
| Ex. « Comptabilité bureau » | BC Accounting | `leopardo_accounting` | saisie/facturation intensive, impayés | Windows (+macOS si besoin) | à activer (scaffolding à créer) |
| Ex. « Kiosk restaurant / pointage » | BC Attendance/Restaurant | `leopardo_employee` (mode kiosk) | poste fixe, badgeuse, offline | Windows | scaffolding existant |
| … (à compléter par le PM selon roadmap) | | | | | |

> Règle : **pas de tranche sans ligne dans ce registre** (issue de décision PM
> obligatoire, cf. DSK-2). Le registre vit dans `docs/PROTOCOLES/06_DESKTOP_TRANCHES_VERTICALES.md`
> ou, mieux, dans `docs/mobile/` à la création du premier desktop.

## 3. Règles d'activation d'une tranche

### DSK-2 — Décision d'activation
1. Issue de décision (labels `process` + BC-XX + `desktop`) décrivant : BC, app,
   workflows, plateformes cibles, périmètre desktop vs mobile, sponsor métier.
2. Verdict PM (le desktop **ne s'active pas** par défaut).
3. Ajout au registre des tranches + issue de spécification (flux Spec Kit `.specify/`).

### DSK-3 — Architecture desktop (règles de code)
- Desktop = la **même app Flutter** que mobile (pas de fork) : mêmes parcours, mêmes
  contrats API, adaptation responsive aux fenêtres (largeur mini, densité) et aux
  différences de plateforme derrière des abstractions (pas de `if (Platform.isWindows)`
  dispersé).
- Ce qui n'existe pas sur desktop (GPS, push, biométrie mobile…) est **neutralisé
  explicitement** (garde fonctionnelle), jamais laissé en crash silencieux.
- Toute évolution partagée va dans `leopardo_core` (protocole 05, DSG-3).
- Versioning : la version desktop suit le semver du CHANGELOG (VT-8) ; identité
  (nom visible, icône) distincte par app, alignée store mobile (même famille).

### DSK-4 — CI : builds desktop par tranche activée
- Ajouter les scripts melos (à créer, chantier outillage) :
  `melos run build:windows -- -t <app>` / `build:macos` (`flutter build windows` /
  `flutter build macos --release`) avec filtre de packages (ignorer `leopardo_core`).
- Nouveau workflow GitHub Actions par tranche activée (ou matrice) :
  - Windows : runner `windows-latest` → `flutter build windows` → packaging ;
  - macOS : runner `macos-latest` (x64 + arm64 via matrice `macos-13`/`macos-14`) →
    `flutter build macos` → app bundle → DMG/zip → notarisation ;
  - Tests desktop sur runner (DSK-6) avant packaging ;
  - Artefacts publiés en **GitHub Releases** (canal dev et prod, DSK-7).
- La CI desktop est **déclenchée** : (a) à chaque PR touchant l'app (build de
  vérification, sans distribution), (b) à la release de la tranche (build + distribution).

### DSK-5 — Signature & notarisation (obligatoires pour distribution)
| Plateforme | Exigence | Outils types |
|---|---|---|
| Windows | Signature code (certificat) pour éviter SmartScreen ; option MSIX/AppInstaller | certificat EV/OV, `signtool`, ou action GitHub dédiée ; `msix` packaging |
| macOS | Developer ID + **notarisation** (sinon Gatekeeper bloque) + stapling | `notarytool`, certificat Developer ID Application, profil CI |
- Les secrets (certificats, profils) vivent en **GitHub Actions secrets**, jamais dans
  le dépôt (cf. `docs/CI_CD_SECRETS.md`, secret scan).
- Pas de distribution publique sans signature (DSK-8 le bloque en gate).

### DSK-6 — Tests desktop (avant distribution)
1. En CI : `flutter test` (widget) exécuté **sur runner desktop** + `integration_test`
   des parcours critiques de la tranche (`flutter test -d windows` / `-d macos`),
   golden tests (DSG-4).
2. Recette manuelle (checklist « smoke desktop ») sur les 2 OS cibles :
   - installation propre (et mise à jour depuis la version précédente),
   - démarrage, fenêtres redimensionnées/multi-écrans, scaling Windows/macOS,
   - parcours de la tranche (saisie, impression si concerné, export),
   - offline → reconnexion (sync), cache, aucun crash,
   - aucune fonctionnalité mobile fantôme visible (boutons morts interdits),
   - logs/erreurs visibles en canal dev uniquement.
3. Recette métier : UAT pilote (`docs/ops/RECETTE_UAT_*.md` du BC concerné) signée
   avant passage en canal prod.

### DSK-7 — Canaux de distribution
| Canal | Usage | Mécanisme |
|---|---|---|
| dev | chaque build de tranche, testeurs internes | GitHub Release préfixée `desktop-<app>-dev` (artefacts non signés tolérés, marqués) |
| beta/pilotes | UAT avec pilotes métier | GitHub Release `desktop-<app>-beta` (signé) + lien direct |
| prod | clients | GitHub Release semver `desktop-<app>-<version>` (signé/notarisé) ; à terme store/auto-update si justifié |
- La **mise à jour automatique** n'est pas activée par défaut (coût d'infra) : toute
  tranche qui en a besoin fait l'objet d'une issue dédiée (choix : MSIX
  AppInstaller / Sparkle / winsparkle / updater maison — décision PM).

### DSK-8 — DoD d'une tranche desktop
- [ ] Ligne au registre (DSK-1) + décision PM (DSK-2)
- [ ] Build CI vert Windows **et** macOS (selon registre) sans distribution intempestive
- [ ] Tests desktop CI (widget + integration) verts sur runner desktop
- [ ] Smoke desktop signé sur chaque OS cible (checklist DSK-6)
- [ ] UAT pilote signée (RECETTE_UAT_* du BC)
- [ ] Signature Windows / notarisation macOS effective (canal prod)
- [ ] CHANGELOG + version semver alignés ; identité visuelle conforme (protocole 05)
- [ ] Vitrine/README mis à jour (surface V1/V8 — protocole 03) : « clients desktop
      disponibles pour <module> »

## 4. Rituels & responsabilités

| Qui | Rôle |
|---|---|
| PM | décision d'activation (DSK-2), arbitrage canaux, GO tranche |
| Agent CI/gardien desktop | workflows, signature, canaux, registre |
| Pilote métier | UAT (RECETTE_UAT_*) |
| Tout agent | RETEX desktop (problèmes de packaging, plateforme, offline…) — protocole 04 |

## 5. Liens & sources

| Sujet | Où |
|---|---|
| Apps Flutter & identités | `front/mobile_apps/README.md`, `melos.yaml` |
| CI mobile actuelle | `.github/workflows/mobile-apps-ci.yml`, `mobile-distribute.yml` |
| Distribution mobile | `docs/validation/MOBILE_FIREBASE_DISTRIBUTION.md`, `MOBILE_STORE_READINESS.md` |
| Recettes UAT par module | `docs/ops/RECETTE_UAT_*.md` |
| Secrets CI | `docs/CI_CD_SECRETS.md` |

## 6. Écarts constatés le 2026-09-09 (rattrapage)

1. **Aucune chaîne desktop** : à créer (scripts melos + workflows + registre). Premier
   chantier suggéré : activer une tranche pilote simple (ex. comptabilité ou kiosk) pour
   poser la chaîne, puis généraliser.
2. `leopardo_accounting` et `leopardo_travel_agent` n'ont pas de scaffolding desktop —
   à ajouter si une tranche les concerne (`flutter create --platforms=windows,macos .`).
3. Pas de certificats de signature provisionnés dans les secrets GitHub → à prévoir
   (Windows) et Developer ID (macOS) avant tout canal public.
