# Registre des agents automatises Jules

Date de creation: 2026-04-22
Statut MVP: GO MVP, pilote client encadre
Source de prudence obligatoire: `JULES_ORIGE_BUG.md`

Ce registre sert de memoire operationnelle pour Codex, Jules et les autres intervenants.
Il resume les agents automatises autorises, leur cadence, leur perimetre et les signaux qui doivent declencher une modification de prompt.

## Regle commune

Tous les agents doivent proteger le MVP avant de produire de l activite.
Une journee sans PR est preferable a une PR opportuniste.

Priorite globale:

1. Stabilite de `main`
2. Securite, tenant isolation, auth et RBAC
3. Compatibilite mobile/backend
4. Clarte documentaire
5. Micro-UX utile
6. Performance seulement si bottleneck reel
7. Nettoyage seulement si evidemment sur

## Agents actifs ou proposes

| Agent | Role | Cadence conseillee | Peut corriger seul | Ne doit jamais faire |
|---|---|---:|---|---|
| Guardian | Triage des PR ouvertes | Quotidien 06:05 | Changelog, conflit trivial, note doc minime | Merger, supprimer branche, accepter PR rouge |
| Bolt | Performance ciblee | Opportuniste / max quotidien | Petite optimisation mesuree et reversible | Optimiser sans bottleneck, changer architecture/dependances |
| Palette | Micro-UX/accessibilite | Opportuniste / max quotidien | Tooltips, Semantics, textes utiles, etats loading/disabled | Redesign, nouveaux tokens, logique backend |
| Sentinel | Securite | Opportuniste / max quotidien | Test/fix securite petit et couvert | Exposer details exploit, changer auth/RBAC sans tests |
| DocKeeper | Drift documentaire | 2 fois/semaine | Correction factuelle petite | Inventer roadmap/features, supprimer docs |
| Contractor | Contrats API/mobile | 2 fois/semaine ou apres PR API/mobile | Test de contrat ou parser mobile backward-compatible | Changer shape API sans validation |
| Scout | Tests MVP manquants | 1 fois/semaine | Un test de regression comportement existant | Modifier production sans accord |
| Janitor | Hygiene repo | 1 fois/semaine | Nettoyage parasite evident | Supprimer branches/docs/tests/migrations sans accord |

## Prompts donnes au proprietaire

Les prompts complets ont ete fournis dans la conversation projet pour:

- Guardian
- Bolt
- Palette
- Sentinel
- DocKeeper
- Contractor
- Scout
- Janitor

Si un prompt doit etre reconstruit, utiliser ce registre + `JULES_ORIGE_BUG.md` comme source de verite.

## Quand modifier un prompt

Modifier le prompt d un agent si un de ces signaux apparait:

- L agent cree une PR alors que le rapport "rien de sur aujourd hui" aurait ete plus adapte.
- L agent oublie `CHANGELOG.md` pour un scope critique.
- L agent modifie des fichiers generes ou parasites sans justification.
- L agent touche auth, RBAC, tenant isolation, invitation, biometrie ou estimation sans test.
- L agent propose une feature apres GO MVP sans validation humaine.
- L agent confond les commandes du repo avec des exemples generiques comme `pnpm`.
- L agent cree `.Jules/` au lieu de `.jules/`.
- L agent publie trop de details securite dans une PR publique.
- L agent melange plusieurs sujets dans une seule branche.

## Comment ajuster un agent

1. Lire le diff de la PR fautive.
2. Identifier la regle violee.
3. Ajouter une phrase stricte au prompt, pas un long paragraphe vague.
4. Si la regle concerne tous les agents, mettre a jour `JULES_ORIGE_BUG.md`.
5. Si la regle concerne une cadence ou responsabilite, mettre a jour ce registre.
6. Creer une PR documentaire petite, avec `CHANGELOG.md`.

## Decision de merge des PR agents

Une PR issue d agent automatise est mergeable seulement si:

- Elle est mono-sujet.
- Elle n a pas de conflit.
- Tous les checks requis sont verts.
- Elle respecte le GO MVP.
- Elle contient `CHANGELOG.md` si le scope est critique.
- Le rollback est evident.
- Elle ne deplace pas le centre de gravite du MVP vers de nouvelles features.

Si une seule condition manque, la PR reste en attente.
