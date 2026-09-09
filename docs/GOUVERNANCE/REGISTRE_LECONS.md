# Registre des leçons — Leopardo (protocole P4)

> Index bidirectionnel des leçons capitalisées : chaque ligne relie une leçon à son issue source
> et à la garde/règle qu'elle a produite. Tenu par le PM, alimenté par tout agent (P4 §6).
> Règles : pas de doublon (chercher avant d'écrire) ; une leçon d'AGENTS.md référence son issue ;
> une leçon qui change une règle de travail est répercutée dans le protocole concerné (P1-P7) à la revue mensuelle.

| Date | Leçon (1 ligne) | Issue source | Garde / règle produite | Fichier impacté |
|------|-----------------|--------------|------------------------|-----------------|
| 2026-08-14 | Vague QA hardening : endpoints réels, mocks cockpit, contrats | sessions QA 2026-08 | Règles de contrats API | `AGENTS.md` |
| 2026-08-16 | Famine du pipeline de déploiement | #3545 | Règle capacité CI (pas de jobs redondants) | `AGENTS.md` |
| 2026-08-24 | Collisions de noms de migrations | #1962 | Garde locale avant push (check-migration-basename-collisions) | `AGENTS.md`, `dev-hub/tools/` |
| 2026-09-08 | Gate deploy dev aveugle : état affiché ≠ état réel | #6834/#6973 | Vérifier l'état réel avant de déclarer un déploiement vert | `AGENTS.md` |
| 2026-09-09 | Adoption du socle P1-P7 (onboarding, capitalisation…) | _PR d'adoption_ | Protocoles `docs/GOUVERNANCE/` | `docs/GOUVERNANCE/` |
| … | | | | |

## Statistiques de flux (mises à jour à la rétro mensuelle)

- Leçons ce mois-ci : _n_ — dont transformées en garde/règle : _n_ (cible ≥ 50 %)
- Issues `capitalisation` créées : _n_ — fermées : _n_ (cible ≥ 80 %)
- Délai moyen constat → issue : _n_ j (cible ≤ 48 h)
- Erreurs « connues » re-déclenchées : _n_ (cible 0)
