# PROMPT P04 : MODULE POINTAGE & BIOMÉTRIE
# Dossier : /api

Implémente la logique de présence au cœur du système.

### Objectifs :
1. Endpoints `POST /attendance/check-in` et `check-out`.
2. Calcul automatique des `hours_worked` et `overtime_hours` lors du check-out.
3. Support de la biométrie via webhook ZKTeco (mapping `zkteco_id`).
4. Vérification optionnelle du rayon GPS.
5. Endpoint critique `GET /attendance/today` pour le dashboard mobile.

Respecte les règles de calcul de `09_REGLES_METIER_COMPLETES.md`.
