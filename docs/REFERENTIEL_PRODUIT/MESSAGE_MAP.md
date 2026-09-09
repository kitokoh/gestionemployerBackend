# Message map — « à dire / à ne pas dire » (surfaces publiques)

> Source : protocole `docs/PROTOCOLES/P03_VITRINE_PRESENTATION.md` (VIT-1, §2-§3) — issue #7067.
> Complète le référentiel produit (`APV.md` = source du wording ; `STATUTS.md` = statuts opposables ;
> `METRIQUES_VITRINE.md` = chiffres datés). Appliquer à TOUTES les surfaces publiques : README,
> site GitHub Pages, vitrines Next.js, stores, contenus GTM, démos.

## Positionnement (pitch canonique)

> « Plateforme **modulaire open-source** de gestion des opérations pour entreprises
> multi-sites et terrain » — **Leopardo RH** = l'expérience RH & paie, cœur historique.
> Verticales = solutions métier complètes (restauration, travel, fuel, éducation, livraison…).

## Lexique opposable

| Terme public | Définition publique |
|---|---|
| « plateforme modulaire open-source de gestion des opérations » | multi-sites / terrain ; core + BC isolés |
| « Leopardo RH » | l'expérience RH & paie, cœur historique de la plateforme |
| « verticales » | solutions métier complètes (restauration, travel, fuel, éducation, livraison…) |
| « multi-pays / multi-paie » | uniquement les pays réellement couverts (registre `docs/payroll/*_COMPLIANCE.md`) |
| « app mobile » | apps Flutter distribuées (Firebase/Android/iOS) — jamais « disponible sur store » si non soumise |
| « IA » | Leo IA et fonctionnalités réellement livrées — jamais de roadmap présentée comme existante |

## ✅ À dire

- « Open-source, auto-hébergeable et prêt SaaS » (vrai : repo public, multi-tenant).
- « Mobile-first pour équipes terrain » (vrai : 7 apps Flutter + kiosk + web).
- « HR & paie multi-pays, pointage, onboarding, compta, CRM… » **uniquement pour les modules/pays au statut `live`** (`STATUTS.md`).
- « 14 jours d'essai guidé » / « compte démo » (flux réel : /signup → sandbox).
- Les métriques **datées** du registre `METRIQUES_VITRINE.md`.

## ❌ À NE PAS DIRE (sans preuve/statut live)

- « Client desktop Windows/macOS téléchargeable » — **interdit** tant qu'aucun installateur public n'existe (#3257).
- « Disponible sur le store » pour une app non soumise (Firebase ≠ store public).
- « Tous les plans incluent… » sans tableau des plans vérifié.
- Badges d'économies, case studies, logos clients **non vérifiables** (#4202, #3863).
- « Bientôt disponible » pour une fonctionnalité sans issue publique de référence (roadmap explicite uniquement).
- « 18 modules », « 1 900+ tests », « 5 apps » — **périmés** ; utiliser `METRIQUES_VITRINE.md` (27 modules, 7 apps, chiffres datés).
- Pays/devises non couverts par la paie (registre `docs/payroll/`).

## Règles

1. Toute phrase publique dérive du message canonique (README/APV/STATUTS) — jamais l'inverse.
2. Une fonctionnalité non `live` ne peut apparaître qu'en *roadmap* explicite.
3. Toute chaîne publique ajoutée l'est dans les **4 locales** (fr/en/tr/ar) dans la même PR.
4. Toute PR qui modifie une promesse publique référence `STATUTS.md` + cette doc.
5. Revue mensuelle des surfaces (P03) par le gardien de surface désigné.

## Références croisées

- `docs/PROTOCOLES/P03_VITRINE_PRESENTATION.md` (protocole source)
- `docs/REFERENTIEL_PRODUIT/APV.md` (wording) · `STATUTS.md` (statuts) · `METRIQUES_VITRINE.md` (chiffres)
- `docs/ops/DOMAINS.md` (surfaces par domaine)
