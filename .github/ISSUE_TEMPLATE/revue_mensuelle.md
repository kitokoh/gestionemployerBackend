---
name: "🗓️ Revue mensuelle des protocoles"
about: Rituel de fin de mois — exécuter, auditer et amender la suite de protocoles (docs/PROTOCOLS/00_INDEX.md §5).
title: "Revue mensuelle des protocoles — YYYY-MM"
labels: []
assignees: ''
---

## Contexte

Rituel du dernier jour ouvré du mois, défini dans `docs/PROTOCOLS/00_INDEX.md` §5.
Le fondateur/PM exécute cette checklist ; le rapport consolidé est déposé dans
`docs/PROTOCOLS/rapports/YYYY-MM.md`.

## 1. Exécuter les rituels mensuels des protocoles

- [ ] **01 — Validation & marché** (§7) : portes marché du mois rejouées, verdicts vs incidents réels
- [ ] **02 — Onboarding** (§6) : retours des nouveaux entrants, pièges rencontrés
- [ ] **03 — Vitrine** (§7) : chiffres régénérés et datés, surfaces comparées, issues d'écart ouvertes
- [ ] **04 — Capitalisation** (§7) : moisson des leçons du mois (rapports figés, rétros, journal) → issues
- [ ] **05 — Design** (§5) : audit visuel des surfaces, dérives détectées → issues
- [ ] **06 — Desktop** (§7) : portefeuille des tranches verticales (actives, canaux, retours pilotes)
- [ ] **07 — Architecture** (§5) : contrôle d'écart doc ↔ réalité des deux volets (health, versions, workers)

## 2. Consolider

- [ ] Rédiger `docs/PROTOCOLS/rapports/YYYY-MM.md` (synthèse par protocole, écarts, issues, métriques §6)
- [ ] Premier rapport du mois suivant la création de la suite attendu (sinon noter l'écart)

## 3. Auditer & amender

- [ ] Règles de la suite réellement suivies ? Écart répété → amendement de protocole (issue + PR)
- [ ] Décisions ouvertes de l'index (§8) rejouées, tranchées ou re-portées
- [ ] États des lieux (§8) de chaque protocole mis à jour
- [ ] Versions des protocoles amendés incrémentées + historique de l'index (§7) complété

## 4. Sortie

- [ ] Rapport mensuel déposé
- [ ] Issues créées pour tout écart non soldé (`[REX]` / `[ECART]`, cf. PROTOCOLE 04)
- [ ] Index `docs/PROTOCOLS/00_INDEX.md` à jour (statuts, historique, décisions ouvertes)
