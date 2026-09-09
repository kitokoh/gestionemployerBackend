---
name: "🗓️ Revue mensuelle des protocoles & surfaces"
about: Rituel de fin de mois — vitrine (V1-V9), design (DSG-8), architecture 2 volets (ENV-9), tri RETEX, revue des protocoles.
title: "Revue mensuelle — YYYY-MM"
labels: []
assignees: ''
---

## Cadre

Rituel du dernier jour ouvré du mois (corpus `docs/PROTOCOLES/`, issue #7087).
Exécutant : gardien vitrine / PM. Rapport attendu :
`docs/GOTO_MARKET/rapports-mensuels/YYYY-MM.md`.

## 1. Vitrine (V1-V9)

- [ ] Chiffres régénérés et datés (registre `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md`)
- [ ] Termes conformes (MESSAGE.md / TERMES.md) — zéro sur-promesse
- [ ] URLs/lien démo vérifiés (docs/ops/DOMAINS.md)
- [ ] Issues `vitrine` triées

## 2. Design (DSG-8)

- [ ] Dérives de tokens détectées (check-design-token-sync.py + gardes) → issues `design`
- [ ] Surfaces comparées (captures) ; apps en dérive identifiées

## 3. Architecture 2 volets (ENV-9)

- [ ] Parité live-vs-dépôt (registres DOMAINS/DEPLOYMENT_URLS/topologie)
- [ ] Secrets & workers vérifiés ; écarts → issues `infra`

## 4. Capitalisation (P04)

- [ ] Tri des issues [LECON] / RETEX en attente
- [ ] Rapports figés du mois convertis en issues

## 5. Revue des protocoles

- [ ] Protocoles P01-P07 rejoués ; durcissements proposés → PR `docs:`
- [ ] Index/sommaire mis à jour

## Sortie

- [ ] Rapport `docs/GOTO_MARKET/rapports-mensuels/YYYY-MM.md` déposé
- [ ] Issues créées pour tout écart ; arbitrages remontés au fondateur
