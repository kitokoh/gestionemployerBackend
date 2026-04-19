# BACKLOG PHASE 2 POST-MVP - LEOPARDO RH

## 1. Regle produit de reference

Tous les profils internes de l entreprise sont des employes :

- employe
- RH
- manager

Donc chacun peut :

- pointer sur mobile
- pointer sur borne
- consulter son propre historique

Les differences portent sur les droits de supervision.

## 2. Backlog priorise

## P2-01 - Pointage universel par role

### Objectif

Permettre a manager, RH et employe de pointer dans les memes parcours personnels.

### Taches

- unifier la regle de pointage personnel
- verifier les droits de pointage sur tous les roles
- harmoniser les reponses API `attendance/today`, `check-in`, `check-out`, `history`
- ajouter les tests manager / RH / employe
- verifier la borne pour tous les roles internes actifs

### Livrables

- API pointage universelle
- tests backend par role
- mobile coherent pour tous les profils internes

## P2-02 - Dashboard mobile manager

### Objectif

Donner au manager une vision rapide de son equipe sur mobile.

### Taches

- liste equipe
- statut du jour de chaque employe
- heures travaillees
- retards
- heures supplementaires
- acces au detail employe

### Livrables

- endpoints manager mobile
- ecrans Flutter manager
- scenarios de test manager

## P2-03 - Dashboard mobile RH

### Objectif

Donner au RH une vue plus large de suivi des collaborateurs.

### Taches

- liste employes
- statut presence
- retards
- heures supp
- absences
- acces au profil RH employe
- supervision demandes biometrie

### Livrables

- endpoints RH mobile
- ecrans Flutter RH
- scenarios de test RH

## P2-04 - Historique personnel enrichi

### Objectif

Ameliorer l historique pour tous les profils.

### Taches

- afficher heures travaillees par jour
- afficher heures supp
- afficher statut du jour
- afficher source du pointage mobile / borne
- afficher corrections / regularisations si ajoutees

### Livrables

- endpoint historique enrichi
- ecran mobile historique ameliore

## P2-05 - Synthese heures travaillees

### Objectif

Permettre une lecture immediate du temps de travail.

### Taches

- calcul total semaine
- calcul total mois
- calcul heures supp
- indicateurs manager / RH
- indicateurs employe personnel

### Livrables

- aggregates API temps
- vues synthese mobile/web

## P2-06 - Pre-paie visible

### Objectif

Afficher un montant gagne / estime a partir des donnees de temps.

### Taches

- definir regles de calcul de base
- relier temps travaille et remuneration
- vue employe personnelle
- vue RH/manager de supervision si autorisee
- clarifier le vocabulaire : estime, brut, valide ou non

### Livrables

- endpoints pre-paie
- vues mobile/web de pre-paie

## P2-07 - Fiche employe mobile de supervision

### Objectif

Permettre a manager/RH de consulter une fiche employe utile sur mobile.

### Taches

- identite
- poste
- equipe
- manager
- statut
- heures du jour
- heures supp
- pre-paie ou donnees autorisees
- etat biometrie

### Livrables

- endpoint detail employe mobile
- ecran Flutter fiche employe

## P2-08 - Profil employe avance

### Objectif

Rendre le dossier employe plus riche et plus RH.

### Taches

- infos perso
- infos pro
- urgence
- adresse
- infos administratives
- statut contractuel
- enrichissement pieces / references

### Livrables

- schema de donnees profil enrichi
- formulaires web/mobile

## P2-09 - Biometrie et validations tracees

### Objectif

Mieux gouverner l activation biometrie.

### Taches

- tracer la date de demande
- tracer la date de validation
- tracer le validateur
- tracer les refus
- tracer les modifications
- clarifier les etats mobile/web

### Livrables

- historique biometrie
- vues manager/RH
- indicateurs de conformite

## P2-10 - Supervision borne et sync offline

### Objectif

Renforcer l exploitation terrain.

### Taches

