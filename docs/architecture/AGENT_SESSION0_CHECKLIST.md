# Checklist Session 0 — nouvel agent (protocole P02)

Ordre de lecture obligatoire avant toute action sur le dépôt :

1. [ ] Lire `dev-hub/prompts/00_AGENT_QUICK_CARD.md` (2 min)
2. [ ] Lire `AGENTS.md` (règles complètes) puis `docs/PROTOCOLES/P02_ONBOARDING_AGENT.md`
3. [ ] `git fetch origin main && git checkout main && git pull`
4. [ ] Vérifier les stashes (`git stash list`) — ne jamais les perdre
5. [ ] Lister les branches distantes + PRs ouvertes : **le nom de branche est le verrou anti-doublon** (chercher `fix/<issue>` avant de prendre une issue)
6. [ ] Choisir une issue : non assignée, critères d'acceptation clairs (labels `Agent-Ready` / `good first issue`)
7. [ ] S'assigner (`gh issue edit <N> --add-assignee @me`) puis pousser une branche `fix/<issue>-<slug>` avec un commit vide de claim
8. [ ] Ne jamais créer de branche pour de la QA pure (livrable = issues + preuves)
9. [ ] En fin de session : exécuter `dev-hub/prompts/16_FIN_DE_SESSION.md`

## Pièges connus (à relire)
- Clone local du sandbox possiblement **filtré** → lire le code via raw.githubusercontent.com quand un fichier semble altéré.
- PR sans `Closes #N` dans le **body** = issue jamais fermée au merge.
- Vercel rouge = quota, pas un échec de build (check non requis).
