# Checklist Session 0 — agent Leopardo RH (versionnée)

> Protocole P02 (§7, issue #7060). Obligatoire au début de chaque session agent
> (nouvel agent ou reprise). Cochez au fur et à mesure ; en cas d'échec d'une
> étape, signalez-le dans le rapport de fin de session (prompt 16) avant de
> travailler.

## 1. Lecture obligatoire (gouvernance)
- [ ] `AGENTS.md` (racine) — règles de travail, anti-doublon #2400, protocole branches/PR
- [ ] `dev-hub/prompts/00_AGENT_QUICK_CARD.md` (carte rapide, 2 min)
- [ ] `.specify/constitution.md` (loi fondamentale, Spec-Driven Development)
- [ ] `docs/REFERENTIEL_PRODUIT/` (APV, ROADMAP, STATUTS) si le travail touche le produit
- [ ] `docs/GOUVERNANCE/LEÇONS.md` (bibliothèque des erreurs centralisée, si applicable)
- [ ] `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` (périmètre autorisé) si nouvelle feature

## 2. Synchronisation & verrous (anti-doublon)
- [ ] `git fetch origin main` — travailler depuis `origin/main` à jour
- [ ] Vérifier qu'aucune branche n'existe déjà pour mon issue :
      `gh api repos/kitokoh/leopardo-hr/branches | grep -i <issue>` (+ `gh pr list`)
- [ ] S'assigner l'issue (ou annoncer la prise en charge)
- [ ] Créer sa branche de travail (une par issue, ou une par lot de BC — protocole)

## 3. Environnement local
- [ ] Clone/dépendances OK (composer/npm/melos selon la surface)
- [ ] Tests pertinents exécutables (cible : les 4 checks requis de la protection)
- [ ] Gardes locales connues passées (migrations, lint, PHPStan sur le diff)

## 4. Contrat de sortie (fin de session)
- [ ] Rapport de fin de session rédigé avec le template `16_SESSION_REPORT.md`
- [ ] Branche poussée + PR avec `Closes #N` dans le body (jamais de merge rouge)
- [ ] Token de session révoqué (si un token temporaire a été fourni)
- [ ] Journal de session (`memory/<date>.md`) à jour
