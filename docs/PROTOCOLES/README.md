# 📜 PROTOCOLES — Cadre de gouvernance opérationnelle Leopardo RH

> **Statut :** proposition initiale (v0.1 — 2026-09-09), à ratifier en revue mensuelle.
> **Objet :** définir, de façon unique et opposable, **comment on travaille, comment on valide,
> comment on présente et comment on livre** Leopardo RH — pour les humains **et** les agents IA.
> **Position dans le dépôt :** ce dossier complète `AGENTS.md` (règles de travail quotidiennes),
> `.specify/constitution.md` (loi fondamentale) et `docs/GOUVERNANCE/` (protocoles de branche).
> Il ne les remplace pas : il les **organise** en protocoles révisables, et ajoute ce qui manque.

---

## 1. Pourquoi ce cadre

Le dépôt a accumulé une très grande richesse de règles, de gardes CI et de retours d'expérience
(voir `docs/qa/`, `docs/audits/`, `dev-hub/prompts/`, `docs/GOUVERNANCE/`). Mais ces règles sont
**dispersées** : un nouvel agent (ou le PM) ne sait pas toujours quel protocole s'applique à quel
moment, lequel est à jour, lequel a échoué par le passé, et qui doit le faire vivre.

Ce dossier répond à sept questions permanentes :

| # | Question | Protocole |
|---|---|---|
| P01 | Quand peut-on dire « prêt pour le marché » ? | [P01_VALIDATION_MARCHE](P01_VALIDATION_MARCHE.md) |
| P02 | Comment un nouvel agent démarre sans se perdre ni répéter les erreurs passées ? | [P02_ONBOARDING_AGENT](P02_ONBOARDING_AGENT.md) |
| P03 | Comment le projet est-il présenté avec les bons mots, partout, tout le temps ? | [P03_VITRINE_PRESENTATION](P03_VITRINE_PRESENTATION.md) |
| P04 | Comment créer des issues/tâches selon l'expérience, déléguer et capitaliser ? | [P04_TACHES_ISSUES_EXPERIENCE](P04_TACHES_ISSUES_EXPERIENCE.md) |
| P05 | Comment garantir un design harmonisé sur toutes les surfaces ? | [P05_DESIGN_HARMONISE](P05_DESIGN_HARMONISE.md) |
| P06 | Comment produire et tester des clients desktop (Windows/macOS) par BC vertical ? | [P06_DESKTOP_DISTRIBUTION](P06_DESKTOP_DISTRIBUTION.md) |
| P07 | Comment s'assurer en continu que l'architecture dev/prod reste saine ? | [P07_ARCHITECTURE_DEV_PROD](P07_ARCHITECTURE_DEV_PROD.md) |

Chaque protocole suit le [TEMPLATE_PROTOCOLE](TEMPLATE_PROTOCOLE.md) : objet, déclencheurs, règles,
définitions de fait, gardes, rôles, indicateurs, révision.

## 2. Principes de non-duplication

1. **Une règle = un propriétaire.** Si une règle existe déjà (ex. anti-doublon #2400 dans
   `AGENTS.md`, constitution §I), le protocole **pointe vers elle** au lieu de la recopier.
   Toute contradiction entre protocoles est un bug à corriger dans la revue mensuelle.
