## Analyse du rapport externe T19 → T34

Date d'analyse : 19 avril 2026  
Source analysée : `C:\Users\cheic\Downloads\leopardo_plan_t19_t34.docx`

### Conclusion générale

Le rapport est globalement **pertinent**.  
Il apporte de vraies améliorations sur 4 axes utiles pour Leopardo RH :

- sécurité et gouvernance backend
- fiabilité métier RH
- crédibilité visuelle web/mobile
- préparation du produit à la monétisation

En revanche, il mélange :

- des **urgences produit réelles**
- des **améliorations structurantes mais non bloquantes**
- des points **déjà partiellement traités dans le dépôt**

La bonne approche n'est donc pas de tout exécuter d'un bloc, mais de classer.

---

## 1. Recommandations jugées très pertinentes et prioritaires

Ce sont les remarques qui ont un impact direct sur la sécurité, la crédibilité du produit ou l'exploitation réelle.

### T19 — Correction manuelle de pointage

**Pertinence : très forte**

Pourquoi :

- besoin métier concret
- déjà demandé par le terrain
- complète très bien la phase 2 en cours
- permet au manager/RH de corriger les anomalies de pointage sans bricolage

Décision :

- **à intégrer rapidement**
- fait partie des meilleurs prochains tickets backend métier

### T20 — Révocation des autres tokens après changement de mot de passe

**Pertinence : très forte**

Pourquoi :

- faille sécurité réelle
- faible coût
- amélioration immédiate sans impact UI majeur

État actuel :

- le changement de mot de passe existe
- la révocation sélective des autres tokens n'est pas encore garantie

Décision :

- **quick win sécurité**

### T23 — Feature flags par plan

**Pertinence : très forte**

Pourquoi :

- essentiel pour vendre plusieurs plans sans fuite de fonctionnalités
- cohérent avec les bornes, la biométrie et les futures options premium
- évite qu'un client Starter consomme des features Business/Enterprise

État actuel :

- pas de `PlanFeatureGate` central trouvé

Décision :

- **priorité haute avant généralisation commerciale**

### T25 — Audit logs super admin / impersonation

**Pertinence : très forte**

Pourquoi :

- important pour la traçabilité
- protège la couche plateforme
- utile pour incident, conformité et confiance client

État actuel :

- pas de service `AuditLogger` dédié détecté

Décision :

- **priorité haute côté plateforme**

### T26 — Chiffrement IBAN / national_id

**Pertinence : forte**

Pourquoi :

- très bon point conformité et sécurité
- données sensibles
- cohérent avec une montée en maturité produit

Décision :

- **à faire avant paie / RH avancé**

### T32 — Light mode mobile

**Pertinence : forte**

Pourquoi :

- retour utilisateur crédible
- l'app mobile actuelle est encore très orientée dark mode
- améliore fortement l'adoption terrain

État actuel :

- `mobile/lib/core/theme/app_theme.dart` expose seulement `darkTheme`
- `mobile/lib/app.dart` utilise seulement `AppTheme.darkTheme`

Décision :

- **priorité haute côté expérience mobile**

---

## 2. Recommandations pertinentes mais à prioriser juste après

### T21 — Scheduler alertes pointage manquant

**Pertinence : forte**

Pourquoi :

- très utile en exploitation réelle
- bon prolongement du pointage

Mais :

- dépend du bon cadrage scheduler / cron / mail
- moins urgent que T19/T20/T23

Décision :

- **priorité moyenne haute**

### T22 — Alertes contrats expirant à J-30

**Pertinence : bonne**

Pourquoi :

- utile RH
- peu coûteux

Mais :

- moins critique que sécurité, feature flags ou correction pointage

Décision :

- **priorité moyenne**

### T27 — Seeder templates RH DZ / MA / TN avec vrais taux

**Pertinence : forte**

Pourquoi :

- fondamental si on veut crédibiliser estimation, paie et conformité pays

État actuel :

