# PROTOCOLE 03 — Présentation & vitrine

*Statut : Actif v1.0 — 2026-09-09 | Porteur : fondateur/PM + commercial | Revue : MENSUELLE fin de mois, cf. `docs/PROTOCOLS/00_INDEX.md`*

> Pourquoi : le projet doit pouvoir être présenté à tout moment — site vitrine, README, pitch, démos, dossier commercial — avec les BONS TERMES, sans contredire les autres surfaces ni exposer de chiffres invérifiables. Aujourd'hui trois discours coexistent (README, site statique, dossier go-to-market) et des métriques circulent sans date de mesure. Ce protocole pose une source unique par type d'information, un lexique vitrine, des pitchs par audience, un registre de métriques datées et un rituel de mise à jour mensuelle exécutable.

## 1. Objectif & périmètre

**Objectif.** Garantir qu'en toute situation de présentation (prospect, pilote, investisseur, partenaire, recruteur, communauté open source), le discours tenu est conforme à la source de vérité du moment, dans la langue attendue, avec des chiffres datés et des liens joignables.

**Périmètre couvert.** Surfaces de présentation publiques et commerciales listées au §2 ; discours de référence et procédure de départage (§3) ; lexique vitrine (§4) ; pitchs par audience (§5) ; chiffres datés et registre des métriques (§6) ; rituel mensuel (§7). Ce protocole s'applique aussi aux contenus générés par des agents (issues, PR, réponses commerciales) qui reprennent le discours produit.

**Périmètre exclu.** Les décisions de positionnement et de marque (au fondateur, voir décisions ouvertes §8) ; la rédaction des contenus eux-mêmes ; la réalisation des assets. Ce document ne crée ni ne remplace aucun contenu marketing.

## 2. Surfaces de présentation

