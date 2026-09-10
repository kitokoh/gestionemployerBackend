# Feature Specification: BC-28 — fiche produit publique

**Feature Branch**: `bc/bc28-product-page` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6883 (frontend, Agent-Ready, P2, BC-28 CATALOG)

## Contexte

La fiche produit est la surface publique de conversion du BC-28 : elle doit être
lisible sans JS (SEO, acheteurs), avec galerie, caractéristiques et CTA de devis.

## Portée v1

- Page publique **rendue serveur** : titre, description, galerie (lot média #6887),
  caractéristiques clé/valeur, prix indicatif + unité/devise, CTA « Demander un devis »
  (→ lot lead).
- Design responsive cohérent avec la charte (tokens, protocole design).
- Pas de JS lourd requis pour la lecture.

## Hors périmètre

- Paiement ; panier ; comptes acheteurs ; avis clients.

## User Stories & scénarios d'acceptation

### US1 — Consulter une fiche produit (P2)
1. Given un produit **publié**, When j'ouvre son URL publique, Then la fiche complète s'affiche sans JS (contenu, galerie, specs, prix indicatif, unité).
2. Given un produit **brouillon**, When j'accède à son URL, Then 404 (aucune fuite de brouillon).
3. Given un produit sans image, Then un visuel de repli propre est affiché (pas de case cassée).

### US2 — Déclencher une demande (P2)
1. Given la fiche, When je clique « Demander un devis », Then je suis conduit au formulaire de demande (lot C-LEAD) avec le produit pré-renseigné.

## Exigences techniques (opposables)

- Rendu SSR ; meta SEO conformes au lot #6888 ; canonical.
- Contrats publics (DTO), cache, `throttle:shop-public` ; aucune donnée interne.
- i18n : libellés depuis les catalogues ; aucun texte en dur.

## Critères d'acceptation (DoD)

- [ ] Fiche lisible **sans JS** ; galerie fonctionnelle ; CTA présent.
- [ ] Meta SEO présentes (titre/description/OG) ; brouillons en 404.
