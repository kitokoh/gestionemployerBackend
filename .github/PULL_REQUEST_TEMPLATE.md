## 📝 Pull Request Overview

**Issue Reference(s):** Fixes # <!-- une ligne "Fixes #N" / "Closes #N" par issue si cette PR livre un lot BC (docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md) -->
> ⚠️ **Obligatoire (PA2-OPS-008 + règle #2512) :** cette PR doit obligatoirement inclure `Closes #XXX` (ou `Fixes #XXX` / `Resolves #XXX`) **dans le corps (description)** de la PR — le titre seul est fragile et ne ferme pas l'issue de façon fiable. Sauf PR explicitement typée `docs:`/`chore:`. Garde CI : `.github/workflows/pr-issue-guard.yml`, `dev-hub/tools/check-pr-closes-issue.sh`. Pour une PR de lot BC (branche `bc/<code>-*`), répéter le mot-clé pour CHAQUE issue fermée.
> ⚠️ **Gardes CI (RET-1, triage 2026-09-09) :** une garde jugée inadaptée se corrige par une **PR dédiée** — il est interdit de la contourner ou de la désactiver localement ; tout contournement doit ouvrir une issue RETEX immédiate.
**Category:** [Feature / Bug Fix / Documentation / Refactor]

---

## 🚀 Changes Description

Please provide a clear and concise description of the changes introduced by this PR.

-   Item 1
-   Item 2

---

## 🗺️ Surfaces touchees

Cocher toutes les surfaces reellement modifiees par cette PR (aide au triage) :

-   [ ] API (`api/app`)
-   [ ] Web (`front/web`)
-   [ ] Admin dashboard (`front/admin-dashboard`)
-   [ ] Mobile (`front/mobile_apps/*`)
-   [ ] Kiosk (`front/zkteco-kiosk`)
-   [ ] CI / GitHub Actions (`.github/workflows`)
-   [ ] Docs uniquement (`docs/`, `*.md`)
-   [ ] Infra / config (`render.yaml`, `docker*`, etc.)

---

## 🔌 Contrat API

-   [ ] Cette PR ajoute/modifie une route, un payload de requete, ou une reponse API existante.
    -   Si coche : `openapi.yaml` mis a jour ? [ ] oui / [ ] non applicable
    -   Si coche : compatibilite retro-active verifiee pour web/mobile/kiosk existants ? [ ] oui / [ ] non applicable
-   [ ] Aucun changement de contrat API dans cette PR.

---

## ⚠️ Risques residuels

Listez les risques connus, limitations, ou dette technique deliberement laissee de cote (vide si aucun) :

-   Aucun / voir description ci-dessus.

---

## 🎨 Design Review — DSG-6 (PR touchant une UI)

> Obligatoire si une surface UI est cochée ci-dessus (Web / Admin / Mobile / Kiosk / Vitrine).
> Référence : `docs/PROTOCOLES/P05_DESIGN_HARMONISE.md` (DSG-6) + `docs/REFERENTIEL_PRODUIT/COULEURS.md` (tokens).

-   [ ] Tokens : couleurs/typo/rayons issus du design system (pas de hex hors palette ni classes legacy).
-   [ ] États UI couverts (vide, chargement, erreur, succès, disabled).
-   [ ] Contraste AA vérifié (texte sur fond).
-   [ ] Responsive : rendu contrôlé aux breakpoints principaux (mobile/tablette/desktop).
-   [ ] i18n/RTL : chaînes externalisées (×4 langues) et rendu RTL (ar) vérifié.
-   [ ] Assets : tailles/format conformes, pas de tracker tiers ajouté.
-   [ ] Golden tests / captures : captures avant/après jointes pour toute évolution visuelle (P05 §3).
-   [ ] Non applicable (PR sans impact visuel).

---
## 🛡 Quality Checklist (Enterprise Standards)

-   [ ] **Code Quality:** My code follows the project's coding conventions (PSR-12, ESLint).
-   [ ] **i18n (L-10):** toute chaîne visible ajoutée l'est dans les **4 langues** (fr/en/ar/tr) — backend `api/lang`, `shared/i18n/locales`, ARB `leopardo_core` (garde `check-i18n-catalog-parity.sh`, #7089).
-   [ ] **Testing:** I have added or updated tests for my changes.
-   [ ] **Verification:** I have verified the changes locally (API, Web, or Mobile).
-   [ ] **Documentation:** I have updated the relevant documentation hub files.
-   [ ] **Security:** I have checked for potential security implications (RBAC, SQLi, XSS).
-   [ ] **Breaking Changes:** This PR does not break existing functionality (or provides a migration path).

---

## 📸 Screenshots / Demos

**Obligatoire si une case UI est cochee ci-dessus** (Web, Admin dashboard, Mobile, ou Kiosk). Ajouter des captures/GIFs avant/apres.

Non applicable si aucune surface UI n'est touchee.

---

## 🤝 Contributor Agreement

By submitting this PR, I agree that my contributions are licensed under the **MIT License**.