- `HrModelSeeder.php` existe déjà
- il faut vérifier si les taux sont suffisamment réalistes et exhaustifs

Décision :

- **à traiter avant vraie phase paie**

### T29 / T30 / T31 — Refonte Blade + dashboard enrichi

**Pertinence : bonne à forte**

Pourquoi :

- crédibilité client
- navigation plus claire
- prépare la montée de modules

Mais :

- pas le premier verrou actuel
- aujourd'hui le vrai besoin immédiat reste plus côté métier/sécurité/mobile

Décision :

- **important, mais après les urgences sécurité et pointage**

---

## 3. Recommandations déjà partiellement couvertes dans le dépôt

### T33 — `local_auth` et biométrie mobile

**Pertinence : valide, mais déjà largement prise en compte**

Constats dans le code :

- `local_auth: ^2.3.0` est déjà présent dans `mobile/pubspec.yaml`
- `SettingsScreen` importe déjà `local_auth`
- une authentification biométrique locale est déjà déclenchée avant soumission

Décision :

- **ne pas rouvrir comme gros ticket**
- garder seulement une vérification fine iOS / Android permissions si besoin

### T34 — Gestion 401 / session expirée mobile

**Pertinence : bonne, partiellement traitée**

Constats :

- `mobile/lib/core/api/api_client.dart` a déjà un intercepteur Dio
- sur `401`, le token est supprimé
- un callback `onUnauthorized` existe déjà

Mais :

- la proposition du rapport va plus loin sur UX/navigation propre
- le message utilisateur et la redirection globale peuvent encore être améliorés

Décision :

- **à garder comme ticket d'amélioration, pas comme chantier from-scratch**

### T24 — 2FA Super Admin

**Pertinence : forte**

Mais :

- à confirmer par un audit plus ciblé, car le rapport parle d'une colonne `two_fa_secret` déjà présente
- cette partie doit être vérifiée dans le code avant décision d'implémentation

Décision :

- **probablement pertinent, à confirmer techniquement**

---

## 4. Recommandations pertinentes mais dépendantes d'une décision business

### T28 — Stripe abonnements / webhook / facturation

**Pertinence : forte business**

Pourquoi :

- indispensable pour scaler commercialement

Mais :

- dépend de la stratégie de vente réelle
- dépend des plans, pricing, onboarding et politique de test

Décision :

- **important, mais piloté par la roadmap business**
- pas forcément le prochain ticket de dev si la priorité actuelle reste produit/terrain

---

## 5. Ce qu'il faut retenir comme synthèse

Le rapport a raison sur le fond :

- il identifie bien plusieurs trous réels
- il pousse le produit vers un niveau plus pro
- il met le doigt sur des sujets qu'on ne doit pas ignorer

Mais il faut le filtrer avec notre réalité actuelle.

### Top priorités recommandées à intégrer

1. **T20** — révocation des tokens après changement de mot de passe  
2. **T23** — feature gate par plan  
3. **T19** — correction manuelle de pointage  
4. **T25** — audit logs plateforme / super admin  
5. **T32** — light mode mobile  
6. **T34** — finaliser la gestion UX des 401 mobile  
7. **T27** — fiabiliser les modèles RH pays

---

## 6. Recommandation de plan d'intégration

### Lot A — sécurité + contrôle d'accès

- T20
- T23
- T25
- T26

### Lot B — pointage et exploitation RH

- T19
- T21
- T22
- T27

### Lot C — crédibilité UX web/mobile

- T32
- T34
- T29
- T30
- T31

### Lot D — business / monétisation

- T28

---

## 7. Position finale

Oui, il faut **prendre en compte ce rapport**.

Mais il faut le faire de manière pilotée :

- **oui** aux remarques sécurité
- **oui** aux remarques métier pointage
- **oui** aux remarques mobile UX
- **oui mais plus tard** à Stripe et à certaines refontes visuelles plus lourdes

Le rapport est donc **pertinent à environ 80%**, avec un bon noyau de tickets à absorber dans la suite de la roadmap.
