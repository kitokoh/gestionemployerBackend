# PROTOCOLE 06 — Distribution & tests desktop (tranches verticales)

**Statut :** Actif v1.0 — 2026-09-09
**Porteur :** fondateur/PM + devs mobile
**Revue :** mensuelle fin de mois, cf. docs/PROTOCOLS/00_INDEX.md

> **Pourquoi.** Leopardo est un produit mobile-first (docs/GOTO_MARKET/01_PRODUCT/POSITIONING.md), mais
> des besoins terrain émergent hors smartphone (paie sur PC fixe, réseau instable au pointage, saisie
> de données sensibles). Les 5 apps de lancement ont déjà les scaffolds desktop standards (windows/,
> macos/, linux/), mais rien ne permet de produire, tester ou distribuer un binaire desktop : aucun
> script melos, aucun runner CI Windows/macOS, aucun packaging, aucune doc. Ce protocole définit comment
> extraire, à la demande, des **tranches verticales desktop** et les distribuer proprement — sans faire
> du desktop un pivot produit.

## 1. Objectif & périmètre

**Objectif.** Produire, à partir des apps Flutter existantes (front/mobile_apps/*),
des apps desktop (Windows .exe / macOS .app) par « tranches verticales », et les
tester/distribuer sur 2 canaux (dev/prod) alignés sur la topologie Render
(docs/ops/RENDER_DEV_PROD_TOPOLOGY.md).

**Périmètre.** Apps éligibles : celles ayant un scaffold desktop
(leopardo_employee, leopardo_hr, leopardo_manager, leopardo_marketing,
leopardo_platform_admin) puis, après phase 0, toute app qui en ajoute un.
Hors périmètre : portage desktop complet des apps, Linux desktop (aucun besoin
terrain documenté), kiosques ZKTeco (déjà couverts : front/zkteco-kiosk +
docs/GESTION_PROJET/RUNBOOK_ZKTECO_CLIENT.md). Déclencheurs : une demande de
module/verticale validée par le fondateur (checklist §2) — jamais une décision
« on fait du desktop » globale.

## 2. Tranche verticale desktop : définition & critères de déclenchement (checklist)

**Définition.** Déclinaison desktop d'un **périmètre métier borné**, déjà porté
par une app Flutter et le package partagé leopardo_core, ciblant **une persona**
et son parcours critique, avec synchro réseau ou hors-ligne explicite et
consommation de l'API existante.

| Élément | Contenu attendu | Source |
|---|---|---|
| Persona | 1 seule (gestionnaire paie, RH, manager d'équipe…) | apps par persona |
| BC/domaines | 1 à 3 bounded contexts, déjà dans leopardo_core/lib/features (attendance, payrolls, manager, absences, salary_advances…) | leopardo_core |
| Parcours critiques | ≤ 3 parcours de bout en bout (ex. pointer → consulter → valider) | specs modules |
| Données & synchro | online via API ; hors-ligne borné (stockage local core + file de synchro) si besoin terrain | leopardo_core (storage/database) |
| App hôte | l'app persona existante, réutilisée telle quelle (pas de fork) | front/mobile_apps/* |

**Ce qu'une tranche n'est PAS** : un portage écran-à-écran de l'app mobile, une
nouvelle app par module sans porte produit, un wrapper web, une duplication de
leopardo_core, ni le début d'un pivot desktop.

**Checklist de déclenchement** (toutes cases vertes requises) :
- [ ] Un module/verticale exprime un besoin réel daté (pilote client, processus interne).
- [ ] La persona travaille sur poste fixe (PC de caisse, bureau RH, pointage entrée) ou le mobile est inadapté (saisie longue, écran large, impression).
- [ ] Besoin hors-ligne ou réseau instable identifié et borné (volume, durée).
- [ ] Les BC requis existent dans leopardo_core (pas de développement métier neuf pour la tranche).
- [ ] Les alternatives web (PWA front/web-offline) ou kiosk écartées avec motif.
- [ ] La tranche ne casse pas le split apps (dev-hub/tools/validate-mobile-apps-split.ps1).
- [ ] Coût estimé (phases 0→5) < budget pilote ; sortie de pilote définie.
Décision actée par le fondateur, tracée dans une issue dédiée.

## 3. Cycle de vie en phases (0→5) avec livrables par phase

**Phase 0 — Préparation.** *Livrable : l'app compile en desktop en local.*
Compléter le scaffold manquant (`flutter create --platforms=windows,macos .` —
cas de leopardo_accounting et leopardo_travel_agent, android-only) ; corriger
l'identité produit par app (§4) : titre de fenêtre windows/runner/main.cpp,
méta-données windows/runner/Runner.rc (FileDescription, OriginalFilename),
PRODUCT_NAME et PRODUCT_BUNDLE_IDENTIFIER dans
macos/Runner/Configs/AppInfo.xcconfig, icônes (windows/runner/resources/
app_icon.ico + AppIcon macOS) ; taille min de fenêtre, single-instance si
pertinent. La version pubspec reste non touchée (pilotée par le repo, §4).

**Phase 1 — Build CI.** *Livrable : chaque build desktop est produit en CI sur
un runner dédié.* Besoin à décrire, sans créer les workflows (Annexe A) :
runners `windows-latest` et `macos-latest` (aujourd'hui : 95 jobs CI, tous
`ubuntu-latest`, zéro runner windows/macos), matrice apps × OS calquée sur
mobile-apps-ci.yml, analyze + build --release + upload d'artefacts (l'action
composite actuelle setup-flutter-android est spécifique Android).

**Phase 2 — Packaging.** *Livrable : artefact installable nommé selon §4.*
Windows : `.exe` (`flutter build windows --release`), puis `.msix` quand
l'auto-update sera requis. macOS : `.app` puis `.dmg`. Signature à prévoir
(code signing Windows ; Developer ID + notarisation macOS) ; **au stade pilote,
un binaire non signé est acceptable** (limitations SmartScreen / Gatekeeper,
contournement à documenter aux pilotes).

**Phase 3 — Canal dev vs prod.** *Livrable : les pilotes reçoivent le bon
binaire.* Rattachement aux 2 volets (§6) : canal dev = builds vers l'API de dev
continu (Render `render.yaml`), canal prod = builds stables issus d'une GitHub
Release (tag `vX.Y.Z`, docs/RELEASE_PROCESS.md). Stade pilote : canal dev
distribué manuellement (lien artefact CI), comme Firebase App Distribution pour
l'Android staging ; canal prod = assets de la GitHub Release.

**Phase 4 — Tests.** *Livrable : tranche testée avant distribution.* Pyramide
§5 : unit/widget existants (déjà en CI via mobile-apps-ci.yml), ajout
d'integration_test desktop, smoke install/upgrade/uninstall côté pilotes.

**Phase 5 — Boucle pilotes.** *Livrable : décision de pérenniser ou arrêter.*
Retours via issues, correctifs itératifs sur le canal dev, sortie de pilote
(prod / abandon) actée au rituel mensuel (§7). Pilote sans retour pendant 2
cycles mensuels → mise en pause de la tranche.

## 4. Cibles & artefacts (tableau Windows/macOS/Linux)

| Cible | Build | Packaging | Signature | Canal | Statut 2026-09-09 |
|---|---|---|---|---|---|
| Windows x64 | `flutter build windows --release` | `.exe` ; `.msix` (plus tard) | code signing à prévoir ; pilote = non signé OK | dev puis prod | scaffold seul |
| macOS (arm64 + x64) | `flutter build macos --release` | `.app` ; `.dmg` | Developer ID + notarisation à prévoir ; pilote = non signé OK | dev puis prod | scaffold seul |
| Linux | `flutter build linux --release` | — | — | non prioritaire | scaffold seul |

**Convention d'artefacts.** Nom de fichier :
`<app>-<os>-<arch>-<version>[-dev].<ext>` — ex.
`leopardo-hr-windows-x64-v4.25.0.exe` (prod, version = tag SemVer du repo) et
`leopardo-hr-macos-arm64-v4.25.0-rc.1-dev.dmg` (dev, version du commit source).
- La **version est celle du repo** (tag `vX.Y.Z`, ou SHA pour le dev), jamais la
  version mobile (`employee-v4.12.0` côté Android, mobile-distribute.yml). Les
  apps sont toutes à `version: 1.0.0+1` dans leur pubspec (scaffold, non
  fiable) : la version repo fait foi. `<arch>` : x64/arm64 ; `<app>` = package.
- Canaux dev/prod dans des emplacements distincts (assets GitHub Release en
  prod ; artefact CI / partage pilote en dev) ; un build dev n'est jamais
  présenté comme version stable.

**Identité par app desktop** (héritée des sources design
docs/PROTOCOLS/05_HARMONISATION_DESIGN.md et du branding existant —
leopardo_core/lib/core/branding et /theme) :

| App | Nom produit | Bundle id macOS / CompanyName |
|---|---|---|
| leopardo_hr | Leopardo RH | com.leopardo.rh |
| leopardo_employee | Leopardo Employé | com.leopardo.employee |
| leopardo_manager | Leopardo Manager | com.leopardo.manager |
| leopardo_platform_admin | Leopardo Admin | com.leopardo.platformadmin |
| leopardo_marketing | Leopardo Marketing | com.leopardo.marketing |

État réel constaté (copie du scaffold, à corriger en phase 0) : employee, hr,
manager et platform_admin déclarent tous PRODUCT_NAME `leopardo_rh`, bundle
`com.leopardo.leopardoRh`, fenêtre et exe `leopardo_rh` ; leopardo_marketing
déclare PRODUCT_NAME/exe `leopardo_accounting` (bundle
`com.leopardo.leopardoMarketing` incohérent). Icônes desktop = défaut de
flutter create (5 windows/runner/resources/app_icon.ico identiques).

## 5. Tests desktop (pyramide)

1. **Unit & widget** (base) : les suites par app tournent déjà en CI
   (mobile-apps-ci.yml, `flutter test`) ; la tranche ajoute seulement les tests
   des écrans desktop (layout large, fenêtres) dans test/ de l'app.
2. **Integration_test** (milieu) : dossier integration_test/ par tranche,
   exécuté sur device desktop en CI (`flutter test integration_test -d windows`
   / `-d macos`) : parcours critique de bout en bout contre l'API de dev.
3. **Smoke install / lancement / mise à jour / désinstallation** (haut) : fiche
   de recette par pilote à chaque build dev (install propre, 1er lancement,
   upgrade depuis la version précédente, rollback, désinstallation sans
   résidu). Pas d'automatisation exigée au stade pilote.

Sortie de phase 4 : analyze vert, integration_test du parcours critique vert sur la cible, smoke signé par un pilote.

## 6. Canaux dev & prod (rattachement aux 2 volets)

Références : docs/ops/RENDER_DEV_PROD_TOPOLOGY.md (2 volets) et
docs/PROTOCOLS/07_ARCHITECTURE_ENVIRONNEMENTS.md.

| Canal desktop | Déclencheur | Backend cible | Signature | Destinataires |
|---|---|---|---|---|
| **dev** | push main (build continu) ou build pilote manuel | dev continu (Render `render.yaml` / deploy-main.yml) | non signé toléré | pilotes de la tranche |
| **prod** | GitHub Release publiée (tag `vX.Y.Z` → deploy-prod.yml, prod Render `render.prod.yaml`) | prod stable | signé (requis avant distribution large) | clients / déploiement réel |

Alignement mobile : mobile-distribute.yml distribue staging (APK → Firebase App
Distribution, groupe testeurs-internes) et prod (AAB) selon le tag `v*`
(correctif #4724 : tags `vX.Y.Z`, `-staging`, `-prod`). Le desktop reprend la
même logique binaire dev/prod avec ses artefacts ; il n'utilise pas Firebase App
Distribution et ne crée pas de troisième environnement. Un binaire desktop prod
ne sort qu'après la porte marché (docs/PROTOCOLS/01_VALIDATION_MARCHE.md) : une
tranche pérennisée est une version du produit, pas un outil interne.

## 7. Rituel mensuel

Revue du portefeuille desktop en fin de mois, fondateur/PM + devs mobiles.
Ordre du jour fixe :

1. Portefeuille des tranches (actives / en pause / abandonnées) et leur phase.
2. Par tranche active : canal courant (dev/prod), builds livrés, retours pilotes
   (issues ouvertes/closes), coût cumulé.
3. Décisions : nouvelles tranches (checklist §2), sorties de pilote, identité /
   version à corriger, échéancier signature/notarisation.
4. Mise à jour du protocole si une convention dérive ; compte rendu au journal
   mensuel — les décisions engagent la phase suivante.

## 8. État des lieux au 2026-09-09

**Ce qui existe (vérifié par lecture) :**

- Scaffolds desktop complets (windows/ CMake+runner, macos/ avec
  Configs/AppInfo.xcconfig, linux/) pour 5 apps : leopardo_employee,
  leopardo_hr, leopardo_manager, leopardo_marketing, leopardo_platform_admin.
- Identité desktop par défaut non personnalisée (copie du scaffold, cf. §4).
- melos.yaml : analyze/format/test/build:android/build:ios uniquement (aucun
  build:windows/build:macos). Makefile + build.ps1 (employee, hr, manager,
  platform_admin) : APK staging / AAB prod via dart-define-from-file
  (.env.staging/.env.production), aucune cible desktop.
- CI : 56 fichiers de workflows, 95 jobs, tous ubuntu-latest ; aucun runner
  windows/macos, aucun job desktop. mobile-apps-ci.yml (analyze 8 packages +
  build APK debug 7 apps) et mobile-distribute.yml (4 apps, tags v*, Firebase
  App Distribution) ne couvrent que l'Android.
- Aucune doc desktop dans docs/ (zéro occurrence de `.exe` ni de
  `flutter build windows|macos`) ; positionnement produit mobile-first
  (docs/GOTO_MARKET/01_PRODUCT/POSITIONING.md).
- Précédent de surface standalone distribuée sur PC local hors Flutter : kiosk
  ZKTeco (front/zkteco-kiosk, bridge Python + SQLite hors-ligne,
  docs/GESTION_PROJET/RUNBOOK_ZKTECO_CLIENT.md) — valide le besoin poste fixe.
- Versioning SemVer repo + release par tag `vX.Y.Z` (docs/RELEASE_PROCESS.md) ;
  2 volets dev/prod (docs/ops/RENDER_DEV_PROD_TOPOLOGY.md).

**Ce qui manque :** builds desktop (aucune cible), CI desktop (runners +
workflows à valider, Annexe A), packaging (.exe/.msix/.dmg + signature), tests
desktop (aucun integration_test, pas de smoke), canal de distribution,
identité desktop par app. leopardo_accounting et leopardo_travel_agent sont
android-only : une tranche les concernant commence par la phase 0.

## Annexe A. Squelettes à valider

> À VALIDER par le fondateur avant implémentation — n'éditer ni melos.yaml ni
> .github/workflows sur la seule base de ce protocole.

Extrait melos.yaml (à insérer dans `scripts:`) :

```yaml
  # ── Desktop builds (protocole 06, tranches verticales) ──────────
  build:windows:
    run: flutter build windows --release
    description: Build Windows desktop (release)
    exec:
      concurrency: 1
    packageFilters:
      dirExists: windows        # apps ayant un scaffold windows
      ignore: [leopardo_core]   # bibliothèque partagée, pas une app

  build:macos:
    run: flutter build macos --release
    description: Build macOS desktop (release)
    exec:
      concurrency: 1
    packageFilters:
      dirExists: macos
      ignore: [leopardo_core]
```

Workflow GitHub (squelette — runners windows/macos à activer ; tag `v*` pour la
prod, workflow_dispatch pour le dev) :

```yaml
name: Desktop - Build Windows/macOS
on:
  workflow_dispatch:            # canal dev : build pilote manuel
  push:
    tags: ["v*"]                # canal prod : release taguée (cf. §6)
jobs:
  build-desktop:
    name: Desktop ${{ matrix.app }} (${{ matrix.os }})
    runs-on: ${{ matrix.os }}
    strategy:
      fail-fast: false
      matrix:
        include:
          - { app: leopardo_hr,       os: windows-latest, platform: windows }
          - { app: leopardo_manager,  os: windows-latest, platform: windows }
          - { app: leopardo_hr,       os: macos-latest,   platform: macos }
          # étendre la matrice aux seules apps de la tranche active
    steps:
      - uses: actions/checkout@v4
      - uses: subosito/flutter-action@v2   # variante sans SDK Android
        with: { channel: stable }
      - run: flutter pub get
        working-directory: front/mobile_apps/leopardo_core
      - run: flutter gen-l10n
        working-directory: front/mobile_apps/leopardo_core
      - run: flutter pub get && flutter analyze
        working-directory: front/mobile_apps/${{ matrix.app }}
      - run: flutter build ${{ matrix.platform }} --release
        working-directory: front/mobile_apps/${{ matrix.app }}
      # renommage §4 + upload-artifact (dev) / assets GitHub Release (prod, signé)
```
