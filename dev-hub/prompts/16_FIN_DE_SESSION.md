# 16 — Contrat de sortie / rapport de fin de session

> Protocole P02 (docs/PROTOCOLES/P02_ONBOARDING_AGENT.md) — à exécuter en fin de **chaque** session agent.

## Quand l'utiliser
À la fin de toute session (travail terminé, interruption, handoff, /clear imminent).

## Instructions
1. **Bilan** : lister en 5 lignes max ce qui a été fait (issues traitées, PRs mergées, décisions).
2. **État** : préciser pour chaque artefact : `fait` / `en cours (où s'arrête-t-on)` / `bloqué (par quoi)`.
3. **Risques & dettes** : problèmes rencontrés, hypothèses non vérifiées, CI rouge connue.
4. **Prochaines actions** : les 3 prochaines étapes recommandées, avec numéros d'issues si pertinent.
5. **Hygiène** : branches locales supprimées ? stashes listés ? token de session révoqué (si fourni) ? fichiers sensibles non persistés ?

## Format
`docs/qa/` ou commentaire final sur l'issue de rattachement — titre : `Fin de session YYYY-MM-DD — <agent/scope>`.
