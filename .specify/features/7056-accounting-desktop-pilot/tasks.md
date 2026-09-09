# Tasks: Pilote desktop BC Accounting — Windows/macOS (Part of #7056)

**Spec**: `.specify/features/7056-accounting-desktop-pilot/spec.md`
**Décision**: #7055 (GO)

- [ ] T1. Analyse : dépendances desktop de `leopardo_core` (plugins sans support, initialisations dans main/StartupGate), contrats API consommés par `leopardo_accounting`, conventions CI mobiles
- [ ] T2. Spec + plan + tasks `.specify` (ce dossier)
- [ ] T3. Scaffolds desktop `leopardo_accounting` (`flutter create --platforms=windows,macos`, identifiants `com.leopardo.accounting`, icônes)
- [ ] T4. Profil desktop `leopardo_core` — neutralisation push/GPS/notifications/Google Sign-In (pattern #3932), zéro changement mobile
- [ ] T5. Scripts melos `build:windows` / `build:macos` (packageFilters ignore leopardo_core)
- [ ] T6. Workflow `desktop-distribute.yml` : runners windows/macos, jobs analyze → tests → build → smoke desktop → install-test → artefact (GitHub Release pilote privée), garde API volet (#4524)
- [ ] T7. Extension `release-compat-matrix.json` (desktop : current/min_api) + garde `check-release-compat.sh` verte
- [ ] T8. Guide pilote `docs/GESTION_PROJET/RUNBOOK_DESKTOP_ACCOUNTING.md` (installation VM propre Windows/macOS, toolchains VS/Xcode, version non notariée étiquetée)
- [ ] T9. Vérifications : tests mobiles existants verts (filet anti-régression), analyze desktop vert, US-1→US-5
- [ ] T10. Docs : CHANGELOG [Unreleased], honnêteté vitrine vérifiée (#3257), retour d'expérience (P04 §6)
- [ ] T11. PR `feat(desktop): pilote desktop BC Accounting (Part of #7056)` → CI verte → merge → suppression branche
- [ ] T12. Revu pilote : extension à d'autres BC ou arrêt — décision en revue mensuelle
