# Protocole P2 — Onboarding d'un agent : réussir sa première tâche sans se perdre

| | |
|---|---|
| **Statut** | PROPOSÉ (2026-09-09) — à valider par le PM |
| **Dernière revue** | 2026-09-09 |
| **Owner** | PM ; tuteur désigné pour l'agent entrant |
| **Périmètre** | Tout agent (IA ou humain) qui intègre le projet, pour une première tâche jusqu'à la première PR mergée |
| **Dépend de** | `AGENTS.md`, `CONVENTIONS.md`, `docs/CONTEXT/README.md`, `dev-hub/prompts/00_AGENT_QUICK_CARD.md`, `docs/architecture/AGENT-START-HERE.md`, `docs/QUICKSTART.md`, P1, P4 |
| **Produit** | Rétro d'intégration J+10 (`docs/notes/RETRO_INTEGRATION_YYYY-MM-DD_<agent>.md`) |

---

## 1. Objet

Le temps « perdu » des agents précédents est capitalisé dans le repo (leçons datées de `AGENTS.md`,
audits, sessions QA, gardes CI). Ce protocole fait que **chaque nouvel agent consomme cette
capitalisation avant d'écrire une ligne de code** — et qu'il en restitue à son tour (P4).

Deux règles d'or :
1. **Lire avant d'agir** : le parcours de lecture §3 est obligatoire et suffisant pour ne pas se perdre.
2. **Une tâche n'est commencée que si elle est prête** (Definition of Ready, §5). Sinon, on la rend prête — on ne « bidouille pas dans le vide ».

## 2. Prérequis d'accès (checklist, à valider avec le tuteur)

