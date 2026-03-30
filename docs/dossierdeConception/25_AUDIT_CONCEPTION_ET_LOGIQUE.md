# AUDIT DE CONCEPTION ET LOGIQUE — LEOPARDO RH
# Rapport d'analyse approfondie | Mars 2026

---

## 1. ANALYSE DE L'ARCHITECTURE & TENANCY

### Points Forts
- **Stratégie Hybride (Shared/Schema) :** Excellente approche pour l'évolutivité. Le mode `shared` permet de supporter de nombreux petits clients à moindre coût, tandis que le mode `schema` répond aux exigences de sécurité/isolation des clients Entreprise.
- **TenantMiddleware Centralisé :** L'utilisation de `search_path` PostgreSQL couplée aux Global Scopes Laravel rend la logique de tenancy transparente pour les développeurs.
- **Table `user_lookups` :** Cruciale pour la performance. Elle évite le scan de tous les schémas lors du login.

### Risques & Points à Surveiller
- **Migrations Multi-schémas :** La montée en charge du nombre de schémas Enterprise peut ralentir les déploiements (boucle de migration sur N schémas).
- **Consistance Public/Tenant :** Risque de désynchronisation si une transaction échoue entre le schéma public et le schéma du tenant lors de la création d'entreprise.

---

## 2. ANALYSE DE L'AUTHENTIFICATION & SÉCURITÉ

### Points Forts
- **Tokens Sanctum OPAQUES :** Choix judicieux par rapport aux JWT pour permettre la révocation instantanée (vol de mobile, etc.).
- **Chiffrement AES-256 :** Bonne pratique sur l'IBAN et les comptes bancaires.

### Manquements / Problèmes de Logique
- **2FA pour les Managers :** La documentation mentionne le 2FA pour le Super Admin, mais il est absent pour les Managers RH/Principaux qui manipulent pourtant des données hautement sensibles (salaires).
- **Durée des sessions :** 90 jours pour le mobile est généreux. Une vérification biométrique locale (FaceID/Fingerprint) à l'ouverture de l'app devrait être **obligatoire** dans la conception.

---

## 3. LOGIQUE MÉTIER : POINTAGE (ATTENDANCE)

### Points Forts
- **Calcul des HS & Pénalités :** Les formules sont détaillées et couvrent bien les arrondis et les tolérances.
- **Statut de Pointage :** La priorité des statuts (`holiday > leave > absent...`) est cohérente.

### Problèmes de Logique Détectés
- **Contrainte Unique `(employee_id, date)` :** La documentation ERD v2.0 impose une seule ligne par jour. **C'est un problème majeur.**
    - *Scénario :* Un employé de restauration travaille de 11h à 15h, puis de 18h à 22h.
    - *Impact :* Impossible d'enregistrer le deuxième shift (split shift).
    - *Correction suggérée :* Supprimer l'unique sur `date` ou ajouter une colonne `session_id` / autoriser plusieurs entrées par jour.
- **Synchronisation Biométrique :** La conception repose sur des webhooks. En cas de coupure internet sur le site physique, le buffer du lecteur ZKTeco doit être "poussé" massivement lors de la reconnexion. Le rate limit de 1000 req/min semble suffisant, mais une gestion de batch serait préférable.

---

## 4. LOGIQUE MÉTIER : PAIE (PAYROLL)

### Points Forts
- **Modèle de Données complet :** L'ERD des bulletins de paie couvre bien les cotisations, l'IR et les retenues.
- **Proratisation :** La gestion des entrées/sorties en cours de mois est spécifiée.

### Risques de Logique
- **Dépendance aux `company_settings` :** Les formules de calcul d'IR (tranches) sont stockées en JSON dans les settings. Si un manager modifie mal ce JSON, toute la paie est faussée.
    - *Recommandation :* Verrouiller les modèles RH officiels (Algérie, Maroc, etc.) pour qu'ils ne soient modifiables que par le Super Admin ou via une interface ultra-sécurisée.
- **Reporting DAS/CNAS :** Il manque la spécification des fichiers d'export officiels pour les déclarations sociales (ex: formats EDI/XML spécifiques par pays).

---

## 5. RBAC & PERMISSIONS

### Points Forts
- **Granularité :** 7 rôles distincts permettent une séparation des tâches (Comptable vs RH).
- **Manager Départemental :** L'isolation au sein d'un tenant par `department_id` est bien pensée.

### Manquements
- **Délégation :** Aucun mécanisme n'est prévu pour déléguer les validations (ex: un manager en congé délègue à son adjoint).
- **Hiérarchie :** L'autoréférence `manager_id` dans `employees` est correcte, mais la logique de validation des absences par "N+1" vs "RH" mériterait d'être paramétrable.

---

## 6. SYNTHÈSE DES GAPS (MANQUANTS)

1. **Notifications en Temps Réel :** La documentation hésite entre FCM (Push) et SSE (Stream). Pour le web, le SSE est nécessaire. Pour le mobile, FCM est obligatoire. La conception doit clarifier la coexistence des deux.
2. **Gestion des Documents :** Il manque une spécification sur le stockage des fichiers (PDF, justificatifs d'absence). Risque de saturation du VPS si pas de stockage S3/externe.
3. **Import/Export :** L'import CSV des employés est mentionné mais pas détaillé au niveau des mappings d'erreurs.
4. **Logs d'audit :** Pas de trace des "consultations" de données sensibles (qui a vu le salaire de qui ?), seulement des modifications.

---

## CONCLUSION

Le projet est **très bien conçu architecturalement**, particulièrement sur la partie multi-tenancy et la séparation des responsabilités. La logique métier est solide mais souffre de quelques rigidités (sessions de pointage uniques) et d'un manque de sécurité sur les données de paie (absence de 2FA manager).

**Priorité 1 :** Corriger la table `attendance_logs` pour supporter les split-shifts.
**Priorité 2 :** Sécuriser la modification des settings de calcul (Paie/IR).
**Priorité 3 :** Finaliser la stratégie de stockage des documents.
