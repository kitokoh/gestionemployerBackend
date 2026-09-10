# Épic Specification: BC-28 CATALOG — exposition produits B2B

**Feature Branch**: `bc/bc28-catalog` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6877 (EPIC, P1, BC-28 CATALOG)

## Vision

Un tenant **usine/fournisseur** expose son **catalogue produits** aux acheteurs
professionnels : fiches publiques, galerie, caractéristiques, **demande de devis/
contact** — dans le même produit, sans portail séparé.

## Décisions structurantes (PM, 2026-09-05)

- Nouveau BC **BC-28 CATALOG** (registre MAT-001) ; hors `FREEZE_SCOPE_60J` → **exception fondateur**.
- v1 = catalogue + **génération de leads B2B** ; **pas de paiement en ligne**.
- Montants indicatifs multi-devises (XOF/XAF/DZD/EUR…) en minor units.
- **Réutilisable dans une vitrine BC-27** (section `products`, lot #6891) mais autonome.
- Public = 0 donnée interne : routes isolées (`throttle:shop-public`), DTO public + cache.
- Demandes acheteurs → **leads CRM BC-11** (consentement RGPD) + notification BC-13.

## Découpage en lots (issues filles)

| Lot | Issue | Objet |
|---|---|---|
| Fiche produit publique | #6883 | galerie, caractéristiques, CTA devis |
| Médias | #6887 | photos produits (upload, validation, variantes) |
| SEO | #6888 | meta, sitemap produits publiés, SSR |
| E2E | #6890 | parcours complet (création → lead) |
| Vitrine BC-27 | #6891 | section « produits » réutilisable |

## User Stories (niveau épic)

### US1 — Publier un catalogue et recevoir des demandes (P1)
1. Given un tenant fournisseur, When il crée catégories + produits et les publie, Then les fiches sont consultables publiquement sans authentification.
2. Given un acheteur, When il soumet une demande de devis, Then un lead BC-11 est créé avec consentement et le tenant est notifié (BC-13).

### US2 — Zéro fuite de données (P0)
1. Given une route publique, Then aucune donnée interne/tenant n'est exposée ; brouillons non indexables et non listés.

## Contraintes opposables

- Isolation tenant ; contrats publics dédiés (DTO) ; cache et throttling.
- Pas de paiement en ligne ; montants indicatifs marqués comme tels.
- Specs par lot obligatoires avant code (constitution §I).

## Critères d'acceptation (épic)

- [ ] Parcours E2E #6890 vert (création → publication → consultation → devis → lead).
- [ ] Aucune fuite (assertions) ; sitemap limité aux produits publiés.
