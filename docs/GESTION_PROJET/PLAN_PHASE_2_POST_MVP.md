# PLAN PHASE 2 POST-MVP - LEOPARDO RH

## 1. Objet

Ce document formalise la phase qui suit le MVP valide de Leopardo RH.

La logique de cette phase 2 est simple :

- consolider l usage reel
- enrichir les fonctions RH a plus forte valeur
- unifier l experience web, mobile et borne
- transformer le MVP en produit pilote puis en produit vendable plus complet

## 2. Nom recommande pour la phase suivante

Nom recommande :

- **Phase 2 - Exploitation RH avancee et pilotage terrain**

Ce nom est adapte car cette phase ne consiste plus a prouver que le produit fonctionne, mais a :

- exploiter les donnees
- mieux piloter les equipes
- ajouter les modules RH attendus
- preparer une utilisation client plus large

## 3. Decision produit importante retenue

Regle metier a retenir des maintenant :

- le manager est aussi un employe de l entreprise
- le RH est aussi un employe de l entreprise
- l employe classique est aussi un employe de l entreprise

Donc :

- **manager, RH et employe peuvent tous pointer**
- chacun peut pointer :
  - via mobile
  - via borne d entree

La difference entre eux ne vient pas du droit au pointage, mais de leurs droits de supervision.

## 4. Ce que cela implique fonctionnellement

### 4.1 Pointage universel

Tous les profils internes de l entreprise doivent pouvoir :

- consulter leur statut du jour
- faire un check-in
- faire un check-out
- consulter leur historique personnel
- pointer sur mobile si autorise
- pointer sur borne si biometrie activee et approuvee

### 4.2 Supervision mobile manager / RH

Le manager et le RH doivent pouvoir, en plus de leur propre pointage, consulter sur mobile :

- heures travaillees par employe
- heures supplementaires
- statut presence / absence
- retards
- synthese paie ou pre-paie
- montant gagne ou brut estime selon les droits accordes

## 5. Objectifs de la phase 2

La phase 2 vise 6 objectifs.

### 5.1 Unifier le role employe

Chaque utilisateur interne d une entreprise est un employe avec :

- un profil RH
- un role fonctionnel
- un droit de pointage
- des droits de consultation variables

### 5.2 Ajouter la supervision mobile

Le mobile ne doit plus etre seulement un outil employe.
Il doit aussi devenir un outil de pilotage simplifie pour :

- manager
- RH

### 5.3 Enrichir la couche temps et activite

Au-dela du simple pointage, il faut consolider :

- heures travaillees
- heures supplementaires
- absences
- retards
- statut journalier

### 5.4 Introduire la pre-paie et la paie visible

La phase 2 doit commencer a relier le temps de travail et la paie.

### 5.5 Renforcer le profil employe

Le dossier employe doit devenir plus riche, mieux structure et plus utile.

### 5.6 Preparer le produit pour l exploitation client

Cela inclut :

- stabilite metier
- documentation
- workflows repetables
- meilleure lisibilite commerciale

## 6. Perimetre recommande de la phase 2

### 6.1 Pointage et temps

- pointage universel pour manager / RH / employe
- historique personnel unifie
- historique equipe pour manager / RH
- heures travaillees journalieres
- heures supplementaires
- retards
- absences visibles
- correction / regularisation encadree si necessaire

### 6.2 Mobile manager / RH

- liste equipe
- synthese journaliere
- detail par employe
- statut de pointage
- heures du jour
- heures supplementaires
- consultation rapide de la pre-paie

### 6.3 Paie et pre-paie

- calcul de base des elements lies au temps
- affichage montant gagne / estime
- affichage salaire du mois ou periode
- distinction entre consultation employee et supervision RH/manager

### 6.4 Profil employe enrichi

- infos personnelles
- infos professionnelles
- infos urgence
- infos administratives
- pieces ou references utiles
- biometrie et consentements
- historique d activation et validation

### 6.5 Borne et biometrie

- generalisation du pointage borne
- supervision des synchronisations
- suivi des evenements offline
- meilleure cartographie des appareils / sites / equipes

## 7. Lots de travail phase 2

La phase 2 doit etre decoupee en lots pour rester pilotable.

## 7.1 Lot A - Temps de travail unifie

But :

- faire du pointage un socle universel pour tous les roles internes

Contenu :

