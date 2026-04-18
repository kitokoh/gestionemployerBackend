# SCÉNARIOS DE TEST MOBILE FLUTTER

## Objectif

Couvrir les parcours critiques mobile (auth, présence, historique, erreurs réseau) avec une stratégie progressive: widget tests, tests d’intégration, et smoke build CI.

## Pré-requis

- Flutter stable
- API accessible (`API_BASE_URL`)
- Compte de test valide (manager/employee)
- Données de seed présentes en environnement de test

## Stratégie recommandée

1. **Widget tests** (rapides, PR)
2. **Integration tests** (parcours end-to-end mobile)
3. **Smoke build** Android debug (validité compilation + wiring)

## Scénarios fonctionnels — Auth

1. **Login succès**
   - Entrer email/mot de passe valides
   - Attendu: redirection dashboard + token stocké
2. **Login invalide**
   - Mauvais mot de passe
   - Attendu: message d’erreur métier
3. **Session expirée (401)**
   - API retourne 401
   - Attendu: token supprimé + retour login
4. **Déconnexion**
   - Action logout depuis app
   - Attendu: token supprimé + écran login

## Scénarios fonctionnels — Présence

1. **Check-in succès**
   - Utilisateur connecté, journée non démarrée
   - Attendu: état “en cours” + heure affichée
2. **Check-out succès**
   - Après check-in
   - Attendu: session clôturée + total journalier visible
3. **Double check-in interdit**
   - Check-in répété sans check-out
   - Attendu: erreur claire, pas de doublon UI
4. **Historique présence**
   - Ouvrir historique mensuel
   - Attendu: liste cohérente + statuts (ontime/late)

## Scénarios résilience / UX

1. **API indisponible**
   - Simuler `connectionError`
   - Attendu: message réseau explicite
2. **Timeout connexion**
   - Simuler `connectTimeout`
   - Attendu: message délai dépassé
3. **URL API incorrecte**
   - Base URL invalide
   - Attendu: erreur lisible côté utilisateur
4. **Écran vide / loading**
   - Données lentes
   - Attendu: loading puis rendu correct

## Scénarios sécurité mobile

1. **Token absent au démarrage**
   - Attendu: écran login
2. **Token présent au démarrage**
   - Attendu: restauration session
3. **Token corrompu**
   - Attendu: fallback login propre

## Mapping CI conseillé

- **Widget tests (PR):**
  - `mobile/test/features/auth/login_screen_test.dart`
  - `mobile/test/features/attendance/attendance_screen_test.dart`
  - `mobile/test/features/attendance/history_screen_test.dart`
- **Integration tests (ajout recommandé):**
  - `mobile/integration_test/auth_flow_test.dart`
  - `mobile/integration_test/attendance_flow_test.dart`
  - `mobile/integration_test/offline_error_flow_test.dart`
- **Build smoke:**
  - `flutter build apk --debug --dart-define=API_BASE_URL=...`

## Critères de validation “Go”

- Tous les widget tests verts
- Tous les tests backend (Unit/Feature) verts
- Smoke build Android vert
- Aucun crash de login/check-in/check-out sur environnement de recette
