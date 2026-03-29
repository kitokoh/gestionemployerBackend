# RÔLES ET ATTRIBUTIONS DE DÉVELOPPEMENT — LEOPARDO RH
# Version 1.0 | Mars 2026

---

## 1. JULES (Expert Flutter & Mobile)
**Périmètre : Dossier `/mobile`**

Je me sens le plus à l'aise pour développer toute la partie cliente mobile. Mon expertise inclut :
- **Architecture Flutter** : Setup Riverpod, GoRouter, et structuration des features.
- **UI/UX Mobile** : Création d'interfaces réactives, gestion du RTL (Arabe), et animations de confirmation.
- **Intégrations Natives** : GPS (Geolocator), Caméra, Biométrie (FaceID/Fingerprint), et Notifications Push (FCM).
- **Consommation API** : Intégration de Dio, gestion des intercepteurs de token et du rafraîchissement.
- **Offline & Cache** : Gestion du stockage sécurisé et cache local pour la fluidité.

---

## 2. CLAUDE CODE (Expert Laravel & Backend)
**Périmètre : Dossier `/api`**

Claude Code est l'expert pour toute la logique métier lourde et l'infrastructure :
- **Architecture Backend** : Laravel 11, gestion du Multi-tenant complexe (Hybrid Schema/Shared).
- **Logique Métier (Services)** : Calculs de paie, gestion des HS, pénalités de retard, et acquisition de congés.
- **Sécurité & Auth** : Sanctum, RBAC, et isolation stricte des données.
- **Infrastructure & Devops** : PostgreSQL, Redis, Queues (Supervisor), et déploiement o2switch.
- **Frontend Web** : Dashboard gestionnaire en Vue.js + Inertia.js.

---

## 3. COLLABORATION (LE CONTRAT)

- **API First** : Claude Code définit les endpoints dans `02_API_CONTRATS.md` avant de coder.
- **Mock First** : Jules utilise `17_MOCK_DATA_MOBILE.md` pour avancer sur le mobile sans attendre que l'API soit déployée.
- **Validation** : Tout changement de schéma SQL par Claude Code doit être immédiatement répercuté dans l'ERD pour que Jules puisse mettre à jour ses modèles Dart.
