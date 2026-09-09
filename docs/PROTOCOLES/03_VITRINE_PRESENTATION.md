# Protocole 03 — Vitrine & présentation du projet

> **Statut : v1.0 — révisé le 2026-09-09 — propriétaire : PM**
> Code des règles : `VIT-*`.
> Le projet doit être **présentable à tout moment, n'importe où** (site, README, démo,
> dossier commercial, réseau social, store, appel client) **avec les bons termes** —
> jamais de jargon interne, jamais de contenu périmé, jamais de contradiction entre
> surfaces.

## 1. Cartographie des surfaces « vitrine / présentation »

| # | Surface | Support | Public visé |
|---|---|---|---|
| V1 | README GitHub (`README.md`) | dépôt (public) | développeurs, prescripteurs open-source |
| V2 | Site produit GitHub Pages (`site/`, workflow `pages-deploy.yml`) | kitokoh.github.io/leopardo-hr | clients, prospects |
| V3 | Vitrine web Next.js (`front/web`) | Vercel (leopardo + leopardo-prod) | prospects SaaS |
| V4 | Vitrines/portails par module | Vercel (leopardo-delivery, -edu, -fuel, -travel, -resto, prod/dev) | clients verticaux |
| V5 | Dashboard admin & démos | Cloudflare Pages (leo-admin / leo-admin-prod), comptes démo | pilotes, support |
| V6 | Docs publiques | `docs/README.md`, dev-hub, OpenAPI, Postman | intégrateurs |
| V7 | Assets & réseau social | `assets/branding/`, `docs/GOTO_MARKET/SOCIAL_MEDIA_PITCHES.md`, `ASSETS_PRODUCTION/` | marché |
| V8 | Store mobile | identités store (voir `front/mobile_apps/README.md`) | utilisateurs finaux |
| V9 | Dossiers commerciaux | `docs/GOTO_MARKET/`, `docs/GTM/`, `docs/STRATEGIE_COMMERCIALE/` | équipe commerciale |

## 2. Les bons termes (message map) — règle VIT-1

**Sources de vérité du wording** : `docs/REFERENTIEL_PRODUIT/APV.md` (manifeste produit)
et `docs/REFERENTIEL_PRODUIT/README.md` (ordre de lecture). En cas de contradiction
avec un PDF de `docs/vision/`, le référentiel prime (règle documentaire existante).

Termes imposés sur **toutes** les surfaces publiques (V1-V4, V7-V8) :

| Contexte | À dire (terme approuvé) | À ne pas dire |
|---|---|---|
| Le produit | « Leopardo — plateforme modulaire open-source de gestion des opérations d'entreprise » ; « Leopardo RH » = l'expérience RH & paie, cœur de la plateforme | « le repo », « l'app », sigles internes |
| Positionnement | « Mobile-first Company OS pour PME terrain (5-250 employés) » | jargon non défini |
| Architecture | « plateforme modulaire, domaines métier cloisonnés (bounded contexts) » | « monolithe modulaire DDD » en surface publique sauf audience technique |
| Modules | « modules métier : RH & paie, pointage & workforce, comptabilité, CRM, marketing, et verticales (livraison, éducation, stations-service, voyage, restauration) » | codes internes (`BC-15 FUEL`), n° d'issues |
| Multi-tenant | « isolation stricte des données par entreprise cliente » | « search_path », « tenants » brut si non défini |
| Statuts | utiliser `docs/REFERENTIEL_PRODUIT/STATUTS.md` | « en cours », « presque fini » sans statut |
| Version | version semver (CHANGELOG.md) affichée si une version est annoncée | « dernière version » sans numéro |
| Distributions | « applications mobiles natives iOS/Android, clients desktop Windows/macOS selon modules » | « APK », « Firebase » sur surface grand public |

Règle générale : **toute surface publique cite les mêmes faits que le README** ; tout
chiffre annoncé (employés, modules, couverture…) est vérifiable dans le dépôt.

## 3. Règles

### VIT-2 — La vitrine ne ment jamais
- Toute fonctionnalité présentée existe sur `main` **et** est déployée dans le volet
  concerné (VIT-6). Une fonctionnalité « bientôt disponible » est étiquetée comme telle
  (statut produit, cf. STATUTS.md).
- Toute capture d'écran est **réelle** (règle : pas de maquette présentée comme
  produit). Les captures de démo sont régénérées par le workflow dédié
  (`scripts/` racine — capture screenshots).

### VIT-3 — Un seul narratif, décliné par audience
Le récit canonique (README.md §What is Leopardo + APV.md) est décliné, pas réécrit :
- **Audience technique** (V1, V6) : architecture, API, open-source, contributeurs.
- **Audience business** (V2, V3, V4, V9) : problèmes résolus, parcours, preuves pilotes
  (`docs/ops/RAPPORT_PILOT_*.md`, `docs/ops/RECETTE_UAT_*.md`), RGPD.
- **Audience marché/store** (V7, V8) : bénéfices utilisateur, captures, conformité store.
Décliner = adapter le ton et la profondeur, **pas** changer les faits ni les termes.

### VIT-4 — Chaîne de mise à jour (toute évolution produit)
1. Le changement de comportement arrive sur `main` (PR) → CHANGELOG.md (obligatoire).
2. **Dans la même PR ou une issue liée `vitrine`** : le PM coche si une surface publique
   est impactée (nouvelle feature vitrine, changement de wording, capture, chiffre).
