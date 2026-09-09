# Protocole P4 — Capitalisation de l'expérience : du constat à l'issue, de la leçon à la garde

| | |
|---|---|
| **Statut** | PROPOSÉ (2026-09-09) — à valider par le PM |
| **Dernière revue** | 2026-09-09 |
| **Owner** | PM ; chaque agent est contributeur obligatoire |
| **Périmètre** | Toute expérience vécue dans le projet (dev, QA, recette, incident, intégration) → issues/tâches exploitables + connaissance réutilisable |
| **Dépend de** | `AGENTS.md` (leçons), `dev-hub/prompts/04_CREATE_ISSUES.md`, templates issues `.github/ISSUE_TEMPLATE/`, P2 (rétro d'intégration) |
| **Produit** | `docs/GOUVERNANCE/REGISTRE_LECONS.md` (index leçons ↔ issues ↔ gardes) |

---

## 1. Objet

Aujourd'hui la conversion d'un constat en issue est **manuelle et facultative** (prompt 04), les leçons
s'accumulent en format libre dans `AGENTS.md`, et il n'existe aucun type d'issue pour un **constat interne**.
Ce protocole ferme la boucle :

```text
constat (dev/QA/incident/rétro)
   → issue « constat interne » (voie standard, critères d'acceptation)
   → implémentation directe OU délégation (Agent-Ready)
   → PR mergée (1 issue = 1 branche = 1 PR)
   → leçon capitalisée (AGENTS.md / REGISTRE_LECONS) avec lien bidirectionnel issue ↔ leçon
   → si récurrent : promotion en garde CI ou en règle de protocole
```

## 2. Le type d'issue « Constat interne / Amélioration »

Nouveau template à ajouter dans `.github/ISSUE_TEMPLATE/` (ex. `constat_interne.yml`), label automatique
`capitalisation` (+ label BC quand connu). Gabarit :

```markdown
---
name: Constat interne / Amélioration
about: Expérience vécue dans le projet à transformer en action ou en enseignement
labels: ["capitalisation"]
---
### Constat (quoi, où, quand)
### Impact (temps perdu, risque, coût, répétition ?)
- [ ] Cet événement s'est déjà produit (nb occurrences : …)
### Proposition (issue, règle, garde CI, doc)
### Critères d'acceptation (si implémentation)
### Prêt pour un autre agent ? (DoR P2 complétée → label Agent-Ready)
```

## 3. Déclencheurs obligatoires (quand créer une issue `capitalisation`)

| Événement | Délai | Responsable |
|-----------|-------|-------------|
| Session QA/audit terminée : **chaque constat listé doit avoir son issue fille** (section « Issues filles » dans le rapport) | 48 h | Agent QA |
| Tâche ayant pris > 2× l'estimation | 48 h | Agent concerné |
| PR refusée/rework > 2 cycles de review | 24 h | Auteur + reviewer |
| Échec CI récurrent ou flaky (≥ 2 fois) | 48 h | Agent qui l'observe |
| Incident ou quasi-incident (prod, données, sécu) | 24 h | Témoin — lien avec `docs/ops/INCIDENTS.md` |
| Doc lue fausse/obsolète pendant un travail | Immédiat | Tout agent |
| Rétro d'intégration (P2), rétro pilote, rétro mensuelle | 48 h après la rétro | Animateur de la rétro |

Un rapport (QA, audit, rétro) qui ne produit **aucune issue fille** doit le dire explicitement
(« aucun constat ») — le silence n'est pas une sortie.

## 4. Implémentation ou délégation — le choix de l'agent expérimenté

L'agent qui crée l'issue choisit l'un des trois chemins, **toujours dans les règles** (1 issue = 1 branche = 1 PR, #2400) :

1. **Implémenter directement** : branche `fix/<issue>-<slug>`, PR avec `Closes #N` dans le body.
2. **Déléguer à un autre agent** : compléter la DoR (P2 §5), poser `Agent-Ready` (+ `good first issue`
   si accessible), laisser l'issue non assignée.
3. **Ne pas coder mais capitaliser** : la leçon est écrite (AGENTS.md ou REGISTRE_LECONS) et l'issue
   sert de trace de décision (peut être fermée par la PR qui ajoute la garde ou la doc).

Règle de fond : **l'enseignement tiré d'une petite expérience doit servir réellement** — une issue créée
et laissée sans suite est un échec du protocole ; le suivi appartient au PM via le registre.

## 5. De la leçon à la garde (promotion)

Une leçon devient **garde CI bloquante** (script `dev-hub/tools/` + workflow) quand :

- elle s'est reproduite ≥ 2 fois (ou a coûté un incident) ;
- elle est **testable par une machine** (sinon → règle écrite dans AGENTS.md/CONVENTIONS/protocole) ;
- le coût d'implémentation de la garde est inférieur au coût déjà encouru.

Exemples déjà en place à imiter : `check-pr-closes-issue`, garde collisions de migrations (#1962),
garde ghost close (#4816), `pr-issue-guard.yml` (#5442), `merge-quota-guard.yml` (#5634),
`fix-feat-ratio-guard.yml`. La PR qui ajoute une garde ferme l'issue `capitalisation` source.

## 6. Registre des leçons — index bidirectionnel

`docs/GOUVERNANCE/REGISTRE_LECONS.md` (maintenu mensuellement, ou à chaque leçon majeure) :

| Date | Leçon (1 ligne) | Issue source | Garde / règle produite | Fichier impacté |
|------|-----------------|--------------|------------------------|-----------------|
| 2026-08-16 | Famine du pipeline de déploiement | #3545 | Règle capacité CI | `AGENTS.md` |
| 2026-09-08 | Gate deploy dev aveugle | #6834/#6973 | Vérifier l'état réel | `AGENTS.md` |
| … | | | | |

Conventions :
- une leçon dans `AGENTS.md` **référence son issue** (et inversement, l'issue peut référencer la leçon) ;
- pas de doublon : chercher dans le registre avant d'écrire une leçon (dédoublonnage = fusion + occurrence) ;
- une leçon qui change une règle de travail est **répercutée dans le protocole concerné** (P1-P7) lors de la revue mensuelle.

## 7. Rétro mensuelle des agents

Chaque dernier jour ouvré du mois (même fenêtre que P3/P5/P7) : rétro de 30-45 min animée par le PM ou un agent tournant.

- Template : (1) ce qui nous a ralentis, (2) ce qui nous a accélérés, (3) top 3 actions concrètes,
  (4) santé du socle de protocoles (P1-P7) : obsolète ? trop lourd ? trou ?
- Sortie obligatoire : issues `capitalisation` créées sous 48 h ; compte rendu horodaté dans `docs/notes/`
  ou `docs/GESTION_PROJET/` (format `RETRO_AGENTS_YYYY-MM-DD.md`).
- La rétro des pilotes clients (#5157) alimente le même flux : douleurs pilotes → issues.

**Bilan hebdomadaire (vendredi, 15 min)** : chaque agent note 1-2 constats/leçons de la semaine
(ou « RAS ») ; le PM ou un agent tournant convertit les constats en issues `capitalisation` sous 48 h.
Trace minimale : note `docs/notes/BILAN_SEMAINE_YYYY-MM-DD.md`.

## 8. Rôles

| Rôle | Responsabilité |
|------|----------------|
| Tout agent | Déclencheurs §3 ; écrit ses leçons ; référence ses issues |
| Agent QA | Issues filles de chaque session/audit |
| PM | Garant du registre, du suivi des issues `capitalisation`, arbitre les promotions en garde |
| Mainteneur | Revue des PR capitalisation (souvent petites : doc, garde, test) |

## 9. Indicateurs (le flux d'apprentissage, pas seulement la dette)

- Leçons/mois et % transformées en garde ou règle (cible : ≥ 50 %).
- Délai constat → issue (cible : ≤ 48 h) et issue → résolution (cible : ≤ 10 j ouvrés).
- Nombre d'issues `capitalisation` fermées par mois (cible : ≥ 80 % de celles créées).
- Récurrence d'erreurs « connues » (cible : 0 — le registre existe pour ça).
