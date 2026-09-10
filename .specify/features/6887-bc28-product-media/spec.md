# Feature Specification: BC-28 — médias produits

**Feature Branch**: `bc/bc28-product-media` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6887 (Agent-Ready, P2, BC-28 CATALOG)

## Contexte

Le catalogue a besoin d'images produits fiables : upload contrôlé, validation,
variantes de taille et rendu public caché.

## Portée v1

- Upload via le **service de stockage existant** (disk configuré) — pas de nouveau stockage.
- Validation : types `jpg/png/webp`, taille max, sanitisation du nom, contrôle du contenu.
- 1 image principale + galerie ordonnée ; suppression avec le produit.
- Variantes/redimensionnement si l'outillage est présent ; sinon documenter la
  dépendance et servir l'original avec dimensions bornées.
- Rendu public avec en-têtes de cache ; jamais de chemin absolu client.

## Hors périmètre

- Édition d'image avancée (recadrage manuel), CDN dédié, vidéo.

## User Stories & scénarios d'acceptation

### US1 — Téléverser des photos (P2)
1. Given un produit, When je téléverse une image JPG < limite, Then elle est stockée et visible dans la galerie du produit.
2. Given un fichier de type interdit ou trop lourd, Then l'upload est refusé avec un message explicite (aucun stockage).

### US2 — Rendu public (P2)
1. Given un produit publié, When j'ouvre sa fiche, Then la galerie affiche les images via des URLs servies avec cache ; aucun fichier d'un autre tenant accessible.

## Exigences techniques (opposables)

- Isolation tenant : les médias d'un tenant ne sont jamais listables/servis pour un autre.
- Taille max et types validés **côté serveur** (pas seulement client).
- Aucun chemin absolu serveur exposé ; stockage via disk configuré.

## Critères d'acceptation (DoD)

- [ ] Tests upload (refus type/taille, isolation tenant) verts.
- [ ] Galerie publique OK (ordre, repli, cache).
