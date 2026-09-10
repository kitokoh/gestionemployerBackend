# Feature Specification: Mobile manager — écran « Assistant » (texte + voix → commande)

**Feature Branch**: `feat/6860-mobile-assistant` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6860 (mobile, Agent-Ready, P2)

## Contexte

Backend prêt (C1 + A2) : les managers doivent pouvoir donner un ordre depuis
`leopardo_manager` (texte ou voix FR) et voir la réponse/confirmation. La liste
canonique des apps est `front/mobile_apps/README.md`.

## Portée v1

- Écran `Assistant` dans `leopardo_manager` (pattern `screens/` + `providers/` existant).
- Champ texte + bouton micro ; enregistrement audio → `POST /ai/voice/transcribe` (A2).
- Conversation (messages utilisateur / réponses assistant) + **confirmations d'action**
  (approuver / refuser) rendues explicitement.
- i18n FR via le catalogue existant (`shared/i18n/locales/fr.json`).

## Hors périmètre

- Voix hors FR ; wake-word ; exécution d'actions non confirmables.
- Modification des contrats A2/C1 (consommateur uniquement).

## User Stories & scénarios d'acceptation

### US1 — Commander par texte (P2)
En tant que manager, je tape une commande et j'obtiens la réponse ou une
confirmation d'action.
1. Given l'écran Assistant ouvert, When je saisis « combien de pointages en retard cette semaine ? », Then la réponse est affichée dans la conversation.
2. Given une commande nécessitant une action sensible, When l'assistant la propose, Then une carte de confirmation (approuver/refuser) est visible et exploitable.

### US2 — Commander par la voix (P2)
1. Given le micro autorisé, When j'enregistre une phrase FR, Then le texte transcrit apparaît dans la conversation puis la réponse suit.
2. Given une erreur réseau ou un STT indisponible (503), When je valide, Then un message d'erreur clair est affiché (pas de crash, pas de spinner infini).

## Exigences techniques (opposables)

- Gardes mobiles existantes : pas de routes manager dans `employee`, `StartupGate`
  premier widget, mocks éventuels en `*mock*.dart`, `requestWithRetry` /
  `extractDataList|Map`.
- Permissions micro demandées au bon moment (pas au démarrage).
- Tests widget pour l'écran + tests provider pour les états (idle/loading/error).

## Critères d'acceptation (DoD)

- [ ] Parcours texte **et** voix → réponse/action, confirmation visible.
- [ ] Erreurs réseau/503 STT affichées proprement.
- [ ] Tests widget verts ; CI mobile verte ; i18n FR complète.
