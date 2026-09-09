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
| `leopardo_accounting` | Comptabilité | ❌ (Android only) | ❌ |
| `leopardo_travel_agent` | Agent/vendeur Travel | ❌ | ❌ |

Aucun script melos `build:windows`/`build:macos`, aucun workflow desktop, aucune
signature/canal : **tout est à créer**. La CI mobile actuelle (`mobile-apps-ci.yml`,
`mobile-distribute.yml`) ne couvre que l'Android → Firebase App Distribution.

## 2. Registre des tranches desktop (à valider PM)

> Une tranche = { 1 BC + 1 app + workflows à usage intensif + contrat API } justifiée
> par un besoin desktop réel (saisie intensive, offline/périphérique, poste fixe).

| Tranche | BC | App | Cas d'usage | OS cibles | Décision PM |
|---|---|---|---|---|---|
| Comptabilité bureau | BC-08 ACCOUNTING | `leopardo_accounting` | facturation/saisie intensive, impayés | Windows (+macOS ?) | ⏳ requise |
| Kiosk / poste fixe (pointage) | BC-05 WORKFORCE / BC-25 RESTAURANT | `leopardo_employee` | badgeuse, offline, écran fixe | Windows | ⏳ requise |
| Super-admin bureau | BC-01 PLATFORM | `leopardo_platform_admin` | pilotage plateforme multi-écrans | Windows/macOS | ⏳ requise |

Chaque activation suit le protocole P06 (issue de décision `process` + `desktop` + BC) puis la
chaîne ci-dessous. **Aucune tranche ne s'active sans ligne validée ici.**

## 3. Chaîne de distribution cible

1. **CI de vérification** (livrée — `.github/workflows/desktop-ci.yml`) : build
   Windows/macOS des apps disposant du scaffolding, déclenché quand les dossiers
   `windows/`/`macos/` (ou le workflow) changent. Aucune signature ni distribution.
2. **Scripts melos** (à ajouter lors de l'activation d'une tranche) :
   `melos run build:windows -- -t <app>` / `build:macos` (`flutter build windows` /
   `flutter build macos --release`, filtre packages, `leopardo_core` ignoré).
3. **Signature** (obligatoire pour tout canal public) : Windows — certificat code
   signing (+ packaging MSIX si retenu) ; macOS — Developer ID + notarisation +
   stapling. Secrets = GitHub Actions secrets (jamais dans le dépôt).
4. **Canaux** : dev (GitHub Release `desktop-<app>-dev`, non signé toléré, marqué) →
   beta/pilotes (signé, UAT via `docs/ops/RECETTE_UAT_*.md` du BC) → prod
   (GitHub Release semver signée). Auto-update non activé par défaut (décision par
   tranche).
5. **DoD de tranche** : checklist du protocole `docs/PROTOCOLES/P06_DESKTOP_DISTRIBUTION.md` (build CI vert ×OS, tests
   desktop, smoke signé, UAT pilote, signature/notarisation, CHANGELOG, vitrine).

## 4. Écarts & dépendances (rattrapage)

- ⏳ Décision PM : tranche pilote (recommandation : Comptabilité bureau — BC-08 —
  ou Kiosk) — issue #7072.
- ⏳ Provisionner les certificats (Windows) et le Developer ID (macOS) dans les
  secrets GitHub avant tout canal public.
- ⏳ Ajouter le scaffolding desktop à `leopardo_accounting` /
  `leopardo_travel_agent` si une tranche les concerne.
- Le workflow `desktop-ci.yml` est livré **non déclenché** tant qu'aucun dossier
  desktop ne change : première activation = première validation réelle en conditions.

## Liens

- Protocole desktop : `docs/PROTOCOLES/P06_DESKTOP_DISTRIBUTION.md`
- Apps Flutter : `front/mobile_apps/README.md` · CI mobile : `.github/workflows/mobile-apps-ci.yml`
