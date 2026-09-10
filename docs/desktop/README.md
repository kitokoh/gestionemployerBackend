# Desktop Leopardo — registre des tranches verticales & chaîne de distribution

> Créé le 2026-09-09 (issue #7072). Cadre normatif : `docs/PROTOCOLES/P06_DESKTOP_DISTRIBUTION.md`
> (protocole desktop sur main). Ce dossier est le **registre vivant** des clients desktop
> (Windows `.exe` / MSIX, macOS `.app`/DMG) extraits des apps Flutter par tranche
> verticale — jamais de « 6e surface générique ».

## 1. État des apps Flutter (vérifié le 2026-09-09)

| App | Persona | Scaffolding desktop | Chaîne de build/distribution |
|---|---|---|---|
| `leopardo_employee` | Employé (pointage, RH perso) | ✅ windows/macos/linux | ❌ aucune |
| `leopardo_manager` | Manager/RH | ✅ windows/macos/linux | ❌ aucune |
| `leopardo_hr` | RH dédié | ✅ windows/macos/linux | ❌ aucune |
| `leopardo_marketing` | Marketing/communication | ✅ windows/macos/linux | ❌ aucune |
| `leopardo_platform_admin` | Super-admin plateforme | ✅ windows/macos/linux | ❌ aucune |
| `leopardo_accounting` | Comptabilité | ✅ windows/macos (lot 1 — PR #7095, issue #7106) | 🚧 pilote — pipeline CI livré (build + smoke + install-test, canal fermé ; lot 2 #7133 + #7056) |
| `leopardo_travel_agent` | Agent/vendeur Travel | ❌ | ❌ |

Scripts melos `build:windows`/`build:macos` livrés (lot 2, `leopardo_core` ignoré) et
workflow de vérification `.github/workflows/desktop-ci.yml` livré. Le pipeline de
distribution pilote `.github/workflows/desktop-distribute.yml` couvre désormais
`analyze + tests → build → smoke → install-test → artefact (canal fermé)`, sans
signature/notarisation (palier pilote #7055). La CI mobile (`mobile-apps-ci.yml`,
`mobile-distribute.yml`) n'est pas modifiée et reste Android → Firebase App Distribution.

## 2. Registre des tranches desktop (à valider PM)

> Une tranche = { 1 BC + 1 app + workflows à usage intensif + contrat API } justifiée
> par un besoin desktop réel (saisie intensive, offline/périphérique, poste fixe).

| Tranche | BC | App | Cas d'usage | OS cibles | Décision PM |
|---|---|---|---|---|---|
| Comptabilité bureau | BC-08 ACCOUNTING | `leopardo_accounting` | facturation/saisie intensive, impayés | Windows + macOS | **GO** — #7055 (PM, 2026-09-09) |
| Kiosk / poste fixe (pointage) | BC-05 WORKFORCE / BC-25 RESTAURANT | `leopardo_employee` | badgeuse, offline, écran fixe | Windows | ⏳ requise |
| Super-admin bureau | BC-01 PLATFORM | `leopardo_platform_admin` | pilotage plateforme multi-écrans | Windows/macOS | ⏳ requise |

Chaque activation suit le protocole P06 (issue de décision `process` + `desktop` + BC) puis la
chaîne ci-dessous. **Aucune tranche ne s'active sans ligne validée ici.**

## 3. Chaîne de distribution cible

1. **CI de vérification** (livrée — `.github/workflows/desktop-ci.yml`) : build
   Windows/macOS des apps disposant du scaffolding, déclenché quand les dossiers
   `windows/`/`macos/` (ou le workflow) changent. Aucune signature ni distribution.
2. **Scripts melos** (livrés — lot 2, #7133) : `melos run build:windows` →
   `flutter build windows --release` / `build:macos` → `flutter build macos --release
   --no-codesign` (filtre packages, `leopardo_core` ignoré).
3. **Pipeline pilote** (livré — `.github/workflows/desktop-distribute.yml`, #7056) :
   `workflow_dispatch` (app, plateforme, volet, `api_url`) →
   `prepare` (matrice dynamique) → `analyze-and-test` → `build-and-package`
   (Windows `.exe`/zip récursif ; macOS `.app`/ditto — bits d'exécution préservés) →
   `desktop-smoke` (démarrage/fenêtre/fermeture propre) → `install-test`
   (install/désinstall sur runner propre, contrôle d'identité) → `publish-pilot`
   (GitHub Release `desktop-<app>-dev`, **pré-release, non signée**, marquée
   `PILOT_UNSIGNED.txt`). `desktop-smoke` et `install-test` sont des gardes
   **non bloquantes au démarrage** (`continue-on-error`) : à promouvoir en gate dur
   après le premier run pilote vert sur les deux OS (P06 §5). Login + parcours
   critique = UAT pilote (`RUNBOOK_DESKTOP_ACCOUNTING.md` §5), pas en CI (pas de
   credentials en CI).
4. **Signature** (obligatoire pour tout canal public, hors périmètre pilote) :
   Windows — certificat code signing (+ packaging MSIX si retenu) ; macOS —
   Developer ID + notarisation + stapling. Secrets = GitHub Actions secrets
   (jamais dans le dépôt) — ⏳ à provisionner.
5. **Canaux** : dev (GitHub Release `desktop-<app>-dev`, non signé, pré-release —
   ✅ implémenté) → beta/pilotes (signé, UAT via `docs/ops/RECETTE_UAT_*.md` du BC)
   → prod (GitHub Release semver signée). Auto-update non activé par défaut
   (décision par tranche).
6. **DoD de tranche** : checklist du protocole `docs/PROTOCOLES/P06_DESKTOP_DISTRIBUTION.md` (build CI vert ×OS, tests
   desktop, smoke signé, UAT pilote, signature/notarisation, CHANGELOG, vitrine).

## 4. Écarts & dépendances (rattrapage)

- ✅ Décision PM tranche pilote : **GO** Comptabilité bureau (BC-08, `leopardo_accounting`) — #7055.
- ✅ Scaffolding desktop `leopardo_accounting` (windows/macos) — lot 1 (#7095/#7106).
- ✅ Scripts melos, workflow de vérification, workflow pilote, matrice compat desktop + garde — lot 2 (#7133) et #7056.
- ⏳ **Premier run réel** du pipeline `desktop-distribute.yml` (workflow_dispatch) : les jobs
  `desktop-smoke`/`install-test` sont non bloquants tant qu'ils n'ont pas tourné vert une fois
  sur runner Windows **et** macOS — c'est ce run qui valide le smoke/install en conditions (P06 §5).
- ⏳ Provisionner les certificats (Windows) et le Developer ID (macOS) dans les
  secrets GitHub avant tout canal public.
- ⏳ Scaffolding desktop `leopardo_travel_agent` si une tranche le concerne (aucune décision à ce jour).
- ⏳ Harmonisation d'identité desktop (`com.leopardo.<app>`, nom produit « Leopardo Accounting ») :
  le scaffold `flutter create` a produit `com.leopardo.leopardoAccounting` / `leopardo_accounting` —
  à aligner (P06 §3.2) **avant** le premier build signé (l'identité fixe Keychain/DPAPI).

## Liens

- Protocole desktop : `docs/PROTOCOLES/P06_DESKTOP_DISTRIBUTION.md`
- Apps Flutter : `front/mobile_apps/README.md` · CI mobile : `.github/workflows/mobile-apps-ci.yml`