| # | Surface | Chemin | Langue | Discours actuel (constat 2026-09-09) | Statut |
|---|---|---|---|---|---|
| S1 | Vitrine Next.js (portail Vercel) | `front/web/src/app/(landing)/` (30 pages) + données `front/web/src/modules/vitrine/data/` | FR par défaut (i18n fr/en/ar/tr) | Orientée parcours et conversion ; pricing free/pilot/operations/enterprise aligné sur `PlanSeeder` (ADR-0014) | LIVE `gestionemployer-backend.vercel.app` (`docs/ops/DOMAINS.md`) |
| S2 | Site statique GitHub Pages | `site/gh-pages/index.html` | EN (bascule FR embarquée) | « Leopardo RH — Open-source HR & Payroll OS » ; stats non datées : 18 modules, 700+ endpoints, 1 900+ tests, 5 apps Flutter, 21 pays | LIVE `kitokoh.github.io/leopardo-hr` ; discours et chiffres divergents |
| S3 | README racine | `README.md` | EN | « Open-source business operations platform for multi-site and field-based companies » ; section « Project status » | LIVE GitHub ; pose déjà la règle des métriques datées (non appliquée partout) |
| S4 | Dossier go-to-market | `docs/GOTO_MARKET/README.md` | FR | « Leopardo HR — Mobile-First Company OS » ; se déclare source de vérité business | Sections 02-14 et 99 absentes, cf. `docs/GOTO_MARKET/GOTO_MARKET_AUDIT.md` |
| S5 | Positionnement & messaging | `docs/GOTO_MARKET/2026_MARKET_LAUNCH_COMPANY_OS/02_POSITIONNEMENT_ET_MESSAGING.md` | FR | Catégorie « Mobile-First Company OS » ; pitchs 15 s / 60 s ; « Leopardo n'est pas… / Leopardo est… » ; objections ; slogans | Référence business actuelle |
| S6 | Assets marketing | `docs/GOTO_MARKET/ASSETS_PRODUCTION/` (LANDING_PAGE_SPEC, brochure, one-pager, pitch deck, scripts vidéo, prompts) + `assets/{branding,design,screenshots,videos}/` | FR | Specs et supports destinés aux surfaces live | À rattacher explicitement aux surfaces S1-S5 |
| S7 | Démos & comptes | `docs/DEMO_ACCOUNTS.md`, `docs/DEMO_KIT_DZ.md`, `docs/DEMO_PILOTE_CRM.md`, `dev-hub/demo/README.md`, `docs/GUIDES/GUIDE_TESTEURS_PILOTES.md` | FR/EN | Comptes par persona ; kits DZ et CRM ; seeder `DemoCompanySeeder` | `demo.leopardo-rh.com` en NXDOMAIN (`dev-hub/demo/README.md` vs `docs/ops/DOMAINS.md`, #3452) |
| S8 | Référentiels produit | `docs/REFERENTIEL_PRODUIT/` (APV.md, ROADMAP.md, COULEURS.md), glossaire `docs/dossierdeConception/14_glossaire/21_GLOSSAIRE_ET_DICTIONNAIRE.md` | FR | Pitch APV (« l'app s'ouvre sur une conversation… ») distinct du pitch GTM ; couleurs et roadmap canoniques | Internes ; sources des termes et du visuel |

Constat : trois discours concurrents (S2, S3, S4), deux vitrines actives (S1, S2), une règle de métriques datées déjà écrite dans S3 mais pas étendue aux autres surfaces, et aucun lexique produit central (le glossaire S8 est technique et daté de mars 2026).

## 3. Règle de la source unique & procédure de départage

### 3.1 Source unique par type d'information

| Type d'information | Source canonique | Conséquence |
|---|---|---|
| Positionnement & messaging FR | S5 (`02_POSITIONNEMENT_ET_MESSAGING.md`) | Toute nouvelle formulation s'y rattache ou la modifie d'abord, jamais l'inverse |
| Positionnement EN | Aucune — décision ouverte DO-1 | Interdiction d'inventer un 4e discours EN en attendant la décision fondateur |
| URLs, domaines, environnements | `docs/ops/DOMAINS.md` | Registre machine-checkable ; ne pas diffuser d'URL hors registre |
| Prix, plans, plafonds | `api` (`PlanSeeder.php` / `PlanCode.php`, ADR-0014), miroir UI `front/web/src/modules/vitrine/data/pricing.ts` | Le dossier GTM ne fixe pas les montants |
| Chiffres & métriques | Registre §6 (mesures datées) | Jamais copiés depuis une surface vers une autre |
| Marque, couleurs, visuel | `docs/REFERENTIEL_PRODUIT/COULEURS.md` + `assets/branding/` | Lexique §4 à valider (DO-6) |
| Vocabulaire produit | `docs/REFERENTIEL_PRODUIT/APV.md` + glossaire technique S8 | Termes métier et architecture |

### 3.2 Procédure de départage quand deux surfaces divergent

1. Identifier le type d'information en conflit et remonter à sa source canonique (tableau 3.1).
2. Si une surface contredit sa source : la surface est en erreur. Ouvrir une issue de correction « vitrine » (§7.3) et corriger la surface — jamais l'inverse — sauf si la source est elle-même dépassée (passer alors à l'étape 3).
3. Si deux sources de même rang divergent, ou si le conflit porte sur un choix de positionnement : ne pas trancher soi-même. Ouvrir une décision fondateur formulée en une question (options, surfaces impactées, préférence GTM le cas échéant), la tracer en §8, et geler tout nouveau contenu porteur du terme en conflit jusqu'à la décision.

Arbitres par domaine : positionnement, marque, lexique = fondateur ; architecture, métriques techniques = PM / responsable technique ; URLs = registre `docs/ops/DOMAINS.md` (garde existante).

## 4. Lexique vitrine (termes autorisés / interdits / ambigus — à valider)

Tableau de propositions, à valider par le fondateur (DO-6). Tant qu'il n'est pas validé, un terme marqué « ambigu » ne doit pas être utilisé dans un nouveau contenu.

