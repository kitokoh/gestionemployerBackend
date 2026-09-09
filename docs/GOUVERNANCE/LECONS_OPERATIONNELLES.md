# LEÇONS OPÉRATIONNELLES — la bibliothèque des erreurs

> Créé le 2026-09-09 (issue #7069). Source unique consolidée des pièges historiques du
> projet Leopardo RH. **Toute nouvelle leçon est ajoutée ici** via le flux RETEX
> (`docs/PROTOCOLES/04_CAPITALISATION_RETEX.md`, règle RET-1) — jamais seulement en
> commentaire.
> Usage : à lire **avant** la première PR (protocole onboarding, OB-3) ; à citer par son
> `L-XX` dans les issues/PR/revues.

## Légende

| Colonne | Sens |
|---|---|
| Classe | correction / optimisation / dette / processus / design / vitrine / infra |
| Symptôme | ce qui se passe quand le piège se déclenche |
| Règle / garde | comportement imposé + moyen de vérification (script, CI, doc) |
| Source | où l'expérience est documentée (issue historique, doc, commit) |

## Les leçons consolidées

| ID | Classe | Leçon (titre) | Symptôme | Règle / garde | Source |
|---|---|---|---|---|---|
| L-01 | processus | **Deux agents ne travaillent jamais sur la même issue** | PRs doublons sur une issue (#2333 ×3, #2329 ×2, #2264 ×2…) | Vérifier **toutes les branches** (`gh api …/branches`), pas seulement les PR ; claim marker immédiat ; 1 branche `fix/<issue>-*` par issue ; lot BC = 1 branche `bc/<bc>-<slug>` | AGENTS.md « règle anti-doublon » (#2400), `docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md` |
| L-02 | processus | **Une issue ne se ferme que par la preuve** | Issues fermées « pour faire propre » sans correctif (vague #4690/#4687/#4688/#4305/#4410) | Fermeture par merge (`Closes #N` dans le body) ou commentaire motivé (`wontfix`/`superseded`) | AGENTS.md « ghost close » (#4816), garde `check-issues-closed-without-merge.sh` |
| L-03 | processus | **`Closes #N` dans le body de la PR, pas le titre** | Issue référencée mais jamais fermée au merge | `Closes #N`/`Fixes #N` dans le **body** ; garde de détection post-merge | AGENTS.md (#2512), `check-issues-left-open-by-merged-prs.sh` |
| L-04 | correction | **Collision de préfixe de migrations** | Deux migrations avec le même `YYYY_MM_DD_0000NN` → `main` rouge pour toutes les PR | Vérifier AVANT push avec `check-migration-basename-collisions.sh` ; renuméroter en gardant l'ordre chronologique | AGENTS.md (#1962, 3 occurrences 2026-08-24) |
| L-05 | correction | **Test « vert » qui ne vérifie rien** (`PendingCommand` lazy) | `$cmd = $this->artisan('x')` sans `run()` → assertions DB exécutées avant la commande | `run()` explicite avant toute assertion d'état ; pattern chaîné réservé au cas sans assertion d'état | `docs/GESTION_PROJET/CONVENTIONS_TESTS.md` (A-1, #1679) |
| L-06 | correction | **Migrations tenant silencieusement sautées** | `Schema::hasTable()` interroge `current_schema()` seul → backfill/ALTER sauté (bug F-17) | Résoudre le schéma via `current_schemas(false)` (pattern `resolveTableSchema()`), préfixer les ALTER | AGENTS.md (#1613/#1595), migrations `2026_08_09_*` |
| L-07 | dette | **Code dupliqué entre apps mobiles** | 13 fichiers repositories byte-identiques entre employee/manager/hr → correctifs à répéter (mojibake corrigé séparément) | Toute évolution partagée va dans `leopardo_core` ; chantier de résorption suivi (#2661) | `front/mobile_apps/README.md` |
| L-08 | design | **Couleurs en dur hors palette** | `Color(0x…)` dispersés dans les écrans → thème/dark mode incohérents | Couleur = domaine (APV L.05) ; tokens dans `AppColors` ; garde anti-hardcode CI | APV L.05/L.07, `dev-hub/tools/validate-mobile-color-tokens.ps1` (PA2-MOB-011), `COULEURS.md` |
| L-09 | correction | **Texte accentué / mojibake** | Messages avec accents mal encodés (php/dart), corrections répétées | Gardes d'encodage existantes + revue i18n ; clés ARB/`shared/i18n` plutôt que texte en dur | `check-hardcoded-accented-messages.sh`, `check-governance-mojibake*`, rapports i18n `docs/validation/` |
| L-10 | dette | **Dette i18n récurrente** | Clés manquantes entre backend/lang, ARB, JSON partagés (rapports quasi quotidiens août 2026) | Toujours ajouter les clés dans `shared/i18n/locales/{fr,en,ar,tr}` + générer (`melos run l10n`, sync backend) | `docs/validation/I18N_DEBT_REPORT_*`, `check-mobile-l10n-sync.sh` |
| L-11 | infra | **Check requis gaté par `paths:` = merge bloqué pour toujours** | PR docs ne déclenchant pas un check requis → GitHub le traite pending à vie (#1125/#1126) | Les workflows porteurs de checks **requis** n'ont pas de filtre `paths` au niveau workflow ; gater les jobs lourds non requis via `dorny/paths-filter` | `coverage-gate.yml`, `architecture-check.yml`, `mobile-apps-ci.yml` (#6928) |
| L-12 | infra | **Saturation CI** | File de runners saturée par des runs inutiles/dupliqués (branches multiples, PR docs) | Protocoles de branche par lot (BC batch), quotas de merge, jobs lourds gatés, annulation des runs PR obsolètes | `docs/infra/02_alignement/CI_SATURATION.md`, `merge-quota-guard.yml`, `ci-observability.yml` |
| L-13 | correction | **Fuite de secrets / scan** | Secrets commités ou passés en clair | `.secrets.baseline`, secret scan + historique en CI, rotation immédiate si exposition (protocole 07 ENV-4) | `SECURITY.md`, `secret-scan.yml`, `secret-history-scan.yml` |
| L-14 | processus | **Contourner une garde « pour avancer »** | Garde désactivée/contournée → régression silencieuse plus tard | Toute garde contournée = issue immédiate (RET-1) ; une garde inadaptée se modifie par PR, pas en local | sessions QA `docs/qa/`, gardes `dev-hub/tools/` |
| L-15 | processus | **Spec Kit non suivi sur du travail significatif** | Code sans spec `.specify/` → ambiguïtés, rework, doublons | Workflow Spec Kit : specify → clarify → plan → tasks → implement ; constitution = loi fondamentale | `.specify/constitution.md`, AGENTS.md |
| L-16 | dette | **Backend legacy ressuscité** | Code neuf écrit dans les anciens emplacements supprimés (`app/Models`, `Services`, `Http/Controllers/Api/V1`) | Tout nouveau code va dans `App\Modules\<Nom>\` / `App\Shared\` / `App\Core\` ; shims retirés | `api/ARCHITECTURE.md`, `docs/CONTRIBUTING_DDD.md` |
| L-17 | dette | **Docs « vivantes » qui vieillissent** | Rapports datés lus comme état courant ; docs sans date jamais mises à jour | Convention docs : vivant (sans date, à jour) vs preuve figée (datée) ; registres tenus à jour à chaque évolution | `docs/validation/README.md`, `docs/infra/README.md`, protocole 07 ENV-7 |
| L-18 | correction | **`git stash` perdus / branches orphelines** | Perte de travail local, confusion de branches | Checklist onboarding OB-1 (stash list, branches) ; jamais `git clean`/`reset --hard` aveugle | `dev-hub/prompts/14_ONBOARDING_AGENT.md` |

## Comment ajouter une leçon

1. Ouverture d'une issue `RETEX:` (template `.github/ISSUE_TEMPLATE/retex.yml`) ;
2. implémentation de la garde/correction (PR) ;
3. **mise à jour de ce fichier dans la même PR** (nouvelle ligne `L-XX`) si la leçon
   est généralisable ;
4. si la leçon touche le guide de travail, AGENTS.md est mis à jour en parallèle
   (renvoi vers la ligne `L-XX` plutôt que duplication).

## Liens

- Protocole onboarding (lecture obligatoire avant 1re PR) : `docs/PROTOCOLES/02_ONBOARDING_AGENT.md`
- Protocole capitalisation (flux RETEX) : `docs/PROTOCOLES/04_CAPITALISATION_RETEX.md`
- Guide de travail : `AGENTS.md` · Conventions de tests : `docs/GESTION_PROJET/CONVENTIONS_TESTS.md`
