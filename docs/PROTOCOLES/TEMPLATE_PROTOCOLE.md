# TEMPLATE — Gabarit standard d'un protocole Leopardo RH

> Copier ce gabarit pour créer ou réviser un protocole. Un protocole est un **contrat opérationnel** :
> court, précis, vérifiable, révisable. S'il dépasse ~150 lignes, le découper.

```markdown
# PXX — <Nom court du protocole>

> **Statut :** proposition | ratifié | abrogé
> **Version :** x.y — **Dernière revue :** YYYY-MM-DD (revue mensuelle)
> **Propriétaire :** <PM | gardien technique | gardien du domaine concerné>
> **Portée :** <surfaces / BC / rôles concernés — et ce qui est EXPLICITEMENT hors champ>

## 1. Objet
<La question à laquelle ce protocole répond, en 2-3 phrases. À quoi ressemble l'échec sans lui ?>

## 2. Déclencheurs
<Quand ce protocole s'applique-t-il ? (événements, moments du cycle, seuils)>

## 3. Règles
1. <Règle 1 — action + condition, écrite pour être exécutable par un agent>
2. <Règle 2>
   - <sous-détail exécutable : commande, chemin, workflow>

## 4. Définitions de fait
| Niveau | Définition | Preuve exigée |
|---|---|---|
| DoR | … | … |
| DoD | … | … |
| <spécifique> | … | … |

## 5. Gardes & automatisation
| Existant (déjà en CI) | À créer (issue) | Vérifie |
|---|---|---|
| <workflow/script réel> | — | <quoi> |
| — | `#<issue>` | <quoi> |

## 6. Rôles
| Rôle | Responsabilités dans ce protocole |
|---|---|
| PM | … |
| Gardien technique | … |
| Agent exécutant | … |

## 7. Indicateurs & preuves
<Comment savoir si le protocole est respecté (métriques, artefacts, rapports)>

## 8. Revue mensuelle — questions spécifiques
- [ ] <question 1>
- [ ] <question 2>

## 9. Historique
| Version | Date | Changement |
|---|---|---|
| x.y | … | … |
```
