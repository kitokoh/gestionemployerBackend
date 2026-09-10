# Tasks: Pilote desktop BC Accounting — Windows/macOS (Part of #7056)

**Spec**: `.specify/features/7056-accounting-desktop-pilot/spec.md`
**Décision**: #7055 (GO)

- [x] T1. Analyse : dépendances desktop de `leopardo_core` (plugins sans support, initialisations dans main/StartupGate), contrats API consommés par `leopardo_accounting`, conventions CI mobiles — **fait 2026-09-09** : app sans Firebase au boot, secure_storage v11 desktop OK, défaut loopback ApiClient OK ; risque = appels runtime à confirmer par build réel (spec US-2)
- [x] T2. Spec + plan + tasks `.specify` (ce dossier) — lot 1 (#7095)
- [x] T3. Scaffolds desktop `leopardo_accounting` (`flutter create --platforms=windows,macos`) — lot 1 (#7095/#7106) ; identifiants `com.leopardo.leopardoAccounting` / nom `leopardo_accounting` produits par le scaffold — harmonisation `com.leopardo.accounting` / « Leopardo Accounting » suivie (`docs/desktop/README.md` §4, avant build signé)
- [ ] T4. Profil desktop `leopardo_core` — neutralisation push/GPS/notifications/Google Sign-In (pattern #3932), zéro changement mobile — **non requis à ce stade** : le build réel n'a révélé qu'un blocage toolchain MSVC (parade `CXXFLAGS`, workflow) ; à réévaluer sur constat runtime (US-2 §2)
- [x] T5. Scripts melos `build:windows` / `build:macos` (packageFilters ignore leopardo_core) — lot 2
- [x] T6. Workflow `desktop-distribute.yml` : `analyze-and-test` → `build-and-package` (zip Windows récursif + ditto macOS, SHA-256, marquage pilote) → `desktop-smoke` → `install-test` → `publish-pilot` (Release pré-release, canal fermé), garde API (#4524, `DEV_API_BASE_URL` #6839) — #7056. `desktop-smoke`/`install-test` non bloquants au démarrage (promotion en gate dur après 1er run pilote vert)
- [x] T7. Extension `release-compat-matrix.json` (desktop : current/min_api) + garde `check-release-compat.sh` — lot 2 (tests locaux verts)
- [x] T8. Guide pilote `docs/GESTION_PROJET/RUNBOOK_DESKTOP_ACCOUNTING.md` — lot 2
- [ ] T9. Vérifications : tests mobiles existants verts (filet anti-régression) via CI lot 2, US-1→US-5
- [ ] T10. Docs : CHANGELOG [Unreleased], honnêteté vitrine vérifiée (#3257), retour d'expérience (P04 §6)
- [ ] T11. PR `feat(desktop): pilote desktop BC Accounting (Part of #7056)` → CI verte → merge → suppression branche
- [ ] T12. Revu pilote : extension à d'autres BC ou arrêt — décision en revue mensuelle
