# PROMPT P02 : AUTHENTIFICATION & PROFIL
# Dossier : /api

Implémente le système d'authentification multi-tenant complet.

### Objectifs :
1. **Login** : Utiliser `user_lookups` pour trouver le tenant, puis authentifier l'utilisateur dans son schéma respectif.
2. **Sanctum** : Configurer les tokens avec le préfixe `leopardo_` et une durée de 90 jours pour le mobile.
3. **Endpoints** :
   - `POST /auth/login` (incluant l'enregistrement du fcm_token).
   - `GET /auth/me` (retournant les infos de l'utilisateur et de sa compagnie).
   - `POST /auth/logout`.
4. **Sécurité** : Appliquer le rate limiting sur le login (5 tentatives / 15 min).

L'isolation doit être garantie par le middleware. Teste l'isolation avec Pest.
