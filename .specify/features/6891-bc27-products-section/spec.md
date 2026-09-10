# Feature Specification: BC-27/BC-28 — section « produits » réutilisable dans une vitrine

**Feature Branch**: `bc/bc27-products-section` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6891 (frontend, Agent-Ready, P2, BC-27 SHOWCASE)

## Contexte

Une vitrine BC-27 doit pouvoir afficher une sélection de produits publiés du BC-28
**sans dupliquer la logique** : lecture via le contrat public BC-28 uniquement.

## Portée v1

- Nouveau **type de section BC-27 `products`** : affiche une sélection de produits
  publiés du tenant (sélection par produit ou par catégorie).
- Édition : choix des produits/catégories dans l'éditeur de vitrine (UC BC-27 #6870).
- Rendu vitrine : grille produits + lien vers la fiche (BC-28 #6883).
- **Dépendance optionnelle** : vitrine sans catalogue activé = section absente/masquée.

## Hors périmètre

- Création/édition de produits depuis la vitrine (appartient au BC-28).
- Panier, prix dynamiques, personnalisation avancée du rendu.

## User Stories & scénarios d'acceptation

### US1 — Ajouter une section produits (P2)
1. Given une vitrine avec le BC-28 activé, When j'ajoute une section `products` et sélectionne 6 produits publiés, Then la vitrine publiée affiche la grille avec liens fiches.
2. Given un produit retiré/non publié, Then il disparaît de la section sans casser le rendu.

### US2 — Dégradation propre (P2)
1. Given un tenant **sans** catalogue, When la vitrine est rendue, Then la section est absente (pas d'erreur, pas de zone vide cassée).

## Exigences techniques (opposables)

- Aucune duplication : lecture via le **contrat public BC-28** (pas d'accès direct aux tables).
- Rendu serveur (cohérent SEO #6873) ; cache + invalidation à la publication BC-28.
- Aucune donnée interne exposée ; i18n via catalogues.

## Critères d'acceptation (DoD)

- [ ] Section configurable et rendue dans la vitrine ; liens fiches corrects.
- [ ] Vitrine sans catalogue : section absente sans erreur.
- [ ] Tests de rendu verts.
