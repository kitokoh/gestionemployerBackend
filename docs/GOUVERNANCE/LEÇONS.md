# LEÇONS — bibliothèque des erreurs (piège → symptôme → garde)

> Issue #7069 (protocole P02 OB-3 / P04 §6). Doc unique consolidant les pièges historiques
> éparpillés (AGENTS.md, conventions, audits). Chaque nouvelle leçon y entre via le flux RETEX
> (P04 §6) avec sa référence. Les règles actives détaillées restent dans `AGENTS.md` — ici,
> la table de synthèse + les pointeurs.

## Table des pièges

| Piège | Symptôme | Garde / règle | Référence |
|---|---|---|---|
| Deux agents implémentent la même issue | PRs/branches doublons (#2333 ×3, #2329 ×2…) | Vérifier branches AVANT de coder ; marker branch immédiat ; 1 branche `fix/<issue>-*` par issue | AGENTS.md « Règle anti-doublon » (#2400) |
| Issue fermée « pour faire propre » sans correctif | Backlog vert mais bug présent (#4690/#4687…) | Jamais de `gh issue close` sans merge OU commentaire motivé | AGENTS.md « ghost close » (#4816) |
| `Closes #N` hors du body de la PR | Issue reste ouverte après merge | Mot-clé dans le **body** ; garde `check-issues-left-open-by-merged-prs.sh` | AGENTS.md (#2512) |
| Collision de préfixes de migrations | `main` rouge pour TOUTES les PRs (indexation par basename) | `check-migration-basename-collisions.sh` AVANT push | AGENTS.md (#1962) |
| `PendingCommand`/artefacts lazy commités | Déploiements ou CI cassés sans lien évident | Vérifier les fichiers générés (SDK, lockfiles) dans la PR | RETEX P04 |
| Migrations tenant au boot via pooler Neon | Deploy `update_failed` (SQLSTATE 25P02) | Hôte direct pour `migrate` au boot (`resolve_migrate_db_url`) | AGENTS.md / docker-entrypoint (#6916, #6931) |
| `search_path` strict vs tables héritées en public | Boot échoue (42P01) sur ALTER tenant | `DB_SEARCH_PATH=shared_tenants,public` (jamais `shared_tenants` seul) | #6924/#6931 |
| Lecture de tables plateforme sans schéma explicite | Métriques à 0 alors que les données existent | Qualifier `public.` pour les objets plateforme (search_path piège) | #7044 |
| `password_hash` NULL à l'insert employé | SQLSTATE 23502 au provisioning | Poser le hash dans le MÊME insert (`forceFill`) | #5161/#4558/#4947, #6958 |
| Contrainte unique globale vs seed multi-tenant | 23505 sur le 2e tenant | Unicité par `company_id` ou seed conditionnel | #6958 |
| Worker queue sans respawn (`--max-time`) | Jobs pending sans signal | Boucle de respawn dans l'entrypoint | #7041 |
| `healthCheckPath` Render vide | Deploys `update_failed` (sonde `/`) | `/api/v1/health` explicite sur le service | #6973 |
| Accumulation de tokens Sanctum au login | 631+ tokens actifs sur comptes partagés | Quota + purge des plus anciens au login | #7009 |
| Route dupliquée (merge union) | RouteCollisionGuard rouge ; 1re copie shadow l'autre | Garde anti-collision ; garder la variante canonique (tests oracle) | #7030 |
| Vues cockpit avec données fabriquées | Cockpit menteur | Endpoints réels ou état « non disponible » explicite | AGENTS.md leçon 2026-08-14 |
| Chemins mobiles ≠ routes réelles | 404 sur écrans (ex. `/me/training-enrollments`) | Cross-check repos Dart ↔ `route:list`/OpenAPI | AGENTS.md leçon 2026-08-14 |
| Vercel rouge (quota) traité comme bloquant | PRs bloquées à tort | Check externe non requis → merger sur les checks requis | AGENTS.md |
| `.env.example` désynchronisé de `config/` | Check CI rouge | `check-env-example-parity.sh` | AGENTS.md |
| Sélecteur de langue sans re-render | URL change, contenu non | Lien/refresh après changement de locale | #7007 |

## Références citées

- `AGENTS.md` (règles actives détaillées) — la table ci-dessus n'est qu'un index.
- `docs/archive/AGENTS_HISTORIQUE_UTILE.md` (historique archivé, #6698).
- Flux d'entrée des nouvelles leçons : protocole `docs/PROTOCOLES/P04_TACHES_ISSUES_EXPERIENCE.md` (§6 RETEX).