2. **Sources de vérité (dans l'ordre) :** `.specify/constitution.md` > `AGENTS.md` > ce dossier >
   `docs/GOUVERNANCE/` > `docs/ops/` > tout le reste. En cas de conflit, la source la plus haute
   gagne ; signaler le conflit, ne pas choisir en silence.
3. **Un protocole n'est utile que s'il est exécutable.** Chaque règle cite les fichiers, commandes,
   workflows ou issues qui la rendent vérifiable.
4. **Le code prime sur le document** : si un garde CI contredit un protocole, le garde fait foi et
   le protocole doit être mis à jour (constat récurrent — cf. dérive `render.yaml` corrigée #6831).

## 3. Cycle de vie d'un protocole

```text
[Besoin constaté] → [Projet de protocole (TEMPLATE)] → [Revue mensuelle] → [Ratification]
      → [Application + gardes] → [Leçons apprises] → [Révision mensuelle ou déclenchée]
```

- **Création / modification :** toute PR modifiant un fichier de ce dossier est une PR `docs/`
  standard (branche `docs/<numero>-slug`, `Closes #N`, entrée CHANGELOG sous `[Unreleased]`).
  Elle doit lister les fichiers existants impactés (AGENTS.md, quick card…) à mettre à jour en miroir.
- **Déclencheurs de révision hors cycle :** incident P1, échec répété d'un garde, changement de
  topologie dev/prod, nouvelle surface de distribution (ex. premier client desktop), retour pilote
  majeur, arrivée d'un canal de présentation inédit.

## 4. Rituel mensuel — « Revue des protocoles » (dernier jour ouvré du mois)

> Demande explicite du PM : les protocoles doivent pouvoir être **redéfinis chaque fin de mois**.

1. **Préparation (gardien technique, J-3) :** collecter depuis `docs/qa/`, `docs/audits/`,
   `docs/ops/INCIDENTS.md`, issues fermées du mois, retours pilotes (`docs/pilotes/`, `docs/ops/`),
   les leçons qui méritent de durcir ou assouplir un protocole.
2. **Revue (PM + gardien technique, ~1 h) :** parcourir P01→P07. Pour chacun : la règle a-t-elle été
   respectée ? a-t-elle coûté plus qu'elle n'a rapporté ? existe-t-il une règle plus simple ?
   Décisions : **conserver / modifier / abroger / ajouter**.
3. **Livrables :** (a) entrées CHANGELOG pour chaque modification, (b) issues GitHub créées pour
   toute règle nécessitant du code (garde CI, template, workflow), (c) ce README mis à jour
   (date de revue, version), (d) un compte-rendu d'une page dans `docs/GESTION_PROJET/`
   (`REVUE_PROTOCOLES_YYYY-MM.md`).
4. **Règle d'or :** la revue mensuelle ne **crée** pas de protocole dans la précipitation ; elle
   ratifie ce que l'usage a prouvé et abroge ce que l'usage a contredit.

## 5. Vocabulaire commun (à utiliser tel quel, partout)

| Terme | Définition opposable |
|---|---|
| **BC** | Bounded Context (registre `dev-hub/governance/bounded-context-registry.json`, BC-01 → BC-28) |
| **Verticale / BC vertical** | Solution métier complète sur la plateforme (Restaurant, Travel, Fuel, Edu, Delivery…) |
| **Surface** | Une expérience livrée : API, admin, vitrine, app mobile Flutter, kiosk, edge, client desktop |
| **Dev / Prod** | Deux volets d'architecture : dev = continu sur `main` ; prod = stable, déclenchée par Release taguée |
| **Agent** | Tout exécutant, humain ou IA (le dépôt parle indifféremment des deux) |
| **DoR / DoD / DoMarket** | Definition of Ready / of Done / of Marketable (définies P01 et P04) |
| **Spec** | Fichier `.specify/features/<id>/spec.md` produit par Spec Kit avant implémentation |
| **Garde** | Script de `dev-hub/tools/` ou workflow `.github/workflows/` qui rend une règle vérifiable en CI |

## 6. Miroir à tenir à jour

Toute modification d'un protocole **P0x** impose de vérifier si ces fichiers doivent bouger en miroir
(sinon la dérive documentaire recommence) :

- `AGENTS.md` (règles quotidiennes), `dev-hub/prompts/00_AGENT_QUICK_CARD.md` (interdits/obligatoires),
- `.specify/constitution.md` (loi fondamentale — uniquement si la règle est structurelle),
- `docs/ops/DOMAINS.md`, `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` (P07),
- `docs/REFERENTIEL_PRODUIT/*` (P03, P05), `docs/validation/RELEASE_READINESS_GATE.md` (P01),
- `README.md` et `CHANGELOG.md` (présentation et traçabilité).

---

**Historique**

| Version | Date | Changement |
|---|---|---|
| v0.1 | 2026-09-09 | Proposition initiale du cadre (création dossier `docs/PROTOCOLES/`) |