3. L'agent de la PR met à jour la surface impactée **ou** ouvre une issue `vitrine`
   avec la liste exacte des surfaces à toucher (V1-V9).
4. Une issue `vitrine` ne se ferme que surfaces mises à jour (preuve : lien PR/capture).

### VIT-5 — Garde « présentable à tout moment »
- La vitrine web (V3/V4) passe `lighthouse.yml` + test de liens (aucun lien mort sur les
  pages publiques) en CI, comme aujourd'hui.
- Le README et le site (V1/V2) sont **vérifiés à chaque release** (VT-8) : badges,
  captures `og-banner`, chiffres, liste des modules.
- Un **état « vitrine »** (vert/orange/rouge) est tenu à jour dans le rapport mensuel
  VIT-10 ; rouge = action sous 48 h.

### VIT-6 — Règle d'alignement vitrine ↔ déploiement
Une surface publique qui décrit une fonctionnalité n'est **à jour que si** la
fonctionnalité est visible sur l'URL publique correspondante. L'alignement est vérifié
lors de la revue mensuelle et à chaque release.

### VIT-7 — Vocabulaire interne vs externe
- Sur les surfaces publiques : interdits — n° d'issues/PR, labels BC, noms de branches,
  noms de workflows, « spec kit », jargon infra (Render/Vercel/Cloudflare sauf page
  open-source technique), « agents IA » comme argument marketing non validé par le PM.
- Dans les docs internes (ce corpus, AGENTS.md, docs/) : le jargon interne est normal ;
  la frontière est **la visibilité** de la doc (publique vs privée).

### VIT-8 — Responsable « gardien vitrine »
Un agent (ou le PM) est nommé **gardien vitrine** : il connaît V1-V9, reçoit les issues
`vitrine`, exécute la revue mensuelle et tient l'état VIT-5. C'est lui qui prépare le
rapport de fin de mois.

### VIT-9 — Checklist « avant de présenter le projet à quelqu'un » (usage à la demande)
- [ ] Les 2-3 faits clés du produit sont exacts (README vs code sur `main`)
- [ ] URL démo/site chargées, pas d'erreur visible, pas de lien mort
- [ ] Comptes démo fonctionnels si démo annoncée (`docs/DEMO_ACCOUNTS.md`)
- [ ] Aucun contenu interne visible (issues, TODO, console, données pilotes sensibles)
- [ ] Version/statuts cohérents (VIT-1, STATUTS.md)
- [ ] Chiffres annoncés vérifiables
- [ ] i18n : la langue de présentation est complète sur les écrans montrés

### VIT-10 — Rituel mensuel « fin de mois » (le rendez-vous vitrine)
**Quand** : dernier jour ouvrable du mois (créer l'issue récurrente).
**Qui** : gardien vitrine (prépare) + PM (décide).
**Déroulé** :
1. Parcourir V1 → V9 (checklist VIT-9 sur chacune) ; captures d'écran des surfaces clés.
2. Vérifier VIT-6 (alignement vitrine ↔ déploiement) et VIT-5 (état vert/orange/rouge).
3. Vérifier la fraîcheur du wording (VIT-1) et des statuts produit (STATUTS.md).
4. Ouvrir les issues d'écart (labels `vitrine`, BC si pertinent) avec preuve.
5. Produire le **rapport mensuel d'une page** : modèle ci-dessous ; archivé dans
   `docs/GOTO_MARKET/rapports-mensuels/YYYY-MM.md`.
6. Le PM valide les mises à jour de wording pour le mois suivant (les protocoles eux-
   mêmes sont relus à cette occasion — cf. 00_SOMMAIRE, calendrier).

### Modèle de rapport mensuel vitrine
```markdown
# Rapport vitrine — YYYY-MM
État global : 🟢/🟠/🔴
| Surface | État | Écarts constatés | Issue(s) |
|---|---|---|---|
| V1 README | … | … | #… |
| … (V2..V9) | … | … | #… |
Wording validé pour le mois : [2-3 phrases — pitch approuvé PM]
Faits vérifiés : [chiffres/modules/versions contrôlés ce mois]
Prochaines échéances (vitrine) : [stores, launches, salons, posts]
```

## 4. Sources canoniques

| Sujet | Où |
|---|---|
| Manifeste produit & lois | `docs/REFERENTIEL_PRODUIT/APV.md` |
| Roadmap & statuts | `docs/REFERENTIEL_PRODUIT/ROADMAP.md`, `STATUTS.md` |
| Tokens de marque | `docs/REFERENTIEL_PRODUIT/COULEURS.md` |
| Stratégie de marché | `docs/GOTO_MARKET/` (audit, analyse, pitches, assets) |
| Kit de prospection | `docs/GTM/` (cas clients, templates) |
| Historique vitrine web | `docs/web/vitrine/` (phases 1-7) |
| Identités store | `front/mobile_apps/README.md` |
| Design system | voir protocole 05 |

## 5. Écarts constatés le 2026-09-09 (rattrapage)

1. Aucun **message map / lexique approuvé** centralisé n'existe (le wording est épars
   entre README, GOTO_MARKET, APV) → consolider la table VIT-1 dans
   `docs/REFERENTIEL_PRODUIT/` à la prochaine revue de fin de mois.
2. Aucun **rituel mensuel** de revue vitrine n'existe à ce jour → créer l'issue
   récurrente et nommer le gardien vitrine.
3. Les vitrines/portails Vercel par module (V4) n'ont pas de propriétaire de contenu
   explicite → désigner un responsable par portail (module ↔ BC ↔ agent).
