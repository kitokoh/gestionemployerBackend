# LEÇONS — Bibliothèque des erreurs & pièges connus

> Tableau central des pièges historiques du dépôt (issue #7069 — corpus
> `docs/PROTOCOLES/P02_ONBOARDING_AGENT.md` + `P04_TACHES_ISSUES_EXPERIENCE.md`).
> But : qu'un nouvel agent (ou un agent qui revient) retrouve en une page les
> erreurs déjà payées — symptôme → garde → réflexe. Les leçons détaillées et
> datées restent dans `AGENTS.md` (historique) ; ce fichier est la **vue
> transversale** des pièges rejouables.
>
> Règle : toute nouvelle leçon opérationnelle = entrée `AGENTS.md` **et**, si
> c'est un piège rejouable, une ligne ici (flux RETEX, protocole P04).

## Tableau des pièges

| Piège | Symptôme | Garde / réflexe | Référence |
|---|---|---|---|
| Doublons de PR sur une même issue (2 agents en parallèle) | 2+ branches `fix/<issue>-*`, PRs dupliquées | Verrou : 1 branche = 1 issue, claim marker immédiat après self-assign ; vérifier `gh pr list` + branches AVANT de coder | AGENTS.md (protocole #2400) |
| Issue « fermée » par une PR qui ne la ferme pas | Issue reste ouverte après merge | `Closes #N` (ou Fixes/Resolves) obligatoire dans le **body** de la PR | #2512, `pr-issue-guard.yml` |
| Collision de préfixes de migrations | `main` rouge pour TOUTES les PRs | Vérifier AVANT push : `bash dev-hub/tools/check-migration-basename-collisions.sh` | #1962/#5431 |
| Migration tenant sans `company_id` | Fuite cross-tenant en test | Gardes Hygiene Guards ; toute table métier a `company_id` | AGENTS.md, CONVENTIONS.md |
| Route API ajoutée sans spec OpenAPI | PR bloquée (OpenAPI CI) | Mettre à jour `api/openapi.yaml` + rejouer `node dev-hub/tools/generate-openapi-sdk.mjs` et committer miroir/SDK | #3545/#5280, AGENTS.md |
| Rouge « Vercel » sur une PR = quota gratuit épuisé | Check Vercel rouge sans rapport avec le code | Ce n'est PAS bloquant (quota ~100/jour) ; merger sur les 5 checks requis | #4868, AGENTS.md |
| `Closes` mentionné entre parenthèses `(#1234)` ou en commentaire | Issue non fermée | Mot-clé dans le body, pas dans une parenthèse ni un commentaire | #2512 |
| Spec Kit : 2 agents implémentent la même spec | Doublons de travail | Constitution §I : une spec = un implémenteur à la fois | `.specify/constitution.md` |
| Doc d'infra modifiée sans la PR qui change l'infra | Dérive doc ↔ réalité (ex. render.yaml vs live) | Même PR = code + registre (`DOMAINS.md`, topologie) ; audit mensuel P07 | #6831, P07 |
| Copie publique avec chiffre non daté / promesse non tenue | Sur-promesse en vitrine | Vérifier `MESSAGE_MAP.md` + `METRIQUES_VITRINE.md` avant publication | #3257/#4202/#3863/#3888, P03 |
| Fichiers « core » dupliqués localement dans une app | i18n/tokens désynchronisés entre apps | Tout partage va dans `leopardo_core` ; zéro copie (convergence F-27) | P05, `front/mobile_apps/README.md` |

## Où vivent les leçons

- **Historique daté et détaillé** : `AGENTS.md` (sections « Leçon ») + `CHANGELOG.md`.
- **Vue transversale** : ce fichier (pièges rejouables).
- **Nouvelles leçons** : issue `[REX]` (template `retour_experience.yml`), puis
  consolidation ici / AGENTS.md selon le flux P04.
