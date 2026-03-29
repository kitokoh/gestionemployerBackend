# PROMPT P10 : DÉPLOIEMENT & CI/CD
# Dossier : /api

Prépare le passage en production sur o2switch.

### Objectifs :
1. Configurer le workflow `deploy.yml` pour GitHub Actions.
2. Mettre en place les scripts de migration de production.
3. Configurer Supervisor pour les queues et le scheduler Laravel.
4. Effectuer une purge des Audit Logs (Rétention 24 mois).

Vérifie la sécurité TLS et les headers Nginx avant la livraison.
