# RETEX_FLUX — Le flux « constat → issue → leçon » (protocole P04)

> Guide pas-à-pas créé le 2026-09-09 (issue #7111). Références : corpus
> `docs/PROTOCOLES/P04_TACHES_ISSUES_EXPERIENCE.md`, template d'issue
> `.github/ISSUE_TEMPLATE/constat_lecon.yml`, bibliothèque des erreurs
> `docs/GESTION_PROJET/BIBLIOTHEQUE_ERREURS.md`.

## Pourquoi ce flux

Toute expérience vécue dans le projet (dev, QA, recette, incident, intégration) contient
un enseignement. S'il reste dans la tête de celui qui l'a vécue, un autre agent la
revivra. Le flux ci-dessous le transforme en **action** (issue) puis en **mémoire**
(leçon consolidée) — c'est la boucle P04.

## Le flux en 5 étapes

```
1. CONSTATER   → je documente l'expérience (quoi, quand, contexte)
2. ISSUE       → j'ouvre une issue [LECON] (template constat_lecon.yml)
3. ARBITRER    → j'implémente directement OU je délègue (seuils ci-dessous)
4. CORRIGER    → PR avec "Closes #N" dans le body (#2512)
5. CONSOLIDER  → leçon dans AGENTS.md (règle opérationnelle) et/ou
                 BIBLIOTHEQUE_ERREURS.md (piège rejouable)
```

## Étape 3 — Implémenter directement ou déléguer ?

| Condition | Décision |
|---|---|
| Portée ≤ 1 fichier **et** ≤ 1 h **et** BC confié **et** hors freeze de scope | ✅ Implémentation directe (branche `fix/<issue>-<slug>`, claim marker) |
| Portée plus large / BC non confié / freeze | 📤 Issue « agent-ready » (contexte suffisant + critères d'acceptation) laissée au backlog |
| Doute sur le périmètre, le risque ou une règle | 🤝 Arbitrage PM (ne pas bloquer : l'issue est déjà tracée) |

Règles à respecter dans tous les cas : spec kit si travail significatif
(`.specify/constitution.md`), 1 issue = 1 PR (`Closes #N` dans le body), verrou de
branche (protocole #2400), labels BC + `lecon`.

## Étape 5 — Où consolider ?

| Nature de la leçon | Destination |
|---|---|
| Règle opérationnelle (comment travailler) | `AGENTS.md` (section leçons) + `CHANGELOG.md` |
| Piège rejouable (erreur déjà payée) | `docs/GESTION_PROJET/BIBLIOTHEQUE_ERREURS.md` (tableau piège → symptôme → garde) |
| Décision produit/technique structurante | ADR (`docs/architecture/adr/`) ou registre produit |

## Moisson mensuelle

Le rituel de fin de mois convertit les rapports figés, rétros et issues fermées « sans
leçon » en issues [LECON] puis consolide (P04 §7, issue #7061/#7087).
