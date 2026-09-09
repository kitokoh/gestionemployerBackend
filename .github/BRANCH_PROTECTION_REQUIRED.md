# Branch Protection — Required Settings

> Miroir de `BRANCH_PROTECTION_REQUIRED.md` (racine) — la racine reste la
> source de vérité documentaire, synchronisée avec le référentiel machine de la
> garde #2011 (`dev-hub/tools/branch-protection-canonical.json`).

## Protection réelle de `main` (vérifiée via API le 2026-09-09)

- `enforce_admins` : activé (aucun push direct, même admin)
- Branche à jour avant merge (`strict`) : activé
- Reviews : non requises (`required_approving_review_count` absent) — à activer quand l'équipe dépasse 2 développeurs actifs
- `allow_force_pushes` / `allow_deletions` : désactivés
- Merge Queue GitHub : non configurée (0 ruleset) — à activer si > 15 PRs/jour

### Les 4 required checks (bloquent le merge) — corrigé 2026-09-09 (#7096)

> Depuis le fast-path anti-saturation #6928 (2026-09-08/09), **Backend Coverage n'est plus
> requis au merge** — il reste exigé par `release.yml` à la release (≥ 65 % backend,
> ≥ 80 % Payroll). Cf. le fichier racine `BRANCH_PROTECTION_REQUIRED.md` pour le détail.

| Check requis | Workflow émetteur |
|---|---|
| `PHPStan — Strict (Core/Modules/Shared, level 8)` | `architecture-check.yml` |
| `Module Structure Validator` | `architecture-check.yml` |
| `Frontend — ESLint + TypeScript` | `architecture-check.yml` |
| `actionlint (+ shellcheck)` | `actionlint.yml` |

### Portes qualité & sécurité exécutées sur chaque PR (non bloquantes au merge, mais surveillées)

- **CodeQL (Actions)** — analyse de sécurité du code (workflow `codeql.yml`, hebdomadaire + PR)
- **Backend Quality (Pint + PHP Syntax + PHPStan/Larastan)** — formatage Pint + lint PHP + analyse statique diff-scopée (workflow `tests.yml`)
- Backend Security (Composer Audit) — audit des dépendances
- TruffleHog Secret Scan — détection de secrets
- Dependency Review (PR Security)
- Semgrep OSS
- Governance Gates (changelog + canonical files) — `dev-hub/tools/check-governance.ps1`
- OWASP ZAP Baseline (non bloquant, flag `-I`)
- Ratio fix/feat (`fix-feat-ratio-guard.yml`) — signal fort, non requis

> Les portes ci-dessus s'ajoutent aux 4 required checks ; seuls les 4 required
> checks bloquent effectivement le merge (protection réelle, cf. garde #2011).
