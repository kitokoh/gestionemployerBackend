# Épic Specification: BC-27 SHOWCASE — site vitrine d'entreprise en 1 clic

**Feature Branch**: `bc/bc27-showcase` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6862 (EPIC, P1, BC-27 SHOWCASE)

## Vision

Le responsable d'un tenant crée un **site vitrine public de son entreprise en 1 clic** :
pages/sections, thème, logo, contact, publié sur une URL stable, sans compétence
technique, administré depuis la plateforme.

## Décisions structurantes (PM, 2026-09-05)

- Nouveau BC **BC-27 SHOWCASE** (registre MAT-001) ; hors `FREEZE_SCOPE_60J` → **exception fondateur requise**.
- Pas de CMS complet embarqué : v1 = **moteur de sections + thèmes maison** (blocs éditables, rendu serveur) ; spike éditeur visuel (ex. GrapeJS) séparé.
- Public = **0 donnée tenant interne/RH** : routes publiques isolées (`throttle:shop-public`), DTO public dédié + cache.
- v1 : URL `/vitrine/{slug}` ; sous-domaine/domaine personnalisé = phase 2.

## Découpage en lots (issues filles)

| Lot | Issue | Objet |
|---|---|---|
| Sections & API | #6866, #6867 | contrat de sections + API CRUD + API publique |
| Thèmes | #6868 | 3 thèmes + variables (couleurs, logo, typos) |
| Éditeur admin | #6870 | composer la page (Vue admin) |
| Publication | #6871 | brouillon → publié, aperçu, invalidation cache |
| Médias | #6872 | logo & images de sections |
| SEO | #6873 | meta/OG, sitemap, robots, SSR |
| i18n | #6874 | fr/en/ar/tr + garde |
| RGPD | #6875 | mentions légales, cookies |
| E2E | #6876 | parcours complet |

## User Stories (niveau épic)

### US1 — Créer un site en 1 clic (P1)
1. Given un tenant actif, When le responsable clique « Créer ma vitrine », Then un site brouillon est créé avec un slug stable et un thème par défaut.
2. Given le brouillon, When il publie, Then le site est visible publiquement sur `/vitrine/{slug}` et les brouillons ne le sont pas.

### US2 — Personnaliser sans compétence technique (P1)
1. Given l'éditeur, When il ajoute/édite des sections (texte, images, contact), Then le rendu public reflète les changements publiés.
2. Given un contenu non publié, Then il n'apparaît jamais publiquement.

### US3 — Aucune fuite de données internes (P0)
1. Given une route publique, Then aucune donnée RH/interne n'est exposée ; les accès sont throttlés et servis via DTO public + cache.

## Contraintes opposables

- Isolation tenant stricte (routes publiques ≠ routes authentifiées).
- Aucune dépendance BC-27 → BC interne autre que lecture via contrats publics.
- Specs par lot obligatoires avant code (constitution §I) ; les 9 issues filles ont chacune leur spec/plan.

## Critères d'acceptation (épic)

- [ ] Création → édition → publication → consultation publique de bout en bout (E2E #6876).
- [ ] 0 donnée interne exposée (assertions de non-fuite).
- [ ] Thèmes/i18n/RGPD/SEO conformes aux specs des lots.