| Terme | Statut proposé | Usage autorisé | À ne pas faire | Justification |
|---|---|---|---|---|
| « Company OS » / « Mobile-First Company OS » | Autorisé (FR) | Positionnement et messaging stratégiques (S4, S5) | Le décliner en EN avant DO-1 | Concurrence avec « business operations platform » (S3) et « HR & Payroll OS » (S2) |
| « Leopardo » / « Leopardo RH » / « Leopardo HR » | Ambigu | Première mention « Leopardo » ; « Leopardo RH » en FR, « Leopardo HR » en EN (traduction) | Mélanger RH et HR dans une même surface ; GTM FR écrit « Leopardo HR » | Variantes non normalisées constatées dans S2, S3, S4 et l'i18n de l'app |
| « cockpit mobile » | Autorisé (marketing) | Métaphore de positionnement (« le cockpit mobile qui connecte présence, paie… ») | L'employer dans les docs techniques ou le glossaire | Registre commercial, pas produit |
| « app mobile » | Autorisé | Terme générique désignant les applications Flutter | « application » seul quand on parle du mobile | Précision nécessaire dans les surfaces FR |
| « pilote » | Autorisé | Client accompagné sur un périmètre réel (dossier `docs/pilotes/`, S7) | « beta tester » | « bêta » suggère un logiciel inachevé ; le pilote est un usage réel suivi |
| « module » | Autorisé | Capacité métier isolée (bounded context) | « fonctionnalité » pour un module complet | Vocabulaire DDD (S3 product map, APV) |
| « SIRH » | Interdit | — | « un SIRH », « SIRH mobile » comme catégorie | Positionnement : « pas un SIRH desktop adapté au mobile » (S5) |
| « PME terrain » | Autorisé | Audience ICP1 : 20-250 salariés, multi-sites (sécurité, BTP, logistique, nettoyage, restauration, retail, maintenance) | « PME » seul dans les pitchs | Cible GTM (S5) |
| « endpoint » | Nuancé | Surfaces techniques EN uniquement | « endpoint » dans la vitrine FR | Anglicisme technique ; préférer « API », « route » |
| « paie » / « payroll » | Nuancé | « paie », « bulletins » en FR ; « payroll », « payslips » en EN | Mélanger les langues dans une surface i18n | Règle générale des surfaces multilingues |

## 5. Pitchs par audience

Règle : tout pitch oral s'appuie sur un support écrit existant ; aucun chiffre nouveau en improvisation (cf. §6).

