# Feature Specification: BC-28 — parcours E2E complet

**Feature Branch**: `bc/bc28-e2e-journey` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6890 (Agent-Ready, CI, P2, BC-28 CATALOG)

## Contexte

Le parcours critique de l'EPIC BC-28 (#6877) doit être prouvé de bout en bout :
création produits → publication → consultation acheteur → demande de devis → lead.

## Portée v1 (specs Playwright)

1. **Tenant** : crée catégories + produits, téléverse une image, publie.
2. **Acheteur (sans auth)** : consulte le catalogue + une fiche, soumet une demande.
3. **Tenant** : voit le lead en back-office (statut initial).
4. **Assertions de non-fuite** : routes publiques → aucune donnée interne ;
   brouillon non accessible ; sitemap limité aux publiés (#6888).

## Hors périmètre

- Paiement ; multi-devises avancé ; performance de charge (couvert ailleurs).

## User Stories & scénarios d'acceptation

### US1 — Parcours nominal (P1)
1. Given un tenant avec le catalogue activé, When il crée et publie un produit, Then la fiche est visible publiquement.
2. Given la fiche publique, When l'acheteur soumet le formulaire de devis, Then un lead est créé et visible côté tenant.

### US2 — Non-fuite (P0)
1. Given une URL de brouillon, Then 404 ; Given les routes publiques, Then aucune donnée interne dans les réponses.

## Exigences techniques (opposables)

- Suite **isolée** (`e2e-isolated.yml` ou équivalent) : données de test propres, pas de dépendance à l'ordre.
- Sélecteurs stables (data-testid) ; pas d'attente arbitraire (attentes explicites).
- La suite est **verte** en CI avant merge du lot fonctionnel.

## Critères d'acceptation (DoD)

- [ ] Suite E2E verte couvrant le parcours critique de l'EPIC.
- [ ] Assertions de non-fuite présentes et vertes.
