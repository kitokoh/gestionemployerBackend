# Gouvernance — Protocoles Leopardo

> **Socle de protocoles** définissant les bases de fonctionnement du projet :
> validation « prêt marché », onboarding des agents, présentation/vitrine, capitalisation de l'expérience,
> harmonie du design, distribution desktop, gouvernance de l'architecture dev/prod.
>
> Rédigé le 2026-09-09 à la demande du PM. **Statut : PROPOSÉ — à valider par le PM** avant application.
> Chaque protocole est amendable via le processus décrit en fin de ce README (jamais hors processus).

---

## 1. Cartographie des protocoles

| # | Protocole | Répond à | Fichier | Statut |
|---|-----------|----------|---------|--------|
| P0 | Index & rituels | Vue d'ensemble, calendrier mensuel, amendement | `docs/GOUVERNANCE/README.md` | PROPOSÉ |
| P1 | Prêt marché | Valider par les tests que le livrable peut être mis sur le marché | `docs/GOUVERNANCE/PROTOCOLE_PRET_MARCHE.md` | PROPOSÉ |
| P2 | Onboarding agent | Un agent qui intègre le projet réalise ses tâches sans se perdre | `docs/GOUVERNANCE/PROTOCOLE_ONBOARDING_AGENT.md` | PROPOSÉ |
| P3 | Vitrine & présentation | Le projet est présentable à tout moment, avec les bons mots | `docs/GOUVERNANCE/PROTOCOLE_VITRINE_MENSUEL.md` | PROPOSÉ |
| P4 | Capitalisation de l'expérience | L'expérience de chaque dev devient issues/tâches + enseignement | `docs/GOUVERNANCE/PROTOCOLE_CAPITALISATION_EXPERIENCE.md` | PROPOSÉ |
| P5 | Harmonie du design | Une seule vérité design, toutes surfaces alignées | `docs/GOUVERNANCE/PROTOCOLE_HARMONIE_DESIGN.md` | PROPOSÉ |
| P6 | Distribution desktop | Extraire et distribuer des clients desktop (.exe / macOS) par verticale | `docs/GOUVERNANCE/PROTOCOLE_DESKTOP_DISTRIBUTION.md` | PROPOSÉ |
| P7 | Architecture dev/prod | L'architecture à deux volets reste réfléchie, documentée, sans dérive | `docs/GOUVERNANCE/PROTOCOLE_ARCHITECTURE_DEV_PROD.md` | PROPOSÉ |

Protocoles existants (non rédigés ici, ils restent la référence de leur domaine) :

| Domaine | Fichier |
|---------|---------|
| Branches par lots Bounded Context | `docs/GOUVERNANCE/BC_BATCH_BRANCH_PROTOCOL.md` |
| Branches & capacité CI programme CRM | `docs/GOUVERNANCE/CRM_BRANCH_PROTOCOL.md` |
| Freeze de scope 60 jours | `docs/GOUVERNANCE/FREEZE_SCOPE_60J.md` |
| Gate technique de release (26 checks) | `docs/validation/RELEASE_READINESS_GATE.md` |
| Processus de release & versioning | `docs/RELEASE_PROCESS.md` ⚠️ **obsolète** (API-only, déploiement « push main ») — à réécrire : découpage dev/prod par tag + surfaces non-API (cf. P7) |
| Conventions de code | `CONVENTIONS.md` |
| Règles de travail agent | `AGENTS.md` |

Le registre des leçons (P4) est tenu dans `docs/GOUVERNANCE/REGISTRE_LECONS.md`.

## 2. Rituels périodiques (calendrier)

| Quand | Rituel | Protocole | Artefact produit |
|-------|--------|-----------|------------------|
| Chaque semaine (vendredi) | Bilan de semaine (15 min) + conversion des constats en issues | P4 §7 | Issues `capitalisation` ; note `docs/notes/BILAN_SEMAINE_YYYY-MM-DD.md` |
| **Dernier jour ouvré du mois** | **Revue de fin de mois** : vitrine + design + architecture + rétro agents + protocoles | P3, P4, P5, P7 | `VITRINE_REVIEW_YYYY_MM.md`, `DESIGN_AUDIT_YYYY_MM.md`, `ARCHI_REVIEW_YYYY_MM.md`, rétro horodatée |
| Avant chaque release `vX.Y.Z` | Recette & gate prêt marché | P1 | `docs/validation/PRET_MARCHE_REPORT_YYYY_MM_DD.md` |
| Chaque release touchant l'offre | Synchronisation vitrine | P3 | PR vitrine ou issue fille |
| À l'arrivée d'un agent | Onboarding balisé | P2 | Rétro d'intégration J+10 |

Le **dernier jour ouvré du mois** est le moment où le socle lui-même est redéfini si nécessaire :
chaque protocole porte une date de « dernière revue » ; toute évolution du projet (nouveau module,
nouvelle plateforme, échec répété) doit y être reflétée le mois suivant au plus tard.

## 3. Définitions communes

- **Surface** : un livrable du projet (API, admin-dashboard, web vitrine, web-offline, app mobile,
  client desktop, kiosk, edge, site gh-pages).
- **Verticale (B2C/module)** : un métier cible extrait de la plateforme (RH, paie, comptabilité,
  pointage/terrain, marketing, CRM client, travel…) porté par une ou plusieurs surfaces.
- **Gate** : porte de validation avec critères objectifs et décision tracée
  (`Go` / `Go conditionnel` / `No-Go` / `No-Go produit` — cf. `docs/validation/RELEASE_READINESS_GATE.md`).
- **Preuve** : artefact vérifiable (rapport CI vert, capture, rapport daté, issue fermée par code).
  Une décision sans preuve est une décision non tracée.
- **Décision finale** : toujours humaine (PM / chef de projet). Un agent prépare, documente,
  recommande — il ne tranche jamais seul un `Go` marché (règle héritée des gates pilotes).

## 4. Amendement d'un protocole

1. Toute proposition de modification d'un protocole passe par une issue de type
   « Constat interne / amélioration » (P4) avec le label `capitalisation`.
2. La modification se fait par PR dédiée (`docs/<protocole>`) — revue par le PM + 1 mainteneur.
3. Le fichier porte : date de revue, nature du changement (`mineur`/`majeur`), référence de l'issue.
4. Une leçon opérationnelle issue d'un incident mettant en cause un protocole **doit** y être
   répercutée dans le mois (calendrier ci-dessus).

## 5. Liens utiles

- Index général de la documentation : `docs/README.md`
- Ordre de lecture produit/tech/ops : `docs/CONTEXT/README.md`
- Carte rapide agent : `dev-hub/prompts/00_AGENT_QUICK_CARD.md`
- Registre des bounded contexts : `dev-hub/governance/`
- État courant de l'architecture : `docs/ARCHITECTURE_STATUS.md`, `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md`