| Audience | Pitch de référence | Supports | Démo / comptes |
|---|---|---|---|
| Prospect PME terrain (ICP1) | Pitchs 15 s / 60 s et messages par persona (S5) | S6 : `LEOPARDO_ONE_PAGER.md`, `LEOPARDO_BROCHURE_PLAN.md` ; page vitrine `front/web/src/app/(landing)/` | Parcours vitrine Vercel (S1) ; personas de `docs/DEMO_ACCOUNTS.md` |
| Cabinet RH / comptable multi-clients (ICP2) | Messages ICP2 de S5 (multi-client, preuves, exports) | One-pager S6 ; `docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md` | Comptes démo par persona RH (S7) |
| Pilote algérien (DZ) | Kit démo DZ réaliste | `docs/DEMO_KIT_DZ.md` (F-23 #1553) : société fictive, 30 employés, paie DZD clôturée | Comptes `demo-dz` (mot de passe commun indiqué dans le kit) |
| Investisseur | Pas de dossier GTM dédié (sections 11_INVESTORS / 99_EXECUTIVE absentes, cf. S4) | `docs/GOTO_MARKET/LEOPARDO_STRATEGIC_ANALYSIS.md` ; S6 `LEOPARDO_PITCH_DECK_OUTLINE.md` | Décision ouverte DO-7 |
| Partenaire intégrateur | Pitch partenaire de S5 + guide intégration | `api/openapi.yaml` ; `docs/GUIDES/GUIDE_INTEGRATION_PARTENAIRES.md` | Environnement local (seeder `DemoCompanySeeder`, `dev-hub/demo/`) |
| Recruteur | Pages carrières et blog de la vitrine | `front/web/src/app/(landing)/careers`, `(landing)/blog` | Vitrine S1 |
| Communauté open source | README + CONTRIBUTING + section « Developers » de S2 | `README.md`, `CONTRIBUTING.md`, `site/gh-pages/index.html` | GitHub ; démo locale uniquement (pas `demo.leopardo-rh.com`, NXDOMAIN) |

**Quick-card agent commercial / marketing.** Par où commencer : lire (1) S5 pour les messages, (2) le registre §6 pour les chiffres autorisés, (3) `docs/ops/DOMAINS.md` pour les URLs joignables, (4) `docs/GOTO_MARKET/README.md` pour la carte du dossier. Quel compte pour quelle audience : prospect PME = personas de `docs/DEMO_ACCOUNTS.md` ; pilote DZ = comptes `demo-dz` de `docs/DEMO_KIT_DZ.md` ; pilote CRM = tenants `crm-pilot-alpha`/`crm-pilot-beta` de `docs/DEMO_PILOTE_CRM.md` ; démo technique = seeder `DemoCompanySeeder`. Interdits : `demo.leopardo-rh.com` et les adresses `@leopardo-rh.com` (NXDOMAIN, #3452), tout chiffre non daté, tout terme du §4 marqué interdit ou ambigu.

## 6. Règle des chiffres datés & registre des métriques

**Règle (étendue à toutes les surfaces).** Aucun chiffre de mesure — modules, endpoints, tests, applications, pays, couverture, taux — ne peut figurer sur une surface de présentation (S1-S7, pitchs, dossier commercial, assets) sans (a) une date de mesure et (b) une source de mesure. Format imposé : « 1 900+ tests backend automatisés (mesure du 2026-09-09, suite de tests `api`) ». Cette règle généralise l'avertissement de la section « Project status » de `README.md` (« should not be copied into long-lived marketing claims without updating their measurement date ») que S2 viole aujourd'hui. Les badges dynamiques (CI, couverture) sont tolérés car régénérés par le CI, mais leur valeur affichée doit rester traçable vers une mesure datée.

**Registre des métriques à régénérer** (colonne « constat » vérifié le 2026-09-09) :

| Métrique | Affichage actuel | Où mesurer | Constat au 2026-09-09 |
|---|---|---|---|
| Modules métier | « 18 DDD business modules » (S2) | `api/app/Modules` (convention DDD) | 27 dossiers présents — périmètre « module » à définir (DO-4) avant tout chiffre |
| Endpoints API | « 700+ » (S2, section developers) | `api/openapi.yaml` | ~738 chemins de niveau 1 comptés — script de comptage à fixer |
| Tests backend | « 1 900+ » (S2) ; badge « coverage 71 % » (S3) | suite de tests `api` (phpunit), gate de couverture | Non re-vérifié — à régénérer à l'étape 1 du rituel |
| Apps Flutter | « 5 » (S2) | `front/mobile_apps` | 8 dossiers d'apps (dont `leopardo_core`, probablement partagé) — « app publiée » vs « en dev » à définir (DO-4) |
| Pays | « 21 » au catalogue paie (S2) | catalogue paie `api` ; registre essai `GET /api/v1/supported-countries` (miroir `front/web/src/modules/vitrine/data/supported-countries.ts`, arrêté au 2026-08-16) | Deux périmètres distincts (catalogue paie ≠ pays éligibles à l'essai) à ne pas confondre |
| Micro-chiffres vitrine | « 99,9 % précision » (données `features.ts`, S1) | équipe produit | À dater ou retirer des surfaces publiques |

Règle de copie : un chiffre ne se copie jamais d'une surface à l'autre ; il se copie depuis sa mesure (colonne « où mesurer »), datée le jour de la copie.

## 7. Rituel de mise à jour mensuelle (checklist exécutable)

Ancré au rituel fin de mois du fondateur (`docs/PROTOCOLS/00_INDEX.md`). Quand : dernier jour ouvré du mois. Qui : PM/fondateur + commercial (1 h) puis validation fondateur (15 min). À la fin du mois de septembre 2026, exécuter ce rituel pour la première fois sur la base du §8.

1. **Régénérer les chiffres.** Mesurer chaque métrique du registre §6 à sa source (« où mesurer »), consigner valeurs et date dans le registre. Livrable : registre §6 à jour.
2. **Comparer les surfaces.** Parcourir S1-S7 : discours conforme à la source du §3.1 ? chiffres datés et exacts ? URLs joignables (`docs/ops/DOMAINS.md`) ? termes du §4 respectés ? Livrable : liste des écarts.
3. **Ouvrir une issue de correction « vitrine » par écart.** Modèle : surface concernée, écart constaté, source de vérité, date du constat. Aucun nouveau contenu reprenant l'écart ne doit être publié tant que l'issue n'est pas corrigée.
4. **Valider les pitchs.** Relire §5 et S5 (positionnement, objections, slogans) : toujours exacts au regard du produit et du marché ? Dater la relecture dans S5. Livrable : pitchs validés du mois.
5. **Tracer le rapport daté.** Créer `docs/PROTOCOLS/rapports/YYYY-MM_vitrine.md` : résultats des étapes 1-2, issues ouvertes (étape 3), pitchs validés (étape 4), décisions nouvelles à remonter au fondateur. Transmettre le rapport à la revue de fin de mois (`docs/PROTOCOLS/00_INDEX.md`).

## 8. État des lieux au 2026-09-09 & décisions ouvertes

**Constat initial (vérifié par lecture le 2026-09-09).**

- C1. Trois discours concurrents : S3 « Open-source business operations platform » (EN), S2 « Open-source HR & Payroll OS » (EN), S4/S5 « Mobile-First Company OS pour PME terrain » (FR). Aucune version EN validée du positionnement GTM.
- C2. Nom de marque flottant : « Leopardo » (S3), « Leopardo RH » (S2, i18n FR de l'app), « Leopardo HR » (S4/S5 y compris en français).
- C3. S2 affiche cinq métriques sans date (18 modules, 700+ endpoints, 1 900+ tests, 5 apps, 21 pays) ; le décompte brut des dossiers (`api/app/Modules` : 27 ; `front/mobile_apps` : 8) suggère des périmètres à clarifier, pas nécessairement des valeurs fausses.
- C4. `dev-hub/demo/README.md` pointe `demo.leopardo-rh.com` (NXDOMAIN, #3452) et des adresses `@leopardo-rh.com` non joignables ; risque de reprise en démo publique.
- C5. Deux vitrines actives (S1 Vercel FR, S2 GitHub Pages EN) ; S3 relie « Product site » vers S2. Risque de double discours et de maintenance dédoublée.
- C6. `docs/GOTO_MARKET/README.md` déclare lui-même les sections 02-14 et 99 absentes : aucun dossier investisseur ni playbook de vente structuré à ce jour (voir `GOTO_MARKET_AUDIT.md`).
- C7. Surfaces annexes non auditées ce jour : `docs/GOTO_MARKET/SOCIAL_MEDIA_PITCHES.md` et fiches annuaires `docs/GOTO_MARKET/platform-submissions/` — à passer en revue au premier rituel.
- C8. Le dossier `docs/PROTOCOLS/` est créé avec le présent document (v1.0) ; l'index `00_INDEX.md` et les autres protocoles de la série sont attendus dans la même passe de septembre 2026.

**Décisions ouvertes à trancher par le fondateur (à remonter au PM).**

| # | Décision | Enjeu | Blocage tant que non tranchée |
|---|---|---|---|
| DO-1 | Positionnement EN de référence (S3, S2, traduction EN de S5, ou nouveau) | Discours EN des surfaces live | Tout nouveau contenu EN porteur du terme en conflit |
| DO-2 | Nom de marque et règle RH/HR par langue | C2 | Usage du nom dans les nouveaux contenus |
| DO-3 | Sort de S2 `site/gh-pages/` (aligner, fusionner dans S1, archiver) | C5 | Mise à jour des stats et du discours EN |
| DO-4 | Périmètres de mesure des métriques vitrine (module, app publiée, pays) | C3, registre §6 | Publication de tout chiffre sur une surface |
| DO-5 | Environnement de démo publique à référencer (NXDOMAIN #3452 ; `DEMO_MODE_ENABLED` inactif en prod) | C4, S7 | Tout lien démo publié |
| DO-6 | Validation du lexique §4 | Cohérence des termes | Emploi des termes ambigus |
| DO-7 | Création des dossiers GTM investisseur / partenaire (sections 02-14, 99) | C6, pitch investisseur | Pitch investisseur complet |
