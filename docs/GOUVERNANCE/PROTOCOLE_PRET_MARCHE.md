# Protocole P1 — Prêt marché : valider un livrable avant mise sur le marché

| | |
|---|---|
| **Statut** | PROPOSÉ (2026-09-09) — à valider par le PM |
| **Dernière revue** | 2026-09-09 |
| **Owner** | PM / chef de projet (décision finale) |
| **Périmètre** | Toute surface ou verticale destinée au marché : API, admin-dashboard, web vitrine, web-offline, apps mobiles, clients desktop, kiosk, edge |
| **Dépend de** | `docs/validation/RELEASE_READINESS_GATE.md`, `docs/RELEASE_PROCESS.md`, `docs/DEPLOYMENT_PRODUCTION.md`, gates pilotes (`dev-hub/tools/pilot-gates.json`) |
| **Produit** | `docs/validation/PRET_MARCHE_REPORT_YYYY_MM_DD.md` |

---

## 1. Objet

Dire **« ce livrable est prêt à être mis sur le marché »** ne peut reposer ni sur une intuition,
ni sur une seule porte technique. Ce protocole consolide en **un artefact de décision unique**
les preuves existantes (gate 26 checks, CI, recettes, audits) et ajoute ce qui manque :
un **comité de release tracé**, des **critères par verticale**, et un **gabarit de rapport de conformité**.

Il ne duplique pas les outils en place : il les convoque.

## 2. Définition : qu'est-ce qu'un livrable « prêt marché » ?

Un livrable est déclaré prêt marché quand **les quatre blocs suivants sont simultanément verts**,
preuves à l'appui :

| Bloc | Question | Preuves attendues |
|------|----------|-------------------|
| **A. Produit** | Le besoin marché/verticale est couvert et recetté par un humain | Parcours critiques recettés (pilote ou PM), issues de recette fermées, positionnement & discours à jour (P3) |
| **B. Technique** | Le code est stable, testé, conforme aux conventions | Gate strict 26/26 rejoué en local sur le commit (`RELEASE_READINESS_GATE.md`) **+** 5 checks requis verts sur le tag (release.yml/deploy-prod.yml), coverage ≥ seuil, PHPStan L8, e2e, smoke |
| **C. Ops & infra** | L'environnement cible est prêt, observable, sauvegardé | Checklist `DEPLOYMENT_PRODUCTION.md` complétée, monitoring/alertes actifs, secrets inventoriés (`docs/CI_CD_SECRETS.md`), topologie dev/prod à jour (P7) |
| **D. Légal, sécurité & données** | Pas de blocage RGPD, sécu, licence | Registre RGPD (`docs/RGPD_REGISTRE_TRAITEMENTS.md`) à jour, scans sécu verts (CodeQL, ZAP, secret scan), dépendances auditées |

Un bloc orange (partiel) est possible uniquement en **Go conditionnel**, avec conditions écrites,
échéance et responsable. Tout bloc rouge = **No-Go**.

## 3. Niveaux de validation selon la cible

| Cible | Niveau exigé | Notes |
|-------|--------------|-------|
| Pilote client accompagné (recette terrain) | **N1 — Recette pilote** | Gates pilotes (`pilot-gates.json` + `check-pilot-gates.sh`) : recette, runbook, security_review. SLA bugs pilotes #5155. |
| Mise en production générale d'une surface existante | **N2 — Release marché** | N1 si pilotes actifs + bloc B complet + comité de release |
| Lancement d'une **verticale** nouvelle (app, module, desktop) | **N3 — Lancement verticale** | N2 + validations spécifiques à la verticale (ex. P6 desktop : signature, notarisation, matrice OS) |
| Lancement commercial multi-clients | **N4 — Go to market** | N3 + `docs/GOTO_MARKET/` complet, vitrine synchronisée (P3), support & SLA définis |

Le protocole **s'applique par surface et par verticale** : « le marché » n'est pas un bloc monolithique —
`employee` mobile peut être N2 pendant que `accounting` desktop est encore en N0 (non éligible).

## 4. Processus avant chaque mise sur le marché

```text
J-10   Geler le périmètre de la release (FREEZE_SCOPE_60J si pertinent)
J-10   Constituer le dossier de preuves (blocs A-D) dans une issue "release vX.Y.Z"
J-7    RC : CI complète verte sur la branche/lot de release + recette sur l'environnement de validation
       (note : les tags pre-release `-rc.N` ne sont pas supportés par release.yml aujourd'hui —
       extension à arbitrer avec le lead technique)
J-7→J-1 Fenêtre de recette : parcours critiques par verticale (checklist §5),
       tests on-device/desktop si concerné, audit vitrine (P3)
J-2    Comité de release (§6) → décision Go / Go conditionnel / No-Go
J-1    Artefact PRET_MARCHE_REPORT + communication (CHANGELOG, vitrine)
J0     Tag vX.Y.Z → deploy-prod (release.yml) ; diffusion mobile/desktop selon P6
J+7    Revue post-release : incidents, leçons → issues capitalisation (P4)
```

Le **comité de release** se réunit à chaque N2+ ou sur décision du PM. Pour N1 (pilote), la
décision reste celle du PM après passage des gates pilotes.

## 5. Checklist de recette par verticale (parcours critiques)

