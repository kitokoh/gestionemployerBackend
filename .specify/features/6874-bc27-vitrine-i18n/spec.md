# Feature Specification: BC-27 — i18n du site vitrine (fr/en/ar/tr)

**Feature Branch**: `bc/bc27-vitrine-i18n` | **Created**: 2026-09-09 | **Status**: Draft (prérequis constitutionnel §I)
**Issue**: #6874 (i18n, Agent-Ready, P2, BC-27 SHOWCASE)

## Contexte

Le site vitrine BC-27 doit être multilingue (fr/en/ar/tr, RTL pour l'arabe) et
éditable par langue, avec un rendu public qui suit le choix exposé ou
`Accept-Language`.

## Portée v1

- **Contenu de sections multilingue** : structure par locale (une valeur par langue
  et par champ éditable).
- **Éditeur** : sélecteur de langue ; complétion guidée (langue manquante signalée).
- **Rendu public** : locale choisie (paramètre explicite) sinon `Accept-Language`,
  repli `fr`.
- Aucune chaîne dure accentuée (gardes CI existantes).

## Hors périmètre

- Traduction automatique ; langues supplémentaires ; contenu RTL complet au-delà du sens de lecture.

## User Stories & scénarios d'acceptation

### US1 — Publier un site en 2 langues (P2)
1. Given un site avec sections fr remplies, When j'ajoute les valeurs `en`, Then le rendu public en anglais affiche les valeurs EN et retombe sur FR pour les champs vides.
2. Given une locale `ar`, Then la page est rendue en RTL, sans rupture de mise en page.

### US2 — Parité des clés (P2)
1. Given une nouvelle clé d'interface, When la PR est ouverte, Then les 4 locales sont présentes et la garde i18n est verte.

## Exigences techniques (opposables)

- Clés partagées : `shared/i18n/locales/{fr,en,ar,tr}.json` (pont existant) ; aucune chaîne en dur.
- Éditeur : les 4 langues visibles, état incomplet explicite (pas de fallback silencieux en édition).
- Public : cache par (site, locale) ; invalidation à la publication.

## Critères d'acceptation (DoD)

- [ ] Site en 2 langues créé et rendu correctement (FR + EN, contrôle AR RTL).
- [ ] Parité des clés vérifiée par l'outillage i18n existant ; garde verte.
