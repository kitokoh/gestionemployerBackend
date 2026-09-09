# MESSAGE_MAP — Les bons termes de Leopardo (vitrine & présentations)

> Source : `docs/REFERENTIEL_PRODUIT/APV.md` (manifeste, prime) + `README.md`
> + protocole vitrine (`docs/PROTOCOLES/03_VITRINE_PRESENTATION.md`, règles VIT-*).
> Créé le 2026-09-09 (issue #7067). Toute évolution de wording passe par ce fichier,
> relu à la revue mensuelle de fin de mois (VIT-10).

## 1. Le récit canonique (2 phrases)

> **Leopardo** est une plateforme modulaire, open-source et prête pour le SaaS, qui
> aide les entreprises multi-sites et de terrain (5-250 employés) à piloter leurs
> opérations : RH & paie, pointage & workforce, comptabilité, CRM et marketing —
> déclinée en modules métier verticaux (livraison, éducation, stations-service,
> voyage, restauration).
> **Leopardo RH** en est l'expérience fondatrice (RH & paie) ; le reste de la
> plateforme s'articule autour d'un socle commun sécurisé (identité, multi-tenant,
> rôles, API).

Pitch mobile-first (APV) : *« l'app s'ouvre sur une conversation, se déploie module
par module, et apprend à mesure qu'on l'utilise — en partant toujours du téléphone »*.

## 2. Lexique approuvé — « à dire / à ne pas dire »

| Contexte | À dire | À ne pas dire (surfaces publiques) |
|---|---|---|
| Produit | « Leopardo — plateforme modulaire open-source de gestion des opérations d'entreprise » ; « Leopardo RH » = l'expérience RH & paie | « le repo », « l'app », sigles internes |
| Positionnement | « Mobile-first Company OS pour PME terrain (5-250 employés) » | jargon non défini |
| Modules | « RH & paie, pointage & workforce, comptabilité, CRM, marketing + verticales (livraison, éducation, stations-service, voyage, restauration) » | codes internes (`BC-15 FUEL`), n° d'issues/PR, noms de branches |
| Architecture | « plateforme modulaire ; domaines métier cloisonnés ; isolation stricte des données par entreprise cliente » | « monolithe modulaire DDD », « tenants », « search_path » (hors audience technique) |
| Distributions | « applications mobiles natives iOS/Android, clients desktop Windows/macOS selon les modules » | « APK », « Firebase » (grand public) |
| Statuts | statuts de `docs/REFERENTIEL_PRODUIT/STATUTS.md` | « en cours », « presque fini » |
| Versions | version semver du `CHANGELOG.md` si une version est annoncée | « dernière version » sans numéro |
| Couleurs | référentiel `COULEURS.md` (vert RH, ambre finance, bleu sécurité, violet IA) | noms de classes CSS internes |

Règles générales :
1. Une surface publique cite les **mêmes faits que ce fichier et le README** ;
2. tout chiffre annoncé est **vérifiable dans le dépôt** ;
3. le vocabulaire technique interne est autorisé **uniquement** sur les surfaces à
   audience technique (README, docs publiques, OpenAPI).

## 3. Déclinaison par audience

| Audience | Surfaces | Ton | Contenu |
|---|---|---|---|
| Technique / open-source | README GitHub, docs, OpenAPI, dev-hub | factuel, précis | architecture, API, contribution, licence MIT |
| Business (prospects SaaS) | site GitHub Pages, vitrines Next.js (V1-V4), dossiers GTM | bénéfices, parcours | problèmes résolus, pilotes, RGPD, démos |
| Marché / store | stores mobile, assets réseaux sociaux | utilisateur | bénéfices, captures réelles, conformité store |
| Pilotes / support | admin démo, comptes pilotes, UAT | opérationnel | parcours réels, statuts, limitation connues |

Décliner = adapter ton et profondeur, **jamais** changer les faits ou les termes du §1/§2.

## 4. Application par surface (état au 2026-09-09)

| Surface | Support | Statut d'application |
|---|---|---|
| V1 README GitHub | `README.md` | ✅ conforme (récit canonique) — à re-vérifier si ce fichier évolue |
| V2 Site produit | `site/` (GitHub Pages) | ⏳ à auditer à la prochaine revue mensuelle |
| V3 Vitrine Next.js | `front/web` | ⏳ à auditer (wording) |
| V4 Portails verticaux Vercel | leopardo-{delivery,edu,fuel,travel,resto}(-prod) | ⏳ pages de validation pour l'instant (voir DOMAINS.md) |
| V5 Admin & démos | leo-admin(-prod) | interne — pas de wording commercial |
| V6 Docs publiques | `docs/`, OpenAPI | ✅ vocabulaire technique maîtrisé |
| V7 Assets & réseaux sociaux | `docs/GOTO_MARKET/` | ⏳ à auditer |
| V8 Stores mobile | voir `front/mobile_apps/README.md` | ⏳ à auditer |
| V9 Dossiers commerciaux | `docs/GTM/`, `docs/STRATEGIE_COMMERCIALE/` | ⏳ à auditer |

> L'audit des surfaces ⏳ se fait à chaque revue mensuelle (VIT-10) ; les écarts
> constatés deviennent des issues `vitrine`.

## 5. Garde d'usage

- Nouvelle fonctionnalité vitrine → ajouter/changer une ligne du §2 **et** mettre à
  jour la surface impactée dans la même PR (VIT-4) ou ouvrir une issue `vitrine`.
- Contradiction constatée entre deux surfaces → issue `vitrine` P2, le narratif de ce
  fichier fait foi en attendant.
