# Spike BC-27 — Éditeur visuel de la vitrine : builder open-source vs éditeur de sections maison

- **Date :** 2026-09-08
- **Statut :** décision rendue (spike V-SPIKE, issue #6869) — à entériner dans l'issue #6869
- **Consommateur :** V-EDITOR #6870 (éditeur de sections côté admin-dashboard)
- **Références :** spec `docs/specifications/SOLUTION_SITE_VITRINE.md` (§4 décisions, §6 API), EPIC #6862, contrat de sections V-SECTIONS-API #6866, thèmes V-THEMES #6868, tokens design `docs/specifications/DESIGN_SYSTEM_TOKENS.md`

---

## 1. Question posée

Le tenant (admin/responsable, non technique) compose la page de sa vitrine par **sections typées** (v1 : `hero | features | produits | gallery | testimonials | contact | footer`), chacune validée par un **JSON Schema versionné** (#6866), rendue **côté serveur** par un moteur de thèmes maison (#6868). La seule inconnue d'UX : **quel outil d'édition** dans l'admin-dashboard (Vue 3.5 + Tailwind + Pinia, `front/admin-dashboard`) permet de composer/éditer/réordonner ces sections avec aperçu ?

Deux familles :
- **O1 — Builder open-source headless** (GrapeJS ou équivalent) intégré dans l'admin.
- **O2 — Éditeur de sections maison** : liste de blocs typés + panneau d'édition par type + aperçu, adossé au contrat #6866.

La spec acte déjà : « v1 = moteur de sections + thèmes **maison** (rendu serveur) ; pas de CMS embarqué » et « l'éditeur retenu **exporte vers notre contrat de sections** — jamais l'inverse » (§4.1-4.2). Le spike doit trancher l'outil d'édition dans ce cadre.

## 2. Contraintes non négociables (tirées de la spec et du repo)

| # | Contrainte | Source |
|---|---|---|
| C1 | Le contenu vitrine = liste ordonnée de **sections typées** (pas de HTML libre), validée **serveur** par JSON Schema versionné | #6866 |
| C2 | **Contenu séparé de la présentation** : le rendu appartient au moteur de thèmes serveur (3 thèmes v1, variables) | #6868 |
| C3 | **i18n fr/en/ar/tr** du contenu + rendu public selon Accept-Language ; l'arabe impose le **RTL** | V-I18N #6874 |
| C4 | Public = **0 donnée interne** ; rendu SSR Laravel indexable ; anti-XSS systématique (échappement HTML) | #6867/#6875/#6868 |
| C5 | L'admin-dashboard est Vue 3.5 + Tailwind ; **aucune** lib de drag & drop / éditeur aujourd'hui ; politique repo : toute dépendance ajoutée exige une décision | audit repo 2026-09-08 |
| C6 | Pas de CMS open-source embarqué (surface sécurité/maintenance disproportionnée) — décision actée | spec §4.1 |

## 3. Option O1 — GrapeJS (évalué en chef de file des builders headless)

### 3.1 Faits établis (au 2026-09-08)
- **Licence/communauté** : MIT, projet open-source actif et mature (utilisé en production par de nombreux éditeurs de sites), headless (pas de backend imposé), écosystème de plugins officiels/communautaires (blocs, style manager, layers…).
- **Modèle de document** : GrapeJS manipule un **document HTML/CSS** (composants, styles par composant, `editor.getHtml()/getCss()` ou `getProjectData()` JSON). C'est un éditeur de **pages libres**, pas un éditeur de données typées.
- **Intégration Vue** : pas de binding officiel première-classe ; intégration manuelle (instanciation sur un conteneur, `editor.destroy()` au cycle de vie) ou wrappers communautaires au suivi irrégulier.
- **Rendu** : la sortie est un HTML+CSS autonome ; si on l'adopte, **c'est GrapeJS qui devient le moteur de rendu** (ou l'on exporte son HTML dans nos pages).
- **Sécurité** : le contenu édité est du HTML/CSS — XSS si non assaini ; style inline abondant (contredit la politique de styles limités du contrat de sections et les tokens design).

### 3.2 Analyse contre nos contraintes
| Contrainte | Verdict O1 | Détail |
|---|---|---|
| C1 sections typées validées serveur | **Rouge** | GrapeJS produit du HTML libre. Reconstruire nos 7 types *à partir* de son JSON/HTML = mapping inverse fragile (heuristique, cas limites, perte silencieuse de données à chaque évolution de schéma). On exporterait vers notre contrat « à l'envers » de son modèle — exactement le sens que la spec interdit (§4.2). |
| C2 thèmes serveur | **Rouge** | Les styles GrapeJS (inline/par composant) court-circuitent le moteur de thèmes et les variables ; le même contenu ne se re-rendrait pas proprement sous nos 3 thèmes. |
| C3 i18n/RTL | **Orange** | GrapeJS a une UI localisable et un support RTL de l'éditeur, mais le **contenu** multilingue par section (structure par locale) n'est pas son modèle ; on gérerait la locale hors de lui → double source de vérité. |
| C4 XSS/0 donnée interne | **Orange** | Sanitisation HTML à poser (DOMPurify ou similaire) + politique CSP à assouplir pour le canvas — surface nouvelle. Gérable, mais coûteuse et permanente. |
| C6 pas de CMS embarqué | **Rouge** | Un builder de pages libres EST un CMS d'édition embarqué (le « CMS dans le CMS » que la décision #6862 exclut). |
| Coût d'intégration v1 | **Rouge** | Adapter + sanitizer + mapping ↔ contrat + double modèle (GrapeJS vs nôtre) + surcouche i18n : effort ≥ éditeur maison, avec une dette de friction permanente. |

## 4. Option O2 — Éditeur de sections maison (recommandé)

### 4.1 Forme
Dans l'admin-dashboard (`src/views/showcase/` à créer en V-EDITOR #6870) :
- **Liste des sections** de la page (ordre = ordre de rendu) ; actions : ajouter (choix parmi les 7 types), dupliquer, supprimer, **réordonner** (drag & drop natif HTML5 — 7 types en v1, pas de lib nécessaire) ;
- **Panneau d'édition par type** : un formulaire dédié par type de section (champs texte, médias via l'upload existant V-MEDIA #6872, choix produits BC-28 pour `produits`), valeurs **validées côté client contre le même schéma** que le serveur (#6866), i18n par locale (onglet/selecteur de langue, C3) ;
- **Aperçu** : iframe (ou `srcdoc`) consommant l'API de rendu/aperçu privée — « vraies données, vrais endpoints » (règle admin) ;
- **Publication 1-clic** (#6871).

### 4.2 Pourquoi ça gagne sur nos contraintes
- **C1 : alignement structurel.** L'éditeur manipule directement la même structure que l'API et le rendu : une section = un objet JSON typé. Zéro mapping, zéro perte ; le JSON Schema sert de **contrat unique** (client + serveur + rendu).
- **C2 : thèmes préservés.** L'éditeur ne stocke que du **contenu sémantique + variables** ; la présentation reste l'affaire exclusive du moteur de thèmes. Un même contenu se re-rend sous « Industrie », « Service » ou « Commerce ».
- **C3 : i18n/RTL naturels.** Les champs sont stockés par locale dans la structure de section ; l'aperçu et le rendu public gèrent la direction (RTL) sans combattre un modèle HTML tiers.
- **C4 : sécurité maîtrisée.** Pas de HTML libre : champs scalaires/objets typés, échappement au rendu (Blade/échappement standard), validation serveur par schéma — la politique anti-XSS existante du repo reste inchangée, aucune CSP à assouplir.
- **C6 : périmètre borné.** 7 types × formulaires dédiés = surface d'UI connue et finie, maintenable par l'équipe existante, sans dépendance critique tierce.

### 4.3 Coûts honnêtes
- UI à construire entièrement (listes, formulaires par type, aperçu, drag) — c'est le cœur de V-EDITOR #6870 ;
- L'éditeur maison est **moins puissant** qu'un canvas libre pour des mises en page exotiques — hors périmètre v1 (7 types, 3 thèmes, charte produit).

## 5. Décision

> **V1 = éditeur de sections maison (O2).** GrapeJS (et les builders headless en général) est **écarté pour le v1** : son modèle de document (HTML libre) est orthogonal au nôtre (sections typées validées par schéma, rendu par thèmes serveur, contenu multilingue), et son adoption imposerait un double modèle de contenu + sanitisation HTML + dépossession du rendu — pour un besoin v1 qui est une liste finie de 7 types.
>
> **Ré-évaluation documentée (déclencheurs)** — GrapeJS (ou équivalent) sera re-évalué si l'un de ces besoins émerge :
> 1. des **sections libres** (landing page custom par tenant, layout non couvert par les thèmes) ;
> 2. une demande forte d'**édition WYSIWYG de texte riche** dans le contenu ;
> 3. un **produit vitrine généraliste** (BC-27 devenant un builder multi-usage) — alors l'investissement d'intégration se justifie, avec sanitizer + export strict vers le contrat.
> Dans ce cas : re-spike court (intégration Vue, sanitisation, export) avant toute dépendance.

## 6. Conséquences pour V-EDITOR (#6870)

- Vue `ShowcaseEditorView` : liste des sections + réordonnancement natif (pas de lib de drag) + panneau d'édition par type + aperçu via endpoint privé ;
- **Aucune nouvelle dépendance** admin-dashboard (pas de GrapeJS, pas de drag-drop lib, pas de DOMPurify) — la politique « dépendance = décision » reste verte ;
- Les formulaires par type consomment le même contrat JSON Schema que l'API #6866 (une seule source de vérité) ;
- UI dans les catalogues i18n admin existants (`front/admin-dashboard/src/i18n/locales`) ; composants réutilisables (inputs, upload) depuis `src/components/common`.

## 7. Vérification du critère d'acceptation de #6869

- ✅ Rapport écrit (`docs/architecture/`) avec décision commentée et critères de ré-évaluation ;
- ✅ Aucune dépendance ajoutée à un package (aucune modification de `package.json`/`composer.json`) ;
- ✅ Décision transmise à V-EDITOR #6870 (§6).
