# TRIAGE RETEX — corpus docs/qa/ (2026-09-09)

> Issue #7074. Méthode : scan par mots-clés des **69 documents** de `docs/qa/`
> (sessions expert/agents d'août 2026), comptage de fréquence des thèmes, puis
> conversion des leçons récurrentes en issues `RETEX` (protocole
> `docs/PROTOCOLES/P04_TACHES_ISSUES_EXPERIENCE.md`).
> Ce rapport est **vivant** : complété à chaque nouveau tri (rituel mensuel RET-6).

## Fréquence des thèmes (69 fichiers scannés)

| Thème | Fichiers touchés | Verdict |
|---|---|---|
| CI / exécution workflows | 68 | normal (sujet central des sessions) — voir saturation |
| i18n (dette, clés manquantes) | 60 | **récurrent → issue RETEX** (gardes existantes à durcir/étendre) |
| doublons (branches/PR/issues) | 55 | protocole durci en place (AGENTS.md) — vérifier l'application |
| spec kit / specs | 50 | culture en place — surveiller l'adhésion (spec avant code) |
| gardes (contournées/à créer) | 45 | **récurrent → issue RETEX** (traçabilité des contournements) |
| close d'issues | 40 | règles ghost-close en place — surveiller |
| saturation CI | 30 | **récurrent → issue RETEX** (quotas/fast-path déjà engagés — mesurer) |
| migrations | 24 | garde collision en place — surveiller |
| claim / auto-assign | 21 | protocole anti-doublon en place — vérifier l'application |
| mojibake / encodage | 8 | **récurrent → issue RETEX** (étendre aux fichiers dupliqués #2661) |
| secrets | 14 | gardes en place (secret scan) — rotation #7065 |

## Sessions représentatives par thème

| Thème | Sessions/exemples |
|---|---|
| Doublons & claims | `QA_SESSION_2026-08-15-expert*.md`, `PLAN_CORRECTION_2026-08-17.md` |
| Saturation CI | `QA_SESSION_2026-08-14.md`, `QA_SESSION_2026-08-15-expert10.md` |
| i18n | `INVENTAIRE_CI_2026-08-19.md`, `QA_RAPPORT_2026-08-27.md`, rapports `docs/validation/I18N_DEBT_REPORT_*` |
| Mojibake | `QA_SESSION_2026-08-15-expert4.md`, `-expert6.md`, `-expert-tests.md` |
| Migrations | `QA_SESSION_2026-08-14.md`, `QA_SESSION_2026-08-15-expert-tests.md` |
| Spec kit | `QA_SESSION_2026-08-15-expert2.md`, `-expert5-live-qa.md` |

## Issues RETEX ouvertes à l'issue de ce tri

| Issue | Sujet | Référence |
|---|---|---|
| #7089 | RETEX : dette i18n — consolider les gardes bloquantes (clés manquantes) | i18n |
| #7090 | RETEX : saturation CI — mesurer et durcir quotas/fast-path | CI |
| #7091 | RETEX : mojibake — étendre les gardes d'encodage aux fichiers dupliqués entre apps | mobile |
| #7092 | RETEX : contournement de gardes — tracer tout contournement en issue immédiate | process |

Issues de suivi liées : #7087 (rituel mensuel septembre — exécution du prochain tri),
#7088 (dérive échelle emerald config web vs COULEURS.md, constatée par le 1er run du
check tokens #7070).

## Règles de suite

1. Toute leçon à 2+ occurrences devient une **garde** ou une ligne de
   `docs/GOUVERNANCE/LECONS_OPERATIONNELLES.md` (L-XX) — jamais seulement un constat.
2. Les sessions datées de `docs/qa/` restent des **preuves figées** (règle
   `docs/validation/README.md`) : ce rapport est l'index de tri, pas un doublon.
3. Prochain tri : revue mensuelle — les ~70 fichiers sont re-scannés par mots-
   clés + lecture ciblée des sessions nouvelles.
