# Bibliothèque des erreurs — registre central

> Protocole P04 (§4) — but : une seule doc pour retrouver **où** chaque erreur est définie, son **code stable** et son **message localisé**.

## Règles
- Une erreur métier exposée à l'API = **code stable en MAJUSCULES** (`INVALID_CREDENTIALS`, `INSUFFICIENT_ROLE`, `LEAVE_BALANCE_INSUFFICIENT`…) + `message` + `localized_message`.
- Les clés i18n des messages vivent dans `api/lang/{fr,en,tr,ar}/errors.php` (et `auth.php` pour l'auth) — parité ×4 exigée (garde #5432).
- Réponse JSON standard : `{ error, message, localized_message, errors? }` (validation 422 = `errors` par champ).
- 5xx : `INTERNAL_ERROR` générique — **jamais de détail** côté client ; l'exception réelle part en log `structured` / Sentry.

## Sources canoniques (à consulter avant d'ajouter une erreur)
| Zone | Fichier(s) |
|---|---|
| Clés i18n erreurs | `api/lang/{fr,en,tr,ar}/errors.php`, `auth.php` |
| Exceptions métier | `api/app/Modules/*/Domain/Exceptions/` (une classe = un code stable) |
| Erreurs AI (BC-23) | `api/app/AI/Exceptions/` (codes `AI_*`, `STT_*`) |
| Erreurs device/kiosk | `api/app/Modules/Attendance|Cameras/*/Domain/Exceptions/` (`ZKTECO_SCHEMA_DRIFT`…) |
| Mapping HTTP | `api/app/Exceptions/Handler.php` + `bootstrap/app.php` |

## Ajouter une erreur (checklist)
1. Classe d'exception dans le Domain du module (jamais `App\Exceptions` — jumelles supprimées #6573).
2. Clé i18n ×4 langues + code stable documenté ici (une ligne).
3. Test du code stable dans la Feature (assertion sur `error` + `localized_message`).
