# Memo orchestration agents automatises

Date: 2026-04-22
Auteur: Codex
But: permettre a Codex futur de comprendre rapidement quels agents Jules existent, pourquoi ils existent, et quand proposer une correction.

## Situation actuelle

Le projet Leopardo RH est declare GO MVP et entre en pilote client encadre.
Le role des agents automatises n est plus de construire des features, mais de proteger le produit:

- surveiller les PR;
- corriger les petits blocages;
- verrouiller les tests;
- detecter les derives documentaires;
- ameliorer doucement UX/performance/securite seulement si c est sur.

## Documents a relire au debut d une session

1. `JULES_ORIGE_BUG.md`
2. `docs/GESTION_PROJET/CODEX/REGISTRE_AGENTS_AUTOMATISES.md`
3. `docs/GESTION_PROJET/GO_NO_GO_MVP.md`
4. `CHANGELOG.md`
5. PR ouvertes sur GitHub

## Intentions derriere les agents

### Guardian

Chef de gare des PR.
Il doit dire quoi merger, quoi corriger, quoi refuser.
S il devient trop permissif, durcir la regle "si doute, ne pas merger".

### Bolt

Performance uniquement si bottleneck reel.
S il cree des PR quotidiennes sans mesure, reduire sa cadence ou ajouter "report only".

### Palette

Accessibilite et micro-UX utile.
S il fait du redesign ou modifie les fichiers Flutter generes sans raison, renforcer les interdits.

### Sentinel

Securite et tests de regression.
S il touche auth/RBAC/tenant isolation sans test, refuser la PR et durcir son prompt.

### DocKeeper

Alignement documentaire.
S il invente du produit ou une roadmap, le ramener a la clarification factuelle.

### Contractor

Compatibilite backend/mobile.
S il change le shape API au lieu de tester l existant, refuser.

### Scout

Tests MVP manquants.
S il modifie la production pour faire passer un test, stopper et demander validation humaine.

### Janitor

Nettoyage repo.
S il supprime des branches, docs, tests, migrations ou assets sans autorisation, stopper.

## Notes de pilotage

- Les bots doivent souvent produire un rapport sans PR.
- Les PR utiles sont petites, reversibles et mono-sujet.
- Les corrections de governance les plus frequentes sont des oublis `CHANGELOG.md`.
- Les PR d agents qui touchent `api/`, `mobile/`, `docs/GESTION_PROJET/`, `.github/` ou `tools/` doivent presque toujours inclure `CHANGELOG.md`.
- La branche `main` reste protegee; travailler via PR.
- Ne pas taguer la release MVP avant merge du GO officiel dans `main`.

## Prochaine amelioration possible

Si les agents deviennent nombreux, creer un fichier par agent sous:

`docs/GESTION_PROJET/CODEX/agents/`

Pour l instant, deux fichiers suffisent dans `docs/GESTION_PROJET/CODEX/`:

- `REGISTRE_AGENTS_AUTOMATISES.md`
- `MEMO_ORCHESTRATION_AGENTS.md`
