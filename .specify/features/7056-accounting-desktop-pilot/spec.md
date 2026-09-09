# Feature Specification: Pilote desktop BC Accounting — Windows/macOS (Part of #7056)

**Feature Branch** : `feat/7056-accounting-desktop-pilot` (proposée)
**Created**: 2026-09-09 | **Status**: Draft → à valider
**Issues**: décision #7055 (GO, checklist P06 §2 validée) · pipeline #7056 · protocole P06 (`docs/PROTOCOLES/P06_DESKTOP_DISTRIBUTION.md`, mergé PR #7047)
**Spec**: `.specify/features/7056-accounting-desktop-pilot/spec.md`
**Anti-collision**: app `leopardo_accounting` — pas de chantier en cours sur cette app (apps actives : employee/manager/hr/marketing/platform_admin). `leopardo_core` est partagé → **toute modification de core est une zone à coordination** (AGENT-START-HERE.md) : passer par un profil desktop additif, jamais un changement de comportement mobile.

## Contexte

Le protocole P06 cadre l'extraction de clients desktop depuis les apps Flutter par BC vertical.
La décision #7055 acte le **GO pilote BC Accounting** : poste de travail fixe du comptable,
saisie intensive, offline bureau. Aucun installateur public n'existe (#3257) — ce pilote reste
**interne/canal fermé** et n'annonce rien en vitrine.

État réel vérifié dans le dépôt (2026-09-09) :
- `front/mobile_apps/leopardo_accounting` : app Flutter **sans scaffolds `windows/` ni `macos/`** ;
  dépendances légères (flutter_riverpod, go_router, dio, flutter_secure_storage, intl, `leopardo_core`) ;
- `leopardo_core` (partagé) dépend de plugins **sans implémentation desktop complète** :
  `firebase_messaging` (aucun support Windows/macOS), `google_sign_in` (pas Windows),
  `geolocator`, `local_auth`, `flutter_local_notifications`, `image_picker`, `qr_flutter`, `hive_flutter`, drift/sqlite ;
