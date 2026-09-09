# Rapports mensuels — rituel « fin de mois » (vitrine, design, architecture, RETEX, protocoles)

> Cadre : `docs/PROTOCOLES/00_SOMMAIRE.md` (calendrier des rituels) +
> `docs/PROTOCOLES/03_VITRINE_PRESENTATION.md` (VIT-10). Issue #7066.
> **Quand** : dernier jour ouvrable du mois. **Qui** : gardien vitrine (prépare) + PM
> (décide). **Sortie** : un rapport par mois, déposé ici : `YYYY-MM.md`.

## Déroulé du rituel

1. **Vitrine (VIT-10)** : parcourir les surfaces V1-V9 (`03_VITRINE_PRESENTATION.md`
   §1) avec la checklist VIT-9 ; vérifier l'alignement vitrine ↔ déploiement (VIT-6)
   et le wording (`docs/REFERENTIEL_PRODUIT/MESSAGE_MAP.md`).
2. **Design (DSG-8)** : parcours visuel des surfaces clés, tokens synchronisés
   (`COULEURS.md` ↔ code — garde `check-design-token-sync.py`), golden tests,
   inventaire `DESIGN_TOKENS_INVENTAIRE.md` mis à jour.
3. **Architecture 2 volets (ENV-9)** : rejouer la checklist ENV-8
   (`07_ARCHITECTURE_DEV_PROD.md`) : registre des environnements à jour, versions
   dev/prod relevées, secrets/backups/monitoring OK.
4. **RETEX (RET-6)** : tri des issues `retex` en attente (verdict : à faire /
   à documenter / wontfix / à fondre dans un protocole).
5. **Protocoles** : revue du corpus lui-même (les règles sont-elles suivies ? à
   durcir ?) — les modifications passent par des PR `docs:`.
6. Ouverture des issues d'écart (labels `vitrine` / `design` / `infra` / `retex` /
   `protocole`, BC si pertinent) + rédaction du rapport ci-dessous.

## Modèle de rapport

```markdown
# Rapport mensuel — YYYY-MM
État global : 🟢 / 🟠 / 🔴

## Vitrine (V1-V9)
| Surface | État | Écarts | Issue(s) |
|---|---|---|---|
| V1 README | … | … | #… |

Wording validé pour le mois : [2-3 phrases, cf. MESSAGE_MAP.md]
Faits vérifiés : [chiffres / modules / versions contrôlés]

## Design
État : 🟢/🟠/🔴 — tokens OK ? [ ] — golden tests verts ? [ ] — écarts : #…

## Architecture 2 volets (ENV-9)
Registre à jour ? [ ] — versions dev/prod : API …/…, web …/…, admin …/…
Secrets/backups/monitoring : [ ] — écarts : #…

## RETEX triés (RET-6)
| Issue | Verdict | Note |
|---|---|---|
| #… | à faire | … |

## Protocoles
Mises à jour du mois : #… — propositions de durcissement : #…

## Prochaines échéances
[stores, launches, salons, posts, tranches desktop…]
```

## Archives

| Mois | Rapport | État |
|---|---|---|
| 2026-09 | (premier rituel — à produire) | ⏳ |
