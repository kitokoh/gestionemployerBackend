# Protocole P3 — Vitrine & présentation : le projet présentable à tout moment, avec les bons mots

| | |
|---|---|
| **Statut** | PROPOSÉ (2026-09-09) — à valider par le PM |
| **Dernière revue** | 2026-09-09 |
| **Owner** | PM (discours) ; lead technique (exactitude des surfaces) |
| **Périmètre** | Toute surface où Leopardo est présenté : README, site gh-pages, vitrine web (`front/web`), dossier GTM, plateformes publiques, présentations, démos, FAQ |
| **Dépend de** | `docs/GOTO_MARKET/` (01_PRODUCT, 2026_MARKET_LAUNCH_COMPANY_OS, ASSETS_PRODUCTION), `docs/REFERENTIEL_PRODUIT/`, `front/web/src/modules/vitrine/data/`, `docs/client/FAQ.md`, P1 (chaque release touche la vitrine) |
| **Produit** | `docs/GOTO_MARKET/VITRINE_REVIEW_YYYY_MM.md` chaque fin de mois |

---

## 1. Objet et constat

Le projet est aujourd'hui présenté avec **trois discours divergents** : README EN (« modular
platform / HR & Payroll OS »), gh-pages (meta « AI-native », obsolète), thèse produit FR
(« Mobile-First Company OS », 2026-06-05) ; et des supports **inégaux** (one-pager rédigé mais
brochure et pitch deck encore à l'état de plan/outline ; aucun PDF final). Rien ne garantit que la
vitrine suive les releases ni qu'un rituel la maintienne fraîche.

L'ambition : **Leopardo doit pouvoir être présenté correctement à tout moment, en 15 secondes,
en 60 secondes, en 5 minutes ou en dossier complet — avec les mêmes mots, partout.**

## 2. Positionnement canonique (source unique du discours)

1. **La thèse fait foi** : `docs/GOTO_MARKET/01_PRODUCT/COMPANY_OS_THESIS.md` + `POSITIONING.md`
   (et, pour l'édition en cours, `docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/02_POSITIONNEMENT_ET_MESSAGING.md`).
2. Toute phrase publique (README, site, soumissions plateformes, pitch, doc client) **dérive** de cette
   source et y est reliée par commentaire ou lien.
3. En cas de divergence entre deux surfaces → c'est la surface qui a tort ; issue de correction immédiate.

**Phrase de référence (à faire vivre par le PM) :**
> « Leopardo est un Company OS mobile-first pour PME de terrain (5-250 employés, multi-sites) :
> RH, paie, pointage, comptabilité, CRM et marketing dans un même cockpit mobile —
> la visibilité d'une grande entreprise sans consultant ni Excel. »

**Glossaire des termes (FR/EN) — à tenir dans `docs/GOTO_MARKET/01_PRODUCT/GLOSSAIRE.md` :**

| Terme | Autorisé | À éviter |
|-------|----------|---------|
| Catégorie | « Company OS », « plateforme de gestion d'entreprise modulaire », « open-source business operations platform » | « un SIRH », « un ERP », « un logiciel de pointage » seuls |
| Produit | « Leopardo » (nom de plateforme) ; « Leopardo RH » = module historique | Confondre Leopardo et Leopardo RH |
| IA | « assistant IA » uniquement si la fonctionnalité est réelle et démontrée | « AI-native » (obsolète) |
| Positionnement | « mobile-first », « multi-sites », « terrain », « open-source & self-hostable » | Comparatifs non sourcés, superlatifs non démontrés |
| Clients | « PME de terrain », « entreprises multi-sites » | « startups », « grands comptes » (hors offre Scale documentée) |

## 3. Inventaire des surfaces vitrine (propriétaire + fraîcheur)

| Surface | Fichiers | Propriétaire | Règle de fraîcheur |
|---------|----------|--------------|--------------------|
| README racine | `README.md` | PM + lead | Badges CI réels, positionnement canonique, captures ≤ 60 j |
| Site statique | `site/gh-pages/` (index.html, style.css) | À fusionner ou aligner avec `front/web` | Aligné sur la vitrine Next.js (doublon toléré provisoirement, jamais divergent) |
| Vitrine web | `front/web/src/modules/vitrine/data/` : features.ts, pricing.ts, faq.ts, case-studies.ts, testimonials.ts, blog.ts, videos.ts, **changelog-public.ts**, guides.ts | Lead web | Chaque release : `changelog-public.ts` ; chaque changement d'offre : pricing/features dans la même PR ou issue fille |
| Branding | `assets/branding/` (logo-240.png, og-banner.png) | Design (P5) | Cohérent avec le design system |
| Dossier produit | `docs/GOTO_MARKET/ASSETS_PRODUCTION/DOCS/` (`LEOPARDO_ONE_PAGER.md`, `LEOPARDO_BROCHURE_PLAN.md`, `LEOPARDO_PITCH_DECK_OUTLINE.md`, `LANDING_PAGE_SPEC.md`) | PM | Une version finale (PDF) vivante, pas des squelettes |
| Soumissions plateformes | `docs/GOTO_MARKET/platform-submissions/` (Product Hunt, G2, GitHub) | PM | Description + captures à jour à chaque release majeure |
| FAQ & docs client | `docs/client/FAQ.md`, guides | PM | « FAQ projet » à créer (qui, quoi, combien, pourquoi open-source) |
| Référentiel produit | `docs/REFERENTIEL_PRODUIT/` | PM | Source de vérité dev/produit — sert à vérifier les promesses de la vitrine |

## 4. Rituel mensuel — « Revue vitrine du dernier jour ouvré »

Chaque **fin de mois** (dernier jour ouvré, fenêtre commune avec P4/P5/P7), le PM (ou un agent mandaté)
exécute la checklist et produit `docs/GOTO_MARKET/VITRINE_REVIEW_YYYY_MM.md`.

Checklist :
- [ ] **Positionnement** : relire COMPANY_OS_THESIS + POSITIONING ; noter tout écart entre ce que le
      produit fait (REFERENTIEL_PRODUIT, CHANGELOG du mois) et ce que la vitrine promet ;
      `GLOSSAIRE.md` (termes FR/EN, §2) existe et est tenu à jour.
- [ ] **Harmonie des surfaces** : comparer README ↔ gh-pages ↔ vitrine web ↔ soumissions ; chaque écart
      constaté = issue (label `vitrine`), même mineur.
- [ ] **Fraîcheur des données** : `changelog-public.ts` contient-il les releases du mois ? pricing/features
      alignés sur l'offre — correspondance noms GTM ↔ clés `pricing.ts` : Starter Terrain→Pilot,
      Growth Company OS→Operations, Scale→Enterprise (Free en entonnoir) ?
- [ ] **Captures d'écran** : aucune > 60 jours (Git LFS) ; régénérer les captures clés (mobile + web + admin)
      avec un jeu de démo (`docs/DEMO_ACCOUNTS.md`).
- [ ] **Supports** : one-pager / brochure / pitch deck — la version PDF finale existe-t-elle ? est-elle à jour ?
- [ ] **Liens & SEO** : parcours landing → inscription fonctionnel ; lighthouse acceptable ; pas de lien mort.
- [ ] **FAQs** : FAQ projet + FAQ client + FAQ vitrine (`faq.ts`) sans réponse contradictoire.
- [ ] **Décision** : la vitrine est-elle « présentable à tout moment » ce mois-ci ? Oui / Non (raisons + issues).
- [ ] **Protocoles** : P3 lui-même est-il à jour (nouvelle surface, nouveau canal) ?

## 5. Règle déclenchée par release (complément du rituel mensuel)

Toute release `vX.Y.Z` (P1) qui touche l'offre, une fonctionnalité visible ou un marché :
- la PR de release (ou une issue fille dédiée, créée au comité de release) doit mettre à jour
  `changelog-public.ts` et les données vitrine concernées ;
- le `PRET_MARCHE_REPORT` (P1) contient une ligne « Vitrine synchronisée : OUI/NON ».
Objectif : la vitrine n'accuse jamais plus d'une release de retard.

## 6. Le kit « présentation à tout moment »

Constituer et maintenir (checklist mensuelle ci-dessus) un **dossier unique** — proposé :
`docs/GOTO_MARKET/ASSETS_PRODUCTION/KIT_PRESENTATION/` — contenant :
- pitch 15 s et 60 s (scripts, depuis `02_POSITIONNEMENT_ET_MESSAGING.md` et `SOCIAL_MEDIA_PITCHES.md`) ;
- one-pager final (PDF) + version FR/EN ;
- 3-5 slides « démo vivante » (parcours réels avec `docs/DEMO_ACCOUNTS.md`) ;
- captures fraîches par surface ;
- 3 cas d'usage racontables (dont pilotes existants) ;
- FAQ projet (5 questions : qu'est-ce que c'est, pour qui, pourquoi open-source, combien, qui est derrière).

Le lien vers ce dossier est la réponse standard à toute demande de présentation imprévue.

## 7. Ton & voix (règles minimales)

1. Concret avant superlatif : montrer, ne pas promettre.
2. Un terme métier par concept (glossaire §2).
3. FR pour les marchés francophones, EN pour l'international et l'open-source ; jamais de mélange dans une même surface.
4. Open-source assumé : licence MIT, self-hostable, pas de « vendor lock-in » caché.
5. Toute affirmation chiffrée (prix, perfs, compatibilité) sourcée dans REFERENTIEL_PRODUIT ou GTM.

## 8. Rôles

| Rôle | Responsabilité |
|------|----------------|
| PM | Positionnement, rituel mensuel, arbitrage des écarts, contenu GTM |
| Lead web/design | Surfaces vitrine, captures, fraîcheur technique (P5) |
| Agents | Issues « vitrine » dès qu'un texte public est obsolète ; jamais de réécriture de discours sans issue |

## 9. Indicateurs

- Âge moyen des surfaces vitrine (cible : < 30 j) ; nombre d'écarts inter-surfaces ouverts (cible : ≤ 2).
- Délai release → vitrine à jour (cible : ≤ 5 j ouvrés).
- « Présentable à tout moment » : auto-évaluation mensuelle Oui/Non avec motif.
