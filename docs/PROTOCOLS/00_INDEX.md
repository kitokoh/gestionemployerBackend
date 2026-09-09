# PROTOCOLES LEOPARDO — INDEX & MÉTA-PROTOCOLE

**Statut :** Actif v1.0 — 2026-09-09
**Porteur :** fondateur (@kitokoh) / PM
**Revue :** mensuelle, dernier jour ouvré du mois (rituel §5)

> **Pourquoi.** Le projet a accumulé des dizaines de documents de gouvernance de grande
> qualité, mais dispersés (AGENTS.md, CONVENTIONS.md, docs/GOUVERNANCE/, docs/validation/,
> docs/GESTION_PROJET/, docs/GOTO_MARKET/…). Résultat : une même question (« quand est-on
> prêt pour le marché ? », « que fait un nouvel agent le jour 1 ? », « comment présenter le
> projet ? », « que faire de ce que je viens d'apprendre ? ») n'a pas de réponse unique, et
> rien n'impose de re-questionner les règles à date fixe. Cette suite de protocoles répond à
> chaque question par **un document unique, actionnable, relié à l'existant** — et le présent
> index en est le point d'entrée et le mécanisme de révision mensuelle.

## 1. Rôle de ce document

1. **Point d'entrée unique** de la suite : le lecteur (fondateur, PM, agent, nouveau venu)
   part d'ici pour trouver le protocole qui répond à son besoin.
2. **Méta-protocole** : il définit comment un protocole se crée, se modifie, se versionne et
   s'archivera — les règles du jeu s'appliquent aussi aux protocoles eux-mêmes.
3. **Mécanisme de révision** : le rituel mensuel de fin de mois (§5) exige de rejouer chaque
   protocole, de corriger les dérives et d'amender la suite — c'est le « définir les
   protocoles chaque fin du mois » demandé par le fondateur.

## 2. La suite de protocoles

| # | Protocole | Répond au besoin | Porteur | État (2026-09-09) |
|---|---|---|---|---|
| 01 | [Validation & mise sur le marché](01_VALIDATION_MARCHE.md) | « Quels tests et quelles portes pour déclarer une version OK pour le marché ? » | fondateur/PM | Actif v1.0 |
| 02 | [Intégration d'un nouvel agent](02_ONBOARDING_AGENTS.md) | « Un agent qui arrive ne doit pas se perdre ni revivre le temps perdu des autres » | fondateur/PM | Actif v1.0 |
| 03 | [Présentation & vitrine](03_VITRINE_PRESENTATION.md) | « Présenter le projet à tout moment avec les bons termes, partout » | fondateur/PM + commercial | Actif v1.0 |
| 04 | [Capitalisation de l'expérience](04_CAPITALISATION_EXPERIENCE.md) | « Chaque agent transforme ce qu'il a appris en issue/tâche, l'implémente ou la délègue » | chaque agent + agent PM | Actif v1.0 |
| 05 | [Harmonisation du design](05_HARMONISATION_DESIGN.md) | « Une même valeur visuelle = une source unique, aucune surface ne diverge » | fondateur/PM + devs | Actif v1.0 |
| 06 | [Distribution & tests desktop](06_DISTRIBUTION_DESKTOP.md) | « Extraire un .exe / .app par tranche verticale, le tester et le distribuer » | fondateur/PM + devs mobile | Actif v1.0 |
| 07 | [Architecture à deux volets](07_ARCHITECTURE_ENVIRONNEMENTS.md) | « L'architecture dev/prod reste saine, documentée et sans dérive » | fondateur/PM + ops | Actif v1.0 |

Chaque protocole suit le même format : en-tête (statut, porteur, revue) → « Pourquoi »
(douleurs datées et sourcées) → objectif & périmètre → références existantes → règles
numérotées → checklist exécutable → rituel mensuel → état des lieux daté.

## 3. Cycle de vie d'un protocole

1. **Création.** Tout nouveau protocole naît d'une **issue** (titre `[PROTOCOLE] <nom>`,
   contexte + besoin + porteur pressenti), est rédigé en PR (`Closes #issue`), et n'est
   « Actif » qu'après revue du fondateur. Il reçoit le numéro suivant dans la série.
2. **Modification.** Tout amendement passe par une PR qui référence le protocole et
   incrémente sa version (`v1.1`, `v2.0`…) dans l'en-tête + la ligne d'historique de
   l'index (§7). Pas d'édition directe sur `main`.
3. **Statuts.** `Actif` (en vigueur) → `En consolidation` (des règles existent ailleurs et
   doivent le rejoindre) → `Archivé` (obsolète : déplacé dans `docs/archive/` avec un
   renvoi, comme PILOTAGE.md #6698).
4. **Conflit entre documents.** La règle de précédence : code exécutable > workflow/garde
   CI > protocole > document historique. Tout conflit constaté devient une **issue**
   (protocole 04), jamais une note marginale.

## 4. Documents de référence (hors série)

La suite ne réécrit pas l'existant ; elle le consolide. Références majeures à connaître :

- `AGENTS.md` (racine) — guide opérationnel agent (règles, gardes, leçons).
- `.specify/constitution.md` — spec-first, « loi fondamentale » du projet.
- `BRANCH_PROTECTION_REQUIRED.md` — checks requis au merge sur `main`.
- `CONVENTIONS.md` — standards de code, git, tests.
- `docs/validation/` — gates de validation vivants + rapports datés figés.
- `docs/ops/RENDER_DEV_PROD_TOPOLOGY.md` — topologie réelle des deux volets.
- `docs/GOTO_MARKET/` — source de vérité business / positionnement.
- `.github/workflows/README.md` — cartographie des workflows CI/CD.

## 5. Rituel mensuel de fin de mois (LA révision des protocoles)

**Quand :** dernier jour ouvré du mois. **Qui :** fondateur/PM (1 h) + porteurs sollicités
par protocole. **Où :** ouvrir l'issue gabarit
[`revue_mensuelle.md`](../../.github/ISSUE_TEMPLATE/revue_mensuelle.md) et la cocher en direct.

1. **Exécuter les rituels mensuels de chaque protocole** (dans l'ordre) :
   01 §7 (portes marché du mois), 02 §6 (retours d'onboarding), 03 §7 (vitrine & chiffres),
   04 §7 (moisson des leçons), 05 §5 (audit visuel), 06 §7 (portefeuille desktop),
   07 §5 (contrôle d'écart architecture).
2. **Consolider le rapport mensuel** : `docs/PROTOCOLS/rapports/YYYY-MM.md` — synthèse de
   chaque rituel, écarts constatés, issues ouvertes, métriques de santé (§6). Premier
   rapport attendu : fin septembre 2026.
3. **Auditer la conformité** : les règles de la suite sont-elles suivies ? Tout écart
   répété devient un amendement de protocole (issue + PR, §3), pas une tolérance.
4. **Amender** : rejouer les décisions ouvertes (§8), mettre à jour les états des lieux de
   chaque protocole, incrémenter les versions concernées.
5. **Tracer** : mettre à jour l'historique de l'index (§7) et le statut de chaque protocole.

**Sortie du rituel :** rapport mensuel déposé, protocoles amendés si besoin, index à jour,
issues créées pour tout écart non soldé.

## 6. Indicateurs de santé de la gouvernance

| Indicateur | Cible | Mesure |
|---|---|---|
| Protocoles exécutés au rituel mensuel | 7/7 | Cases cochées dans le rapport `rapports/YYYY-MM.md` |
| Écarts doc ↔ réalité non tracés en issue | 0 | Issues `[ECART]` ouvertes vs écarts constatés (§5.2) |
| Âge du dernier rapport mensuel | ≤ 45 jours | Date du rapport vs date du jour |
| Versions de protocoles sans historique | 0 | En-tête + historique index (§7) |
| Décisions ouvertes (§8) sans porteur | 0 | Tableau §8 de l'index |

## 7. Historique

| Version | Date | Changement |
|---|---|---|
| v1.0 | 2026-09-09 | Création de la suite (protocoles 01-07) + index & rituel mensuel |

## 8. État des lieux & décisions ouvertes au 2026-09-09

La suite est livrée en **v1.0** ; elle documente l'existant et comble les trous constatés
(état détaillé dans le §8 de chaque protocole). Décisions ouvertes transverses, à trancher
par le fondateur lors des prochains rituels :

- **DO-A (protocole 03 §8)** — positionnement EN de référence et nom de marque
  (« Leopardo » vs « Leopardo RH ») ; sort de `site/gh-pages/` ; environnement de démo
  publique (NXDOMAIN #3452).
- **DO-B (protocole 06 §8 / annexe A)** — activation des runners Windows/macOS (coût CI) et
  choix de la première tranche verticale desktop pilote.
- **DO-C (protocole 07 §8)** — arbitrage budgétaire Render/Neon payants (workers prod,
  scheduler, staging #1485) ; solde des dettes de nommage (APP_ENV sur le volet dev).
- **DO-D (transverse)** — adoption du label `tech-debt` (déjà présent sur le dépôt, vérifié 2026-09-09) pour les issues REX + ajustement de sa description si besoin (protocole 04 §6) ; re-certification produit post-corrections (protocole 01) ; alignement des apps accounting/travel_agent sur le core (protocole 05).

Chaque décision tranchée met à jour le protocole concerné (PR) et archive la ligne ici.
