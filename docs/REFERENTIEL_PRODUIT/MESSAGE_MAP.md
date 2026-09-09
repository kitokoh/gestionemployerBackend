# MESSAGE_MAP — Message canonique & « à dire / à ne pas dire »

> Source du wording public du produit. Établi le 2026-09-09 à partir de
> `docs/REFERENTIEL_PRODUIT/APV.md` (source de vérité du wording), du corpus de
> protocoles `docs/PROTOCOLES/P03_VITRINE_PRESENTATION.md` et des précédents de
> sur-promesse (#3257 desktop, #4202 badge économies, #3863 case studies, #3888).
> Toute surface publique (README, site GitHub Pages, vitrines Next.js, stores,
> contenus GTM) doit parler ce message. Ce registre sera consolidé dans le
> registre MESSAGE canonique (issue #7057).

## 1. Positionnement en une phrase (source : APV.md)

> « Leopardo RH : le **Mobile-First Company OS** pour PME terrain — l'app s'ouvre
> sur une conversation, se déploie module par module, et apprend à mesure qu'on
> l'utilise, en partant toujours du téléphone. »

## 2. Ce que Leopardo est / n'est pas

| Leopardo EST | Leopardo N'EST PAS |
|---|---|
| Une plateforme d'opérations métier **mobile-first** pour entreprises multi-sites et terrain | Un ERP de bureau « desktop-first » |
| Un produit **modulaire** (RH, paie, comptabilité, CRM, marketing…) activable par entreprise | Un monolithe « tout inclus » |
| **Open source** (MIT) et évolutif par API versionnée | Un produit fermé |
| Un système **vivant** (conversationnel, apprend en fonctionnement) | Un simple dashboard de KPIs |

## 3. Lexique public (à dire / à ne pas dire)

| Terme / promesse | Verdict | Motif |
|---|---|---|
| « Mobile-First Company OS » | ✅ autorisé | Positionnement canonique (APV, corpus P03) |
| « Business operations platform » / « HR & Payroll OS » | ⚠️ à harmoniser | Discours EN historique (README / gh-pages) — décision de positionnement EN ouverte (fondateur) |
| « Application **desktop** Windows/macOS » | 🚫 **interdit en vitrine** | Aucun installateur public n'existe (#3257) |
| « Bientôt », « en cours de développement » | 🚫 interdit | Sur-promesse sans date ni statut `live` |
| « Inclus dans tous les plans » | 🚫 interdit | Contredit la grille pricing (free / pilot / operations / enterprise) |
| « Disponible dans <pays/store> » | 🚫 interdit | Uniquement si le pays/store est réellement couvert et vérifié |
| Chiffres (tests, modules, apps, pays) | ⚠️ jamais sans date | Voir registre `docs/REFERENTIEL_PRODUIT/METRIQUES_VITRINE.md` |

## 4. Cible par audience (pitch court)

| Audience | Message | Support |
|---|---|---|
| Prospect PME terrain | « Pilotez votre entreprise depuis le téléphone — paie, pointage, RH » | Vitrine Next.js, démo |
| Pilote DZ | « Paie algérienne (IRG/CNAS) fiable, multi-sites » | `docs/DEMO_KIT_DZ.md`, recettes |
| Investisseur / partenaire | « Company OS open source mobile-first, marché PME Afrique/Europe/TR/ME » | `docs/GOTO_MARKET/` |
| Communauté OSS | « MIT, DDD, 7 apps Flutter + API Laravel, spec-first » | README, site gh-pages |

## 5. Application aux surfaces (état au 2026-09-09)

| Surface | Chemin | Conforme ? |
|---|---|---|
| README racine | `README.md` | ⚠️ wording EN historique — à aligner (décision positionnement EN) |
| Site statique | `site/gh-pages/index.html` | ⚠️ métriques non datées — corriger via le registre (#7081) |
| Vitrine Next.js | `front/web/src/modules/vitrine/` + `(landing)/` | ✅ discours principal FR |
| Stores / contenus GTM | `docs/GOTO_MARKET/`, `docs/GTM/` | ✅ à vérifier au rituel mensuel (P03) |

## 6. Gardiens

- Gardien vitrine : BC-27 SHOWCASE (agents vitrine) ; validation finale : fondateur.
- Toute PR modifiant une promesse publique ajoute une ligne « impact copie » (P03 §3.6).
