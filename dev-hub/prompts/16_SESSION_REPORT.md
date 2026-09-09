# Prompt 16 — Rapport de fin de session (contrat de sortie)

> Protocole P02 §7 / P04 §6 (issue #7060). À exécuter en FIN de session agent.
> Produit : un commentaire structuré sur l'issue/PR traitée (ou un fichier
> `memory/<date>.md` pour les sessions sans issue), + la checklist session 0
> complétée.

## 1. Bilan de la session

- **Issue(s) traitée(s)** : #N (liens)
- **PR(s) ouverte(s)/mergée(s)** : #PR + SHA
- **Verdict** : ✅ terminé / 🔄 en cours / ⛔ bloqué (cause)

## 2. Ce qui a été fait

- Changements (1 ligne par fichier touché, ou résumé par commit)
- Vérifications effectuées (tests locaux, checks requis, smoke live)

## 3. Découvertes / manquements (→ RETEX)

- Bugs ou incohérences trouvés en route (→ ouvrir une issue si non tracé, avec repro)
- Leçons exploitables pour les prochains agents (→ flux P04 §6 / `docs/GOUVERNANCE/LEÇONS.md`)

## 4. Reste à faire (handoff)

- Prochaines étapes, propriétaire suggéré, dépendances externes éventuelles

## 5. Hygiène

- [ ] Checklist session 0 complétée (cf. `docs/architecture/AGENT_SESSION0_CHECKLIST.md`)
- [ ] Branches locales nettoyées (garder `main` aligné sur `origin/main`)
- [ ] Token(s) de session révoqué(s)