- [ ] Compte GitHub avec accès en écriture à `kitokoh/leopardo-hr` + `gh` authentifié (`gh auth status`).
- [ ] Droits : assignation d'issues, création de branches, lecture des checks.
- [ ] Secrets d'environnement local disponibles via le canal prévu (jamais dans le chat, jamais en clair dans le code).
- [ ] Git LFS installé (#4124) si travail sur captures/assets.
- [ ] Outillage local : Docker, Make, Flutter/Dart (version du repo), Node (vitrine), PHP/Composer (API) — cf. `DEVELOPMENT.md` (racine) qui est la référence ; `dev-hub/DEVELOPMENT.md` est un doublon déclaré **sous-canonique** (#6601) → ne pas le suivre en cas de divergence.
- [ ] Connaissance du canal de demande de review et du canal « validation par le propriétaire » de la spec.

## 3. Parcours de lecture obligatoire (ordre — ≈ 1 h, non négociable)

| Étape | Fichier | Ce qu'on y trouve |
|-------|---------|-------------------|
| 1 | `dev-hub/prompts/00_AGENT_QUICK_CARD.md` | Carte rapide 2 min : interdits, réflexes |
| 2 | `AGENTS.md` (racine) | Règles de travail, leçons datées, cartographie des 7 apps, gardes — **à relire à chaque session** |
| 3 | `docs/CONTEXT/README.md` | Ordre de lecture produit/tech/ops |
| 4 | `docs/architecture/AGENT-START-HERE.md` | Affectation par bounded context, séquence obligatoire |
| 5 | `CONVENTIONS.md` | Conventions de code (PHP, DDD, tenant, tests, git) |
| 6 | `docs/QUICKSTART.md` | Démarrage 5 min (Docker, `leopardo:migrate` — jamais `artisan migrate` nu) |
| 7 | `dev-hub/prompts/14_ONBOARDING_AGENT.md` | Prompt d'onboarding détaillé |
| 8 | `docs/GOUVERNANCE/README.md` + P2/P4 | Le présent socle de protocoles |

Pendant la première session, garder ouvert : `AGENTS.md` + `docs/GOUVERNANCE/README.md`.

## 4. Choix de la première tâche

1. Chercher une issue **`good first issue`** ou **`Agent-Ready`**, non assignée, avec critères d'acceptation.
2. Vérifier qu'elle est rattachée à un BC (registre `dev-hub/governance/`, labels BC) et qu'elle n'est pas
   déjà couverte par une branche ouverte (règle anti-doublon #2400).
3. **S'auto-assigner** l'issue puis créer la branche `fix/<issue>-<slug>` ou `feat/<issue>-<slug>` depuis `origin/main`.
4. Marquer la branche (marker) avant de coder — la branche est un **verrou** : une issue = une branche = une PR.

## 5. Definition of Ready (DoR) — une tâche n'est « faisable » que si tout est vrai

- [ ] Contexte : pourquoi cette tâche existe (lien produit, constat, issue source).
- [ ] Critères d'acceptation **testables** (au moins 1 par comportement attendu).
- [ ] BC et labels renseignés ; estimation grossière (S/M/L) si possible.
- [ ] Dépendances identifiées (autre PR, secret, accès, décision PM).
- [ ] Surface(s) touchée(s) listée(s) (API/admin/web/mobile/desktop/docs) → checks CI requis connus.
- [ ] Risque de régression évalué (migration ? contrat API ? i18n ? design tokens ?).

Si la DoR n'est pas remplie : **ne pas commencer**. Compléter l'issue ou la passer en
« constat interne » (P4) pour qu'un agent plus expérimenté ou le PM la rende prête.

## 6. Exécution — réflexes qui évitent les erreurs historiques

| Leçon passée (source) | Réflexe |
|------------------------|---------|
| Anti-doublon #2400 | Rechercher l'issue/branche existante avant de créer quoi que ce soit |
| Collisions de migrations #1962 | Vérifier les noms de migration avant push ; garde locale obligatoire |
| Ghost close #4816 | Ne fermer une issue que PR mergée avec preuve ; `Closes #N` dans le **body** (#2512) |
| Famine du pipeline #3545 | Ne pas lancer de jobs CI redondants ; respecter la capacité CI |
| Gate deploy aveugle #6834/#6973 | Vérifier l'état réel de l'environnement avant de déclarer un déploiement vert |
| Feature nouvelle | Passer par le workflow Spec Kit (spec d'abord, `docs/specifications/`) — `.github/skills/speckit-*` ; sans Copilot, suivre le même chemin documenté dans le prompt dédié |
| Changement visuel | Captures avant/après dans la PR (template PR) |
| CHANGELOG | Toute PR mergée = entrée CHANGELOG (Keep a Changelog, #2417) |

**Commandes canoniques** (ne pas réinventer) : `make <cible>` (Makefile racine), `melos bootstrap/test/analyze`,
`flutter test`, `dart run build_runner build --delete-conflicting-outputs`, migrations via `leopardo:migrate`.

## 7. Avant d'ouvrir la PR — miroir des checks requis

- [ ] `dart analyze` / `flutter analyze` verts sur les packages touchés ; `dart format` appliqué.
- [ ] Tests unitaires/widget ajoutés ou mis à jour ; pas de test cassé.
- [ ] PHP : Pint + PHPStan strict niveau 8 ; Pest vert (backend) — si surface API.
- [ ] Contrat API/OpenAPI à jour si route modifiée (#4930 verbes, #4932 unicité).
- [ ] i18n complète (pas de chaîne en dur) si UI.
- [ ] Body de PR : `Closes #N`, catégorie, surfaces, screenshots si visuel, CHANGELOG.
- [ ] Checks requis verts (**5** — cf. `dev-hub/tools/branch-protection-canonical.json`) avant merge ; jamais d'auto-merge rouge.

## 8. Après la première PR mergée — rétro d'intégration (J+10 max)

Tout agent rend une rétro courte (`docs/notes/RETRO_INTEGRATION_YYYY-MM-DD_<agent>.md`) :
- ce qui m'a ralenti (le plus honnête possible — c'est de l'or pour P4) ;
- ce qui m'a fait gagner du temps ;
- les docs lues qui étaient fausses/obsolètes (→ issues docs) ;
- 2-3 conseils pour le prochain agent.

## 9. Pièges spécifiques aux agents IA

- Un agent IA **ne décide jamais seul** d'un Go marché (P1), d'une dérogation de protocole,
  ou d'un changement de périmètre gelé (#5147).
- Vérifier que les actions GitHub ont **réellement** tourné (l'état affiché ≠ l'état réel — #6834/#6973).
- Ne pas ouvrir de PR « opportuniste » hors périmètre (guide/garde-fou `docs/JULES_ORIGE_BUG.md` — documentaire, non automatisé).
- Signaler tout accès manquant au lieu de le contourner.

## 10. Indicateurs d'efficacité de l'onboarding

- Temps entre l'arrivée et la première PR mergée (cible : < 5 jours ouvrés).
- Nombre d'allers-retours de review sur la première PR (cible : ≤ 2).
- Taux de première PR sans violation de convention (cible : ≥ 80 %).
- Nombre de « pièges connus » re-déclenchés par des nouveaux (cible : 0 — la capitalisation doit suffire).
