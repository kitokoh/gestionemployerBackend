# PROMPT P01 : INITIALISATION INFRASTRUCTURE & MULTI-TENANT
# Dossier : /api

Tu es un expert Laravel Senior. Ta mission est d'initialiser le socle technique de Leopardo RH.

### Objectifs :
1. Installer Laravel 11 dans le dossier `/api`.
2. Configurer PostgreSQL avec le schéma `public`.
3. Implémenter la table `user_lookups` dans le schéma public.
4. Créer le `TenantMiddleware` gérant l'approche hybride (Schema/Shared) selon `11_MULTITENANCY_STRATEGY.md`.
5. Configurer Redis pour le cache et les queues.

### Contraintes :
- Utilise Laravel Sanctum pour l'auth.
- Respecte strictement le `07_SCHEMA_SQL_COMPLET.sql`.
- Prépare les migrations pour le schéma public uniquement dans cette étape.

Vérifie chaque étape avec des tests unitaires avant de valider.
