# PROTOCOLE_LOTS_MULTI_AGENTS — Lots d'issues & merge sous saturation CI

> RETEX du 2026-09-09 formalisé (issue #7126) : collisions multi-agents sur #7057-#7075,
> file CI saturée ~1 h sans runner, fenêtre sans checks utilisée puis restaurée.
> But : exécuter un lot d'issues sans collision et merger même quand la CI est bloquée,
> **sans affaiblir durablement la protection de `main`**.

## 1. Préparation du lot (anti-collision)

1. Lister les issues ouvertes **non assignées** (assignee = vide) et vérifier qu'aucune
   PR ouverte ni branche (`git ls-remote origin | grep -i <issue>`) ne les référence.
2. Privilégier les issues à faible impact CI (docs, process, templates, scripts locaux) :
   vérifier le diff attendu — pas de suite backend 60 min si évitable.
3. Créer la branche unique `docs/<slug>-<date>` (ou `bc/<code>-<slug>` si même BC) depuis
   `origin/main` à jour, et pousser immédiatement un **claim marker** (`commit --allow-empty`
   listant les issues) pour verrouiller le lot.
4. S'il manque des issues au lot : créer des issues de moisson précises (constat daté +
   actions + critères d'acceptation) ancrées sur `main` réel.

## 2. Implémentation

- 1 commit par issue, message `<type>(<scope>): <résumé> (#issue)`.
- Vérifier localement ce qui est vérifiable (scripts bash/python exécutés, liens markdown,
  YAML parsés, tests rouge/vert démontrés). Ne jamais pousser de changement runtime non vérifié.
- Avant push final : re-fetch `main` ; si `main` a bougé, merger `origin/main` et résoudre
  (conflits fréquents sur AGENTS.md/docs partagés — prendre la version main quand un autre
  agent a déjà livré la même chose, pour éviter les doublons).

## 3. PR & merge sous saturation CI

1. PR unique avec un `Closes #N` par issue dans le **body** (règle #2512 ; PR typée `docs:`
   tolérée sans Closes, mais les issues resteraient ouvertes).
2. Vérifier `mergeable_state` : `clean` → merge normal. `blocked` (checks en file) ou
   `dirty` (main a bougé) → re-synchroniser puis, si la file reste saturée, **merge en
   fenêtre contrôlée** (autorisation fondateur requise, voir §4).
3. Après merge : vérifier issues fermées, supprimer la branche, confirmer que `main`
   contient le commit.

## 4. Fenêtre de merge contrôlée (bypass temporaire)

Réservé aux cas où la file CI est bloquée (saturation > 30-60 min) et avec l'accord du
fondateur. Séquence stricte, fenêtre la plus courte possible (< 30 s) :

1. **Sauvegarder** la protection actuelle : `GET /branches/main/protection` (conserver
   `required_status_checks.contexts`, `enforce_admins`, flags).
2. **Désactiver** : `PUT /branches/main/protection` avec `required_status_checks: null`,
   `enforce_admins: false`.
3. **Merger** la PR (PUT /pulls/<n>/merge), retries courts si état transitoire.
4. **Restaurer** immédiatement (bloc `finally`) la protection exacte ; vérifier ensuite
   `GET /branches/main/protection` : contexts présents, `enforce_admins: true`.

⚠️ Risques : pendant la fenêtre, un autre agent peut merger sans checks (d'où la brièveté) ;
la garde `branch-protection-guard.yml` (horaire) détecte toute dérive résiduelle — la
restauration doit donc être vérifiée, pas supposée.

## 5. Checklist post-lot

- [ ] Issues toutes fermées ; PR mergée ; branche supprimée
- [ ] Protection `main` restaurée et vérifiée (contexts + enforce_admins)
- [ ] Pas de doublon livré (comparer les fichiers aux PR concurrentes mergées)
- [ ] Leçons du lot consolidées (AGENTS.md / BIBLIOTHEQUE_ERREURS.md / ce protocole)
