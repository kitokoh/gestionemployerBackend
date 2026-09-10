# Feature Specification: BC-28 — SEO fiches & catalogue

**Feature Branch**: `bc/bc28-catalog-seo` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6888 (Agent-Ready, P2, feature, BC-28 CATALOG)

## Contexte

Les fiches produits publiques doivent être indexables et partageables, sans jamais
exposer les brouillons ni de données internes.

## Portée v1

- **Meta dynamiques** par fiche : `title`, `description`, image OG (produit), `canonical`.
- **Sitemap public** limité aux produits **publiés** (+ page catalogue).
- **`noindex`** explicite sur brouillons et aperçus.
- Rendu SSR (cohérent avec la fiche produit #6883).

## Hors périmètre

- SEO du site vitrine BC-27 (#6873), données structurées riches (rich snippets) v2,
  régie publicitaire / balises tierces.

## User Stories & scénarios d'acceptation

### US1 — Fiche indexable (P2)
1. Given un produit publié, When un crawler lit la fiche, Then le titre, la description, l'image OG et le canonical sont présents et cohérents.
2. Given la même fiche avec paramètres de tracking, Then le canonical pointe l'URL propre.

### US2 — Aucune fuite de brouillon (P0)
1. Given un produit brouillon, When le crawler lit l'URL ou le sitemap, Then la fiche est `noindex` et absente du sitemap.
2. Given le sitemap, Then il ne contient que des produits publiés et renvoie 200.

## Exigences techniques (opposables)

- Sitemap servé par une route publique dédiée, cache court, pas de génération à la volée par requête crawler.
- Aucune donnée interne dans les meta (pas de nom de tenant interne non public, pas d'ID interne exposé).
- i18n : meta localisées quand la locale est exposée (cohérence BC-27 #6874).

## Critères d'acceptation (DoD)

- [ ] Fiche indexable (meta correctes) ; sitemap limité aux publiés.
- [ ] Test de non-fuite brouillon (noindex + absence sitemap) vert.