- `melos.yaml` : scripts `build:android`/`build:ios` uniquement ;
- CI : `mobile-apps-ci.yml` (analyze/tests/gardes) ; distribution Android via `mobile-distribute.yml` (Firebase) ;
- patterns existants à réutiliser : garde `PushUnavailable` (#3932), `StartupGate` (anti page noire),
  `requestWithRetry` + `extractDataList/extractDataMap`, offline/sync (`sync_service.dart`).

## Objectifs du pilote (périmètre borné)

1. **Builds reproductibles** Windows (`.exe`/zip) et macOS (`.app`) de `leopardo_accounting` en CI, en plus des builds locaux dev.
2. **Installation & lancement** sur postes pilotes (guide d'installation, version signée ou `unsigned` étiquetée pour l'interne).
3. **Parcours comptable minimal utilisable** : login (API volet dev), consultation des documents/listes clés de l'app, **mode offline** cohérent avec les patterns mobile.
4. **Honnêteté** : aucun lien public de téléchargement (vitrine `/download` = demande d'accès pilote, #3257 intact).

## Non-objectifs (exclus du pilote)

- Publier un installateur public / store / GA (palier P01 non décidé).
- Notarisation macOS + signature Windows **publiques** (évaluées, coûts chiffrés séparément) — le pilote interne peut partir en build non notarié étiqueté.
- Auto-update, multi-fenêtres avancées, kiosque verrouillé.
- Portage desktop des autres apps/BC (le protocole P06 s'appliquera BC par BC après retour pilote).
- Push, géolocalisation, biométrie, Google Sign-In **sur desktop** (profil desktop les désactive proprement).

## User Stories & Critères d'acceptation

### US-1 — Builds desktop en CI (P1)
En tant que développeur, je veux produire un build Windows et un build macOS de `leopardo_accounting` depuis la CI, sans toucher aux builds mobiles.

**Acceptance Scenarios**:
1. `melos run build:windows` (resp. `build:macos`) produit un artefact sur runner `windows-latest` (resp. `macos-latest`).
2. Workflow `desktop-distribute.yml` (workflow_dispatch, inputs : plateforme, volet dev/pilot) calqué sur `mobile-distribute.yml` : analyze → tests → build → smoke → artefact (GitHub Release pilote privée).
3. `leopardo_core` exclu du build (packageFilters.ignore), cohérent avec `build:android`.
4. Aucun impact sur `mobile-apps-ci.yml` (paths/checks mobiles inchangés).

### US-2 — Desktop buildable sans les plugins mobile-only (P1)
En tant que développeur, je veux que l'app compile sur Windows/macOS alors que `leopardo_core` référence des plugins sans support desktop.

**Acceptance Scenarios**:
1. L'analyse (`flutter analyze`) est verte sur desktop pour `leopardo_accounting` + `leopardo_core`.
2. Les initialisations mobile-only (Firebase/push, GPS, notifications, Google Sign-In) sont **neutralisées sur desktop** via profil/addendum conditionnel (pattern `PushUnavailable` #3932), **sans aucun changement de comportement mobile** (vérifié par les tests mobiles existants).
3. `StartupGate` reste le premier widget (anti page noire) ; aucun `await` devant `runApp()` (quick card).
4. Si un plugin desktop exige une déclaration (entitlements macOS, manifest Windows), elle est documentée dans le guide d'installation.

### US-3 — Lancement & login sur poste pilote (P1)
En tant que comptable pilote, je veux installer et ouvrir la session sur mon poste Windows/macOS.

**Acceptance Scenarios**:
1. Installation réussie (guide `docs/GESTION_PROJET/RUNBOOK_DESKTOP_ACCOUNTING.md`) sur VM propre Windows + macOS (test d'installation CI, désinstallation propre).
2. Fenêtre titrée `Leopardo Accounting`, taille minimale raisonnable, fermeture propre (pas de processus orphelin).
3. Login contre l'API du volet choisi au build (dev ↔ API dev ; jamais d'API prod en build dev — garde #4524).
4. Échec réseau → message clair + mode offline consultable (patterns `requestWithRetry`/cache existants).

### US-4 — Consultation offline des documents (P2)
En tant que comptable pilote, je veux retrouver les documents récents même sans réseau.

**Acceptance Scenarios**:
1. Liste des documents consultable offline à partir du cache local (mêmes mécanismes que le mobile).
2. Aucune écriture offline risquée dans le pilote (lecture seule hors connexion) — borné par le contrat API existant.
3. La cohérence des données est celle du contrat mobile (aucune logique desktop parallèle).

### US-5 — Honnêteté de la communication (P1)
**Acceptance Scenarios**:
1. Aucune chaîne publique (vitrine, README, stores) ne mentionne un client desktop téléchargeable (garde #3257 et protocole P03 inchangés).
2. L'artefact pilote est étiqueté `PILOT — non signé/notarié` si applicable.

## Décisions techniques proposées (à confirmer au plan)

1. **Profil desktop dans `leopardo_core`** : couche d'initialisation conditionnelle `if (!kIsWeb && Platform.isWindows/MacOS)` — stub push/GPS/notifications (pattern existant #3932). Pas de fork de core.
2. **Scaffolds** : `flutter create --platforms=windows,macos --project-name leopardo_accounting .` dans l'app (idem P06 §3.2), identifiants alignés `com.leopardo.accounting`, icônes de marque.
3. **Versioning** : version desktop = pubspec de l'app ; extension de `release-compat-matrix.json` (desktop : `current`, `min_api`) vérifiée par `check-release-compat.sh`.
4. **Signature** : pilote interne en build non notarié étiqueté ; chiffrage signature publique (certificat Windows, Developer ID + notarisation) documenté dans #7056 avant tout palier GA.
5. **Environnement** : `API_BASE_URL` injectée au build (dev/pilot), garde anti-API-prod en dev (pattern #4524).
6. **Melos** : scripts `build:windows` / `build:macos` additifs (aucune modification des scripts mobiles).

## Risques & mitigations

| Risque | Mitigation |
|---|---|
| Plugins core sans support desktop (firebase_messaging…) | Profil desktop conditionnel (US-2) ; tests mobiles existants = filet anti-régression |
| Coût runners macOS en CI | Workflow déclenché à la demande (workflow_dispatch) — jamais sur chaque push ; budget suivi en revue mensuelle |
| Drift/sqlite desktop (dépendances natives) | Builds desktop locaux d'abord ; documenter versions toolchain (Visual Studio / Xcode) dans le guide |
| Dérive de promesse vitrine | US-5 ; aucune surface publique modifiée sans décision GA (P01/P03) |
| `leopardo_core` partagé → risque mobile | Modifications core = zone à coordination ; PRs core desktop séparées, tests mobiles verts exigés |

## Definition of Done (pilote)

1. Workflow `desktop-distribute.yml` vert sur Windows **et** macOS pour `leopardo_accounting` (build + smoke + install-test).
2. Login + consultation offline documentés et vérifiés sur un poste pilote (preuve : guide + retour pilote).
3. `release-compat-matrix.json` étendu desktop ; garde verte.
4. Aucune promesse publique (US-5) ; CHANGELOG à jour ; retour d'expérience du pilote capitalisé (P04 §6).
5. Décision de suite (étendre/arrêter, autres BC) en revue mensuelle.
