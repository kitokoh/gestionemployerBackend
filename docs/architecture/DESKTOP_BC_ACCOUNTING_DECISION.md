# Décision — Client desktop BC-08 Accounting (P06 §2)

| | |
|---|---|
| **Statut** | **Brief de décision — arbitrage PM requis** |
| **Date** | 2026-09-10 |
| **Objet** | Répondre à la checklist d'opportunité desktop du protocole P06 §2 (issue #7055) |
| **Décideur** | PM (orientations + acceptation des coûts) |
| **Références** | `docs/PROTOCOLES/P06_DESKTOP_DISTRIBUTION.md` §2, `docs/PROTOCOLES/P01_VALIDATION_MARCHE.md`, issue #7056 (pipeline desktop), issue #7106 (spec pilote), README carte produit |

> Ce document **prépare** la décision : il rassemble les faits et une recommandation. La clôture de
> #7055 (GO → ouverture du chantier / NO-GO → motif + re-décision à la revue mensuelle) appartient au PM.

---

## 1. La question (P06 §2)

Un client desktop est-il justifié pour BC-08 Accounting — et à quelles conditions ?

Rappel de la règle P06 : *le desktop n'est pas un réflexe, c'est une décision* ; sont éligibles les
verticals à workflow intensif clavier/saisie, impression locale, offline durable ou multi-fenêtres.

## 2. Réponses motivées à la checklist

### 2.1 Besoin de poste fixe confirmé (comptable en saisie intensive) ? → **Oui, partiellement**

- Le README (carte produit) cite explicitement *« intensive accounting »* comme workflow desktop justifié.
- Les écrans compta actuels (pièces, journaux, rapprochements, exports bancaires) reposent sur de la
  **saisie tabulaire** et des **exports**, usages où le clavier + écran large dominent.
- Réserve : le volume réel de saisie par utilisateur n'est pas mesuré (absence de télémétrie d'usage
  par écran). À objectiver pendant un pilote, pas avant.

### 2.2 Valeur ajoutée démontrable vs web / PWA ? → **Partielle**

| Besoin | Web/PWA aujourd'hui | Apport desktop réel |
|---|---|---|
| Saisie tabulaire massive | Possible (clavier) | Ergonomie multi-fenêtres, raccourcis, impression locale directe |
| Impression locale (bulletins, journaux) | Via navigateur | Impression silencieuse / formats, choix d'imprimante sans dialogue navigateur |
| Offline durable | Limité (web-offline existe pour le pointage) | Utile en zone à faible connectivité — **mais l'edge Docker couvre déjà l'offline-first** côté client |
| Sécurité poste | Navigateur isolé | Rien de décisif (au contraire : surface de mise à jour à gérer) |

Conclusion : la valeur se concentre sur **ergonomie de saisie + impression**, pas sur l'offline
(déjà couvert par `edge/`). Un PWA soigné couvrirait une partie du besoin à coût quasi nul.

### 2.3 App source identifiée ? → **Oui : `leopardo_accounting`… mais les runners manquent**

- App cible : `front/mobile_apps/leopardo_accounting` (déclarée Android uniquement aujourd'hui).
- Les 5 autres apps du monorepo disposent déjà des runners `windows/` + `macos/` ; **accounting doit
  d'abord générer ses scaffolds desktop** (c'est l'objet de #7106).
- Console/back-office déjà disponible via `front/admin-dashboard` (Vue) — à ne pas dupliquer.

### 2.4 Coûts acceptés ? → **À trancher par le PM (poste non technique)**

- Signature Windows (certificat Authenticode, souvent payant à l'année) ;
- Notarisation macOS (compte Apple Developer payant + `notarytool` dans la CI) ;
- Runners CI macOS (minutes GitHub facturées plus cher) ;
- Maintenance du canal de mise à jour (obligatoire : une app desktop sans MAJ devient une dette de sécurité).

Note : le protocole P06 prévoit une montée progressive M1 (builds non signés, usage interne) →
M2 (signature Windows) → M3 (notarisation macOS) → M4 (canal de MAJ + recette). M1 est presque gratuit ;
les coûts réels n'arrivent qu'en M2/M3.

### 2.5 Promesse vitrine maîtrisée ? → **Oui, si aucun téléchargement public avant GA**

- Engagements existants : pas de binaire public avant GA (cf. #3257), et la vitrine ne doit pas
  annoncer de client desktop tant que la recette (P01) n'est pas passée.
- Règle P06 : « Le desktop n'est pas un réflexe » ; toute communication vitrine passe par P03.

## 3. Synthèse

| Point | Verdict |
|---|---|
| Besoin poste fixe | 🟡 plausible, non mesuré |
| Valeur vs web/PWA | 🟡 ergonomie/impression oui, offline déjà couvert par l'edge |
| App source | ✅ `leopardo_accounting` (runners desktop à générer — #7106) |
| Coûts | ⏳ décision PM (M1 quasi gratuit, M2/M3 payants) |
| Promesse vitrine | ✅ maîtrisable (aucun binaire public avant GA) |

## 4. Recommandation

**Pilote interne en 2 étapes, sans engagement de coût immédiat :**

1. **GO conditionnel phase M1** — builds desktop non signés `leopardo_accounting` sur runners CI
   (issue #7056 / spec #7106) + recette interne sur 2 postes (1 Windows, 1 macOS) ;
2. **Point de décision PM à l'issue de M1** : si le gain de saisie/impression est démontré et mesuré,
   engager M2 (signature Windows) puis M3 (notarisation macOS) sous budget validé ;
3. **NO-GO à tout téléchargement public** tant que la recette P01 (bloc A/B) n'est pas verte et que
   le canal de mise à jour n'existe pas.

## 5. Conséquences selon la décision du PM

- **GO phase M1** → #7055 fermée avec ce motif ; #7056/#7106 poursuivis ; ajouter une ligne
  « desktop accounting — pilote » à `docs/ARCHITECTURE_STATUS.md` ; recette à la revue mensuelle (P06 §8).
- **NO-GO** → #7055 fermée avec motif ; re-décision inscrite à la revue mensuelle ; le PWA
  (amélioration de la saisie web compta) devient la piste alternative à instruire.

## 6. Pièces à l'appui

- `docs/PROTOCOLES/P06_DESKTOP_DISTRIBUTION.md` (§2 éligibilité, §7 milestones)
- README — carte produit (« intensive accounting », « kiosk operation »)
- Issue #7056 (pipeline desktop Windows/macOS), #7106 (spec pilote + scaffolds)
- `front/mobile_apps/leopardo_accounting` (Plateforme cible : Android seul à ce jour)
- `edge/` (offline-first client déjà disponible)
