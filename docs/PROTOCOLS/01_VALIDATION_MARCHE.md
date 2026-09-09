# PROTOCOLE 01 — Validation & mise sur le marché

**Statut :** Actif v1.0 — 2026-09-09
**Porteur :** fondateur (@kitokoh) / PM
**Revue :** mensuelle fin de mois (rituel §7) — index des protocoles : docs/PROTOCOLS/00_INDEX.md

> **Pourquoi.** Des versions ont été livrées ou déclarées sans porte unique datée : le
> 2026-08-19, le funnel prospect était cassé en prod alors que la santé API était OK (#5161 P0,
> #5162 P1 — docs/qa/QA_PROD_2026-08-19.md) ; le 2026-08-27, verdict QA « PAS PRÊT mais très
> proche » (Redis down prod, kiosk/PWA cassés — docs/qa/QA_RAPPORT_2026-08-27.md), suivi de
> corrections sans re-certification produit depuis ; et la chaîne tag → release.yml →
> deploy-prod.yml ne vérifie aucun critère produit (docs/RELEASE_PROCESS.md). Ce protocole
> consolide l'existant en UNE porte datée, sans rien dupliquer.

## 1. Objectif & périmètre

**Objectif.** Définir « La Porte Marché » : la validation unique, datée et consolidée qu'une
version doit franchir pour être déclarée « OK pour la mise sur le marché ». Elle chaîne :
tag SemVer → CI verte → readiness → smoke funnel prospect en prod → recette de version →
sign-off explicite → déploiement prod. Elle référence les gates existants (§2) au lieu de les
recopier : toute évolution d'un gate référencé s'applique sans rééditer ce protocole (R11).

**Périmètre.** Toute version destinée au marché : release commerciale prod multi-pays, cœur
transverse RH-pointage-paie et solutions verticales pilotes. Hors périmètre : les
déploiements continus de dev (deploy-main.yml), qui ne relèvent pas de la mise sur le marché.

**Règle d'or.** Déclarer une version « OK pour la mise sur le marché » sans une Porte Marché
exécutée et datée est interdit (R1). Les décisions Go/No-Go existantes (RELEASE_READINESS_GATE.md)
restent la référence ; ce protocole ajoute les cases manquantes : recette de version générique,
re-certification, critères produit, sign-off formalisé.

## 2. Références existantes

| Document / outil | Rôle dans la porte |
|---|---|
| docs/validation/RELEASE_READINESS_GATE.md (vivant) | Table de décision Go / Go conditionnel / No-Go / No-Go produit, surfaces contrôlées, commandes de validation |
| dev-hub/tools/release-readiness.ps1 | 28 checks locaux de readiness ; rapport daté exigé (dernier : RELEASE_READINESS_REPORT_2026_08_14.md, 28/28 PASS) |
| docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md | Matrice canonique domaine → scénarios → workflow → artefacts → gate ; définition « tests concluants » en 5 conditions = définition de la CI verte |
| docs/plan/PLAN_100PCT.md | DoD « 100 % prod-ready » en 8 cases par module (dont « Recette ») : définition produit du prod-ready |
| docs/RELEASE_PROCESS.md + .github/workflows/release.yml + deploy-prod.yml | Chaîne SemVer : tag vX.Y.Z → GitHub Release → déploiement prod (API Render, web Vercel, admin Cloudflare Pages). Vérifie les checks CI requis, aucun critère produit |
| .github/workflows/e2e-staging.yml (renommé « E2E - Playwright Prod Smoke », cf. #3906) + front/web/playwright.staging-funnel.config.ts | Exécution E2E du funnel prospect contre la PROD (signup trial, provisioning, OTP) ; 59 specs Playwright au repo (31 front/web/e2e + 28 front/admin-dashboard/e2e) |
| docs/validation/PILOT_RELEASE_GO_NOGO_CHECKLIST.md (figé, 2026-07-25) | Modèle de Go/No-Go daté par surface avec preuves (« Go conditionnel ») ; à rejouer à chaque porte |
| docs/validation/RELEASE_READINESS_REPORT_2026_08_14.md, MARKET_LAUNCH_AUDIT_2026_06_05.md, rapports QA (figés) | Preuves historiques datées, jamais réécrites ; règle vivants/figés de docs/validation/README.md |
| docs/qa/QA_RAPPORT_2026-08-27.md, docs/qa/QA_PROD_2026-08-19.md | Verdicts QA datés ; déclenchent l'obligation de re-certification (R7) |
| docs/payroll/dz/RECETTE_PILOTE.md, docs/ops/RECETTE_UAT_TRAVEL.md (idem FUELSTATION / RESTAURANTMANAGER / EDUMANAGER) | Recettes verticales existantes ; source du gabarit générique §5 |
| docs/GUIDES/GUIDE_TESTEURS_PILOTES.md | Comptes demo, liens testeurs, personas et parcours transverses pour exécuter la recette |

## 3. Règles

Chaque règle est écrite : condition → action → responsable → garde existante ou « à créer ».

- **R1 — Porte Marché obligatoire.** Condition : une version est candidate à la mise sur le
  marché. Action : exécuter intégralement la checklist §4 et produire le rapport daté §5 avant
  tout Go. Responsable : préparation QA/agent, décision fondateur. Garde : le présent protocole
  (à créer : issue de suivi + gabarit d'issue §5).
- **R2 — CI verte selon le registre.** Condition : SHA candidat au tag. Action : vérifier que
  les 5 conditions « tests concluants » du registre sont remplies (workflows verts + artefacts
  minimums du domaine, aucun job critique failure/cancelled/timed_out). Responsable : agent/PM.
  Garde : docs/GESTION_PROJET/REGISTRE_SCENARIOS_TESTS.md (existe).
- **R3 — Readiness locale 28/28.** Condition : avant chaque porte. Action : exécuter
  `dev-hub/tools/release-readiness.ps1 -Strict` (équivalent bash si pas de PowerShell) et
  joindre un rapport daté au modèle RELEASE_READINESS_REPORT_*. Responsable : QA.
  Garde : docs/validation/RELEASE_READINESS_GATE.md (existe).
- **R4 — Décision par surface avec preuves.** Condition : candidat. Action : produire un
  Go/No-Go daté par surface (API, admin, vitrine, mobile, kiosk, sécurité, ops) avec preuves CI
  datées, au modèle PILOT_RELEASE_GO_NOGO_CHECKLIST.md. Responsable : QA (proposition), fondateur
  (décision). Garde : modèle existant ; la case « porte » est à créer.
- **R5 — Smoke funnel prospect en prod.** Condition : toute version market. Action : exécuter
  la suite funnel (playwright.staging-funnel.config.ts via e2e-staging.yml) : trial guidé
  signup → `ready` < 2 min avec login_url, trial self-service → OTP reçu → login, checkout,
  puis création employé sur le tenant provisionné. Le verdict santé API seul ne suffit pas
  (leçons #5161/#5162). Responsable : QA. Garde : specs et workflow existent ; l'intégration de
  ce smoke comme condition bloquante de la porte est à créer.
- **R6 — Recette de version générique.** Condition : version market multi-pays ou cœur
  RH-pointage-paie. Action : un testeur pilote exécute la recette générique §5 (journal signé,
  zéro anomalie bloquante) sur au moins un pays de production de la version et un parcours
  vertical si le périmètre l'inclut. Responsable : PM + testeur pilote (supports :
  GUIDE_TESTEURS_PILOTES.md). Garde : gabarit §5 à créer (les recettes verticales existantes ne
  couvrent pas le cœur transverse).
- **R7 — Re-certification après corrections.** Condition : verdict ≠ Go (No-Go, No-Go produit,
  PAS PRÊT) puis corrections mergées. Action : re-exécuter la checklist §4 complète sur le SHA
  final et déposer un nouveau rapport daté ; interdiction de déployer en prod sur un verdict
  ancien + corrections sans re-certification (anti « PAS PRÊT → corrections → silence »,
  cf. QA_RAPPORT_2026-08-27 → PR #5698 sans re-cert formelle depuis). Responsable : QA.
  Garde : à créer (champ « re-certification » du rapport §5).
- **R8 — Critères produit dans la porte.** Condition : candidat. Action : cocher les cases
  produit C1-C7 (§4) — parcours prospect complet et parcours cœur recettés, pas seulement la
  santé API — et les DoD 8 cases de docs/plan/PLAN_100PCT.md pour les modules de la version.
  Responsable : préparation QA/agent. Garde : à créer (cases C1-C7).
- **R9 — Sign-off fondateur unique.** Condition : cases §4 cochées et rapport déposé. Action :
  @kitokoh prononce seul le Go final (ou Go conditionnel motivé), daté et référencé sur l'issue
  de recette ; la préparation QA/agent ne décide jamais seule. Responsable : fondateur.
  Garde : à créer (formalisation du sign-off dans le gabarit §5).
- **R10 — Traçabilité et non-réécriture.** Condition : tout rapport produit par ce protocole.
  Action : nommer avec date (`*_YYYY_MM_DD.md` ou `*_YYYY-MM-DD.md`), déposer dans docs/validation/
  ou docs/qa/, ne jamais réécrire rétroactivement ; l'état courant se lit sur `main` et les
  documents vivants. Responsable : auteur du rapport. Garde : docs/validation/README.md (existe).
- **R11 — Pas de duplication.** Condition : évolution d'un gate référencé (§2). Action : mettre
  à jour le gate source ; ce protocole s'y réfère sans le recopier. Toute nouvelle exigence
  récurrente de validation s'ajoute ici par règle numérotée, pas en annexe d'un rapport.
  Responsable : PM. Garde : présent protocole (actif).

## 4. La Porte Marché — checklist exécutable

Tout doit être coché avant le Go final (R9). Une case non cochée = No-Go, sauf Go conditionnel
motivé par écrit (R4) et accepté par le fondateur.

**A. CI et qualité (SHA exact du tag)**
- [ ] A1 `Tests - Leopardo RH` success (≈ 4 010 tests backend, 60 min ; coverage gate ≥ 65 % ;
      payroll-ci.yml ≥ 80 %) + artefacts du registre (R2)
- [ ] A2 `Web CI - Leopardo Admin` success si le SHA touche front/admin-dashboard/**
- [ ] A3 `Web Marketing CI - Leopardo Public` success si le SHA touche front/web/**
- [ ] A4 Jest vitrine exécuté et vert (633 tests après correctifs du 2026-08-27 ; jamais en CI à
      ce jour → exécution locale obligatoire par le préparateur tant que le job CI n'existe pas)
- [ ] A5 Mobile : flutter analyze + flutter test verts (58 fichiers unit/widget ; aucun
      integration_test → compensé par la recette manuelle mobile de la recette §5)
- [ ] A6 Checks requis de protection de branche verts sur le SHA taggé (dont PHPStan strict,
      Backend Coverage, Module Structure, Frontend ESLint+TS)

**B. Readiness et décision**
- [ ] B1 `release-readiness.ps1 -Strict` 28/28 + rapport daté (R3)
- [ ] B2 Go/No-Go daté par surface avec preuves CI datées (R4)
- [ ] B3 Aucun P0/P1 ouvert sur le périmètre de la version ; P2/P3 documentés avec mitigation
      si Go conditionnel

**C. Produit — parcours prospect et cœur (R5, R8)**
- [ ] C1 Funnel prospect prod : trial guidé signup → `ready` < 2 min avec login_url fonctionnel
      (anti #5161)
- [ ] C2 Trial self-service : signup → OTP reçu → login complété (anti #5162)
- [ ] C3 Checkout / activation du plan payant tel qu'exposé au marché
- [ ] C4 Cœur RH-pointage-paie recetté sur un pays de production de la version : employé créé,
      pointage, cycle paie → bulletin conforme, export (DoD 8 cases, PLAN_100PCT §1)
- [ ] C5 Surfaces livrées avec la version fonctionnelles en prod : kiosk ZKTeco, PWA offline,
      apps mobiles installables (anti régression du 2026-08-27)
- [ ] C6 i18n ×4 (fr/ar/tr/en) vérifiée sur les parcours recettés, rendu RTL arabe
- [ ] C7 Isolation tenant (404 sûr cross-tenant) et RBAC vérifiés sur les parcours recette

**D. Recette et sign-off**
- [ ] D1 Recette de version générique §5 exécutée sans assistance par un testeur pilote,
      journal daté signé (R6)
- [ ] D2 Rapport de porte daté déposé (gabarit §5) avec verdict et preuves
- [ ] D3 Re-certification effectuée si corrections post-verdict : dernier rapport = SHA du tag (R7)
- [ ] D4 Go final explicite de @kitokoh, daté, sur l'issue de recette (R9)

**E. Mise sur le marché**
- [ ] E1 CHANGELOG [Unreleased] consolidé + bump de version (docs/RELEASE_PROCESS.md)
- [ ] E2 Tag vX.Y.Z (jamais de tag manuel) → release.yml → deploy-prod.yml verts (API/web/admin)
- [ ] E3 Smokes post-déploiement prod verts : /api/v1/health, demo-auth, vitrine/admin 200
- [ ] E4 Décision d'exposition multi-pays (feature flag vs tous tenants) actée par le fondateur
      (cf. RELEASE_READINESS_REPORT_2026_08_14.md)

## 5. Gabarit « recette de version »

Bloc à copier dans une issue de recette (une issue par version ; titre : `Recette vX.Y.Z — <pays/périmètre>`).

```
# Recette de version vX.Y.Z (issue de la Porte Marché — PROTOCOLE 01)

## 1. Contexte
- Version / SHA exact du tag : vX.Y.Z @ <sha>   (SHA = celui du rapport de porte)
- Date de la recette : YYYY-MM-DD · Environnement : prod · Pays couverts : <DZ/MA/...>
- Périmètre : cœur RH-pointage-paie (obligatoire) + verticale(s) : <optionnel>
- Testeur pilote : <nom> · Relecteur métier/expert : <nom> (si paie : expert-comptable pays)
- Issue de suivi de la version : <#issue> · Lien rapport de porte : docs/validation/<fichier>

## 2. Parcours obligatoires
| # | Parcours | Résultat attendu | Réussi ? |
|---|---|---|---|
| G1 | Prospect : trial guidé → ready < 2 min → login_url | Compte provisionné, accès sans assistance | ☐ |
| G2 | Prospect : trial self-service → OTP → login | OTP reçu, login complété | ☐ |
| G3 | Checkout / activation plan (si exposé) | Paiement ou CTA conforme au marché | ☐ |
| G4 | Employé : création complète → login employé | Fiche conforme, accès fonctionnel | ☐ |
| G5 | Pointage : mobile/kiosk → journée validée | Pointages corrects, anomalies gérées | ☐ |
| G6 | Paie : run pays → bulletin conforme → export | Net/taux exacts (écart ≤ 0,01), PDF valide | ☐ |
| G7 | i18n : parcours G4-G6 en fr + ar (RTL) | 0 chaîne cassée, rendu correct | ☐ |
| G8 | Isolation : accès cross-tenant | 404 sûr, aucune fuite | ☐ |

## 3. Journal de validation (une ligne par session)
| Date | Parcours | Résultat (chiffres clés) | Écart / anomalie (#issue) | Exécuté par |
|---|---|---|---|---|
|      |         |                          |                          |             |

## 4. Verdict de la recette
- [ ] Zéro anomalie bloquante ; anomalies non bloquantes tracées en issues
- [ ] Recette signée par le testeur pilote (date) et le relecteur métier (date)

## 5. Re-certification (R7 — ne remplir que si corrections post-verdict)
- Corrections mergées : <PRs> · SHA final : <sha>
- [ ] Checklist §4 du protocole re-exécutée sur le SHA final, nouveau rapport daté : <fichier>

## 6. Sign-off Porte Marché
- [ ] Cases §4 toutes cochées (rapport de porte joint)
- [ ] Go final @kitokoh : <Go / Go conditionnel / No-Go> — date et motif :
```

Rapport de porte = ce gabarit complété, déposé daté (R10). Les verticales peuvent ajouter leurs
scénarios UAT existants (RECETTE_UAT_*, RECETTE_PILOTE) en annexe, sans les recopier dans ce gabarit.

## 6. Indicateurs de santé du protocole

| Indicateur | Cible | Mesure |
|---|---|---|
| Versions market passées par une Porte Marché datée complète | 100 % | Rapports §5 déposés vs tags vX.Y.Z mis en prod |
| Verdict ≠ Go → re-certification datée | 100 % sous 5 jours ouvrés | Dernier rapport §5 vs date du verdict QA |
| Incident prod P0 sur un parcours couvert par la porte, découvert après Go | 0 | Issues P0 ouvertes post-release (ex. #5161 : découvert 2026-08-19 car la porte n'existait pas) |
| Funnel prospect exécuté en prod à chaque porte | 100 % des portes | Case C1-C3 cochée + run e2e-staging.yml |
| Corrections mergées sans re-certification | 0 | Suivi issues : « PAS PRÊT (date) → corrections → rapport (date) » |
| Écart registre vs portes | 0 | §8 relu à chaque rituel mensuel |

## 7. Rituel mensuel

**Entrée.** Rapports de porte du mois, releases mises en prod, incidents prod et issues P0/P1
ouvertes, changements des gates référencés (§2).

**Actions (dernière semaine du mois, porteur + QA).**
1. Re-vérifier l'état courant : A1-A6 et B1 sur main (les rapports figés ne font pas foi,
   docs/validation/README.md).
2. Comparer les verdicts de porte aux incidents réels : tout P0 prod non intercepté par la
   porte enrichit la checklist §4 (une case ou une règle, pas une annexe).
3. Mettre à jour §8 (état des lieux) avec les cases créées/restantes.
4. Mettre à jour docs/PROTOCOLS/00_INDEX.md (statut de ce protocole, liens croisés).

**Sortie.** §8 à jour, index 00 à jour, nouvelles issues si écart constaté, version de ce
protocole incrémentée (v1.1, v2.0...) en tête de fichier si les règles changent.

## 8. État des lieux au 2026-09-09

| Élément de la porte | Existe déjà | Manque / à créer |
|---|---|---|
| CI verte + artefacts (A1-A3, A5-A6) | Oui — REGISTRE_SCENARIOS_TESTS.md + workflows (tests.yml, payroll-ci.yml ≥ 80 %, coverage ≥ 65 %) | Job jest vitrine en CI (A4 : exécution locale seulement) ; integration_test mobile (58 fichiers unit/widget, 0 integration_test) |
| Readiness locale (B1) | Oui — RELEASE_READINESS_GATE.md + release-readiness.ps1 (rapport 2026-08-14 : 28/28) | Rapport à rejouer à chaque porte (dernier figé au 14/08) |
| Go/No-Go par surface (B2) | Modèle — PILOT_RELEASE_GO_NOGO_CHECKLIST.md (2026-07-25, « Go conditionnel ») | Case « porte » datée rejouée systématiquement |
| Smoke funnel prospect prod (C1-C3) | Specs E2E (front/web/e2e) + e2e-staging.yml « Prod Smoke » (#3906) | Condition bloquante de la porte (aucune porte ne l'exigeait : #5161/#5162 passés en prod) |
| Recette de version générique (D1) | Recettes verticales seulement (RECETTE_UAT_TRAVEL/FUELSTATION/RESTAURANTMANAGER/EDUMANAGER, RECETTE_PILOTE paie DZ) | Gabarit générique cœur RH-pointage-paie multi-pays (§5) + issue de suivi du protocole |
| Re-certification post-corrections (D3) | Rien de formalisé | Règle R7 (QA_RAPPORT 2026-08-27 « PAS PRÊT » → corrections PR #5698 → aucun rapport de re-cert depuis) |
| Critères produit (C4-C7) | DoD 8 cases PLAN_100PCT.md ; deploy-prod.yml ne vérifie que les checks CI | Cases C1-C7 dans la porte |
| Sign-off fondateur (D4) | De fait (décisions fondateur dans les issues) | Sign-off daté formalisé dans le gabarit §5 |
| Traçabilité (R10) | Oui — règle vivants/figés docs/validation/README.md | Rien |
| Index des protocoles | Oui — v1.0 (2026-09-09) | docs/PROTOCOLS/00_INDEX.md |