- manager et RH peuvent pointer comme employes
- logique de droits clarifiee
- historique personnel commun
- calcul heures travaillees et heures supp fiabilise
- synthese journaliere par utilisateur

Livrables :

- API unifiee temps de travail
- ecrans mobile/web coherents
- tests metier sur tous les roles

## 7.2 Lot B - Supervision mobile manager / RH

But :

- permettre aux superviseurs de piloter depuis le mobile

Contenu :

- dashboard manager mobile
- dashboard RH mobile
- liste equipe
- fiche employe de supervision
- vues heures / retards / absences / heures supp
- filtrage par periode ou equipe

Livrables :

- API de supervision mobile
- ecrans Flutter manager / RH
- scenarios de test par role

## 7.3 Lot C - Pre-paie et remuneration visible

But :

- relier le temps de travail a la remuneration

Contenu :

- montant travaille / gagne sur periode
- base pour salaire estime
- base pour heures supplementaires valorisees
- affichage restreint selon role
- vue employe personnelle
- vue RH / manager de supervision

Livrables :

- endpoints pre-paie
- modele de donnees pre-paie
- vues web/mobile

## 7.4 Lot D - Profil employe avance

But :

- faire du dossier employe un veritable socle RH

Contenu :

- enrichissement du profil
- infos administratives et RH
- documents / pieces / references
- statut contractuel
- poste, site, equipe, manager
- biometrie et consentements historises

Livrables :

- schema de donnees enrichi
- formulaires web/mobile
- validations et droits associes

## 7.5 Lot E - Terrain, borne et synchronisation

But :

- rendre la solution exploitable a grande echelle terrain

Contenu :

- meilleure supervision des bornes
- tableaux de sync offline
- statut des appareils
- journal des erreurs de sync
- relance manuelle / automatique
- suivi par site client

Livrables :

- ecrans admin borne
- API de supervision kiosk
- documentation exploitation terrain

## 8. Priorites recommandees

Ordre recommande :

1. **Lot A - Temps de travail unifie**
2. **Lot B - Supervision mobile manager / RH**
3. **Lot C - Pre-paie et remuneration visible**
4. **Lot D - Profil employe avance**
5. **Lot E - Terrain, borne et synchronisation**

Pourquoi :

- on consolide d abord la regle metier universelle
- on rend ensuite le mobile utile aux superviseurs
- on ajoute ensuite la valeur paie
- on enrichit ensuite le dossier employe
- on renforce enfin l exploitation terrain

## 9. Impact par couche technique

### 9.1 Backend / API

Il faudra :

- enrichir les endpoints temps
- ajouter les endpoints supervision mobile
- ajouter les endpoints pre-paie
- clarifier les permissions manager / RH / employe
- renforcer les tests RBAC et multi-role

### 9.2 Web

Il faudra :

- enrichir la supervision
- rendre visibles les bornes et la sync
- exposer la pre-paie
- enrichir les fiches employes

### 9.3 Mobile

Il faudra :

- ajouter un mode manager / RH reel
- ajouter vues equipe
- ajouter vues heures / retards / absences / montant
- garder le parcours personnel pour chaque role

### 9.4 Base de donnees

Il faudra probablement :

- enrichir les aggregates temps
- enrichir les donnees paie
- enrichir le profil employe
- tracer les validations et changements biometrie

## 10. Resultat attendu a la fin de la phase 2

A la fin de la phase 2, Leopardo RH doit pouvoir etre presente comme :

- une solution RH avec pointage moderne
- une solution mobile utile aussi pour manager et RH
- une solution capable de relier presence, temps et pre-paie
- une solution exploitable sur terrain meme avec internet instable

## 11. Recommandation de pilotage

Ne pas lancer toute la phase 2 en vrac.

Il faut :

- cadrer la phase 2 comme phase officielle
- decouper par lots
- valider chaque lot
- garder la coherence metier entre web, mobile, borne et API

## 12. Conclusion

Si le MVP est valide, la phase suivante ne doit pas etre une suite de correctifs disperses.

Elle doit devenir une phase produit claire :

- **Phase 2 - Exploitation RH avancee et pilotage terrain**

Le principe directeur est :

- tous les roles internes sont aussi des employes
- tous peuvent pointer
- certains ont en plus des droits de supervision
- la solution devient alors beaucoup plus forte pour le client