- statut des bornes
- derniere synchro
- evenements en attente
- erreurs de sync
- action de relance
- suivi par site / entreprise

### Livrables

- dashboard borne
- endpoints supervision kiosk
- tests de sync

## P2-11 - Regularisations et corrections

### Objectif

Permettre la gestion des anomalies de pointage.

### Taches

- demande de correction
- validation manager / RH
- journalisation de la correction
- impact sur heures et pre-paie

### Livrables

- workflow correction
- ecrans web/mobile selon droits

## P2-12 - Tests et CI phase 2

### Objectif

Garder la qualite pendant l enrichissement produit.

### Taches

- scenarios API manager / RH / employe
- scenarios mobile manager / RH / employe
- scenarios borne online / offline
- scenarios pre-paie
- scenarios regularisation

### Livrables

- extension GitHub Actions
- doc de scenarios phase 2

## 3. Ordre de demarrage recommande

Sprint / lot recommande :

1. P2-01
2. P2-02
3. P2-03
4. P2-04
5. P2-05
6. P2-06
7. P2-07
8. P2-08
9. P2-09
10. P2-10
11. P2-11
12. P2-12

## 4. Definition de done de la phase 2

Une tache phase 2 est consideree terminee si :

- la regle metier est claire
- backend OK
- mobile/web coherents
- droits par role verifies
- tests critiques ajoutes
- documentation mise a jour

## 5. Recommandation pratique

La bonne suite maintenant est :

- declarer officiellement la phase 2
- commencer par P2-01
- enchainer ensuite P2-02 et P2-03

Pourquoi :

- cela transforme directement le mobile en vrai outil de terrain pour tous les roles
- cela donne rapidement une valeur visible au client

## 6. Backlog terrain issu des 3 premiers clients

## P2-13 - Shifts de nuit et changement de date

### Objectif

Gerer correctement les pointages qui traversent minuit.

### Taches

- ajouter une logique de shift cross-midnight
- recalculer presence / absence sur la plage du shift et non sur la seule date civile
- couvrir check-in/check-out de nuit
- tester pharmacie / securite / horaires decales

## P2-14 - Alertes RH automatiques

### Objectif

Prevenir les superviseurs quand une anomalie de pointage survient.

### Taches

- alerte absence a heure seuil (ex. 9h30)
- alerte oubli de check-out (ex. 18h)
- choix canal email / push
- parametrage par entreprise

## P2-15 - Export Excel / CSV comptable

### Objectif

Permettre l exploitation comptable des pointages hors PDF.

### Taches

- export Excel / CSV par periode
- colonnes heures normales / supp / statut / employe
- acces RH / manager selon droits

## P2-16 - Francisation et professionnalisation UX web

### Objectif

Supprimer les incoherences visibles de langue et de libelles.

### Taches

- harmoniser FR web
- revoir CTA et libelles metier
- corriger les boutons non professionnels

## P2-17 - Pagination, filtres et recherche employes

### Objectif

Eviter les listes lourdes et lentes chez les clients de taille moyenne.

### Taches

- pagination par defaut
- recherche nom / email / matricule
- filtres par role / statut / equipe

## P2-18 - PDF pre-paie enrichi

### Objectif

Rendre le PDF reellement exploitable par DRH / compta.

### Taches

- heures normales
- heures supplementaires
- base brute
- cotisations detaillees
- net estime

## P2-19 - Stabilisation du total estime

### Objectif

Rendre l estimation plus stable et comprehensible pour l utilisateur.

### Taches

- figer les hypotheses de calcul
- afficher date / mode de calcul
- distinguer estimation temps reel et cloturee

## P2-20 - Onboarding self-service entreprise

### Objectif

Reduire la dependance a la creation manuelle de comptes clients.

### Taches

- inscription entreprise
- creation espace essai
- invitation initiale admin/manager

## P2-21 - Facturation multi-devise

### Objectif

Dissocier devise de paiement et devise de facturation.

### Taches

- paiement en devise plateforme
- facture en devise client
- affichage comptable clair
