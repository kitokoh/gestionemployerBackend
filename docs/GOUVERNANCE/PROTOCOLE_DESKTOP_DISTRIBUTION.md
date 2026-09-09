# Protocole P6 — Distribution & tests desktop : extraire des clients .exe / macOS par verticale

| | |
|---|---|
| **Statut** | PROPOSÉ (2026-09-09) — à valider par le PM |
| **Dernière revue** | 2026-09-09 |
| **Owner** | PM (décision verticale) ; lead mobile/desktop (implémentation) |
| **Périmètre** | Extraction de clients desktop **Windows (.exe/.msix)** et **macOS (.app/.dmg)** depuis les apps Flutter (`front/mobile_apps/`), par verticale/module ; tests associés |
| **État initial** | État constaté le 2026-09-09 : aucun build desktop ; CI sur `ubuntu-latest` uniquement (APK/AAB → Firebase) ; runners `windows/` et `macos/` **déjà générés** dans employee, hr, manager, platform_admin, marketing ; `accounting`/`travel_agent` = Android seul ; rien n'est signé, notarié, ni distribué |
| **Dépend de** | P1 (gate marché), P2 (conventions), P5 (design), `melos.yaml`, `.github/workflows/mobile-distribute*.yml`, `docs/RELEASE_PROCESS.md` (API-only aujourd'hui — à réécrire et étendre au desktop, cf. P0) |
| **Produit** | `docs/validation/DESKTOP_READINESS_<APP>_YYYY_MM_DD.md` par diffusion |

---

## 1. Principe : le desktop n'est pas un réflexe, c'est une décision

Le desktop est justifié quand le workflow l'exige (intensif clavier, saisie longue, multi-fenêtres,
impression locale, offline durable, kiosque) — cf. carte produit README : *« only justified desktop
workflows such as intensive accounting or kiosk operation »*. Les verticales **mobile-first
(employee, manager, hr)** restent mobiles/web ; le desktop s'extrait **par besoin métier**, jamais
« parce que c'est possible ».

## 2. Critères d'éligibilité d'une verticale au desktop

| Critère | Poids | Exemple Leopardo |
|---------|-------|------------------|
| Saisie intensive / documents longs | Fort | Comptabilité (pièces, journaux, exports bancaires) |
| Impression / exports locaux | Fort | Paie (bulletins), comptabilité |
| Offline durable (chantier, zone sans réseau) | Fort | Pointage/terrain via edge — attention : l'edge Docker existe déjà pour ce besoin |
| Multi-fenêtres / multi-écrans | Moyen | Admin plateforme |
| Usage ponctuel sur mobile | Faible | Employee self-service → **non éligible par défaut** |

**Processus de demande** : issue `feature` (ou `constat interne`) avec remplissage de cette grille →
décision PM → si OUI, la verticale entre dans la matrice §3 avec un owner. Pas d'issue validée = pas de build desktop.

## 3. Matrice desktop par verticale (proposition initiale à valider)

| App Flutter | Runners desktop | Éligible desktop ? | Priorité | Canal cible |
|-------------|-----------------|--------------------|----------|-------------|
| leopardo_accounting | ❌ (Android seul) | **Oui — candidat n°1** (compta intensive) | Haute — générer runners windows/macos d'abord | Installateur maison + MAJ auto |
| leopardo_hr | ✅ | Possible (gestion RH lourde) | Moyenne | Idem |
| leopardo_platform_admin | ✅ | Possible (supervision interne) | Moyenne | Interne uniquement |
| leopardo_employee / manager / marketing | ✅ | Non par défaut (mobile-first) | Faible | — |
| leopardo_travel_agent | ❌ | À étudier (hors socle actuel) | Faible | — |
| zkteco-kiosk (web + bridge Python) | n/a | Packaging installateur (≠ Flutter desktop) | À part | Installateur edge/kiosk (P7 edge) |

## 4. Pipeline de build, signature et notarisation

**Cibles CI** (workflows existants `mobile-distribute*.yml` à étendre, ou nouveau
`desktop-distribute.yml`) :
- Windows : runner `windows-latest`, `flutter build windows --release --dart-define=API_URL=<env>`,
  packaging MSIX (recommandé : auto-update natif) ou Inno Setup (`.exe`) ;
- macOS : runner `macos-latest`, `flutter build macos --release`, `.app` → `.dmg` (+ notarisation).
- Déclencheurs : `workflow_dispatch` par app (staging/dev) et tag `v*` (prod) — même convention que le mobile.