À adapter dans le rapport ; la liste suivante est le socle minimal.

- **API** : smoke par profil (`EMPLOYEE_TERRAIN`, `PAYROLL_FINANCE`, … — cf. `docs/validation/*_API_SMOKE_*`),
  contrats OpenAPI à jour, migrations versionnées sans collision (#1962).
- **Web vitrine / marketing** : parcours landing → inscription/démo ; liens vivants ; SEO/lighthouse ;
  discours aligné positionnement canonique (P3).
- **Admin-dashboard** : parcours administrateur (tenants, RBAC) via e2e isolé (#4101) ; ZAP.
- **Mobile** : pass **on-device** (matrice Android/iOS réelle — la CI seule ne couvre pas l'on-device ;
  cf. `docs/validation/PILOT_RELEASE_GO_NOGO_CHECKLIST.md` et `MOBILE_RELEASE_DEVICE_QA_2026_06_01.md` ;
  risque P1 historique : apps qui ne compilaient pas, issue #3952) ; mises à jour Firebase App Distribution
  recettées par les testeurs ; pointage GPS/géofence si verticale terrain.
- **Desktop (quand éligible, P6)** : matrice OS (Windows 10/11, macOS 13+), installation/mise à jour,
  offline, impression locale, signature/notarisation vérifiées.
- **Kiosk / edge** : scénario offline-first, reprise de connexion, cycle complet de pointage.
- **Sécurité transverse** : OWASP ZAP baseline vert, CodeQL/Semgrep, secret scan, dépendances (composer audit), registre RGPD à jour.

Chaque ligne de recette est tracée : `recetté le / par / preuve (capture, vidéo, rapport, issue)`.

## 6. Comité de release

- **Présent** : PM/chef de projet (décide), lead technique, QA (ou agent QA ayant conduit les sessions),
  owner sécurité si changement sensible, owner verticale concernée.
- **Ordre du jour** : dossier de preuves → écarts → conditions → décision → actions.
- **Décisions possibles** : `Go` · `Go conditionnel` (conditions datées) · `No-Go` (raisons + prochaine revue) ·
  `No-Go produit` (le besoin n'est pas mûr — retour au produit, pas au code).
- **Trace** : la décision est consignée dans le `PRET_MARCHE_REPORT` ; le compte rendu du comité
  (30 min max) est ajouté à l'issue de release. Pas de comité = pas de Go marché.

## 7. Gabarit du rapport `PRET_MARCHE_REPORT_YYYY_MM_DD.md`

```markdown
# Prêt marché — <Surface / Verticale> — v<X.Y.Z> — <AAAA-MM-JJ>

Décision : GO / GO CONDITIONNEL / NO-GO / NO-GO PRODUIT
Décideur : <PM>   Date du comité : <...>

## Bloc A — Produit          🟢 / 🟡 / 🔴
<parcours recettés, par qui, preuve>
## Bloc B — Technique        🟢 / 🟡 / 🔴
<gate strict 26/26 rejoué en local sur le commit ; 5 checks requis verts sur le tag ; coverage, e2e, smoke, lien run CI>
## Bloc C — Ops & infra      🟢 / 🟡 / 🔴
<environnement, monitoring, secrets, topologie P7 à jour>
## Bloc D — Légal/sécu       🟢 / 🟡 / 🔴
<RGPD, scans, dépendances>
## Conditions (si GO conditionnel)
- [ ] <condition> — échéance <date> — responsable <rôle>
## Actions post-release
- [ ] <revue J+7, leçons P4>
```

Emplacement : `docs/validation/PRET_MARCHE_REPORT_YYYY_MM_DD.md` (convention retenue : dates en `YYYY_MM_DD`).

## 8. Tests exigés pour maintenir l'éligibilité « marché »

En continu (chaque PR/push main), la **non-régression** est assurée par : `tests.yml`, `coverage-gate.yml`
(seuil `BACKEND_COVERAGE_MIN`), `mobile-apps-ci.yml` (flutter analyze/test), `e2e-isolated.yml`,
`onboarding-smoke.yml`, `secret-scan.yml`, `codeql.yml`, `actionlint.yml`, `openapi-ci.yml`.
Sur la **release** (`release.yml`/`deploy-prod.yml`), 5 checks sont re-vérifiés sur le commit du tag.

Les **lacunes connues** (audit 360 2026-08-15, sessions QA) doivent être résorbées avant qu'une
verticale ne passe N2 : pass mobile on-device, kiosk sous CI dédiée, e2e « staging » qui tourne
aujourd'hui contre la prod (incohérence — voir P7), environnement de staging réel.

## 9. Rôles

| Rôle | Responsabilité |
|------|----------------|
| PM / chef de projet | Décision finale, convocation du comité, arbitrage produit |
| Lead technique | Garant du bloc B, revue de la topologie (P7) |
| QA (humain ou agent) | Sessions de recette, constitution du dossier de preuves, rapport |
| Security owner | Bloc D, levée des advisories |
| Agents | Préparent preuves et recommandations — ne décident jamais seuls (P2, P4) |

## 10. Indicateurs

- Score de readiness par surface (gate) — tendance mensuelle.
- Nombre de releases passées en Go sans condition vs avec conditions.
- Délai constat (recette/audit) → issue → correction.
- Incidents J+7 post-release (cible : 0 P1/P2).