**Signature — à préparer avant toute diffusion publique :**
- Windows : certificat **Authenticode** (OV/EV), `signtool sign` + timestamp ; stockage du certificat en
  secret GitHub Actions (jamais en clair, jamais dans le repo) ;
- macOS : certificat **Developer ID Application** + **notarisation** (`notarytool` + staple) — indispensable
  pour éviter le blocage Gatekeeper ;
- IDs de bundle desktop à définir une fois, convention proposée : `com.leopardo.<app>.<vertical>` ;
- Version desktop = tag semver de la release (cohérence P1, CHANGELOG).

## 5. Canal de distribution & mise à jour

- **Phase 1 (interne/pilotes)** : artefacts signés publiés en GitHub Release (tag `v*`) — accès contrôlé.
- **Phase 2 (verticale en marché, P1 N2+)**: canal de mise à jour automatique :
  - Windows : MSIX + App Installer, ou mise à jour auto signée (type Sparkle/Squirrel) ;
  - macOS : **Sparkle** (standard) avec manifest XML hébergé (GitHub Releases ou R2 Cloudflare) ;
  - le client vérifie la signature à chaque mise à jour ; jamais de mise à jour non signée.
- **Phase 3 (optionnelle, décision PM)** : Microsoft Store / Mac App Store — n'engage pas les phases 1-2.
- Règle : **aucune diffusion desktop sans canal de mise à jour documenté** (une app desktop sans MAU
  devient une dette de sécurité).

## 6. Protocole de tests desktop (obligatoire avant diffusion)

| Niveau | Tests | Exécution |
|--------|-------|-----------|
| Unitaire/widget | Tests existants des apps + core (`flutter test`) | CI (ubuntu + windows) |
| Intégration desktop | `integration_test` Flutter sur Windows **et** macOS (fenêtres, IO locales, impression) | CI `windows-latest` + `macos-latest` |
| Installation & mise à jour | Install fraîche, upgrade depuis version précédente (delta), désinstallation | Manuelle recette (matrice) |
| Matrice OS | Windows 10/11, macOS 13/14/15 | Recette humaine tracée + rapports `docs/validation/` |
| Offline & reprise | Démarrage sans réseau, file d'attente locale, resync | Recette humaine |
| Sécurité | Signature vérifiée, SmartScreen/notarisation OK, secrets d'env (`API_URL`, tokens) absents du binaire | Vérification avant diffusion |
| Design | Tokens du core (P5) — pas de thème parallèle | Revue PR + captures |
| Recette métier | Parcours critiques de la verticale (ex. saisie comptable complète) | Recette pilote (P1 N1/N2) |

**Porte de diffusion** : `DESKTOP_READINESS_<APP>_YYYY_MM_DD.md` (dans `docs/validation/`) reprenant la
matrice ci-dessus + décision Go/No-Go du comité (P1). Un desktop non testé sur la matrice OS n'est pas diffusé.

## 7. Séquence d'implémentation recommandée (milestones)

| Milestone | Contenu | Sortie |
|-----------|---------|--------|
| M1 | Job CI builds desktop non signés (windows+macos) par app éligible ; artefacts sur les tags | Preuve de build |
| M2 | Signature Windows (Authenticode) + stockage secrets ; smoke install Windows | .exe/.msix signé |
| M3 | Notarisation macOS (Developer ID + notarytool) ; smoke Gatekeeper | .dmg notarié |
| M4 | Canal de MAJ (Sparkle/MSIX) + matrice OS recettée + `DESKTOP_READINESS_*` | Diffusion verticale pilote |

Chaque milestone = issues dédiées (labels `desktop`, BC concerné) ; l'état d'avancement est suivi dans
`docs/ARCHITECTURE_STATUS.md` (P7) et revu au rituel mensuel.

## 8. Rôles

| Rôle | Responsabilité |
|------|----------------|
| PM | Décision d'éligibilité par verticale, Go de diffusion, comité (P1) |
| Lead mobile/desktop | Pipeline M1-M4, secrets de signature, qualité des artefacts |
| QA/agents | Matrice OS, rapports de recette, issues `desktop` (P4) |
| Design owner (P5) | Conformité visuelle des clients desktop |

## 9. Indicateurs

- Verticales desktop diffusées / éligibles (cible : accounting d'abord).
- Couverture matrice OS par release desktop (cible : 100 % avant diffusion).
- Délai release → artefact desktop signé disponible (cible : ≤ 2 j ouvrés).
- Incidents post-diffusion (cible : 0 P1/P2 — le canal de MAJ permet le correctif rapide).
