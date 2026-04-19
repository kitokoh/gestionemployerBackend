# AUDIT BETA-READY ET RETOURS DES 3 PREMIERS CLIENTS

## 1. Objet

Ce document sert de point de verite avant bascule Beta.

Il consolide :

- l etat reel du produit apres le MVP
- les reserves encore visibles
- les retours terrain des 3 premiers clients
- les actions prioritaires a engager juste apres

## 2. Verdict global

Statut recommande :

- **GO BETA SOUS RESERVE**

Pourquoi :

- le noyau produit fonctionne
- l API, le web et le mobile sont exploitables
- les roles principaux se connectent
- le pointage coeur existe
- la CI/CD est structuree

Mais il reste des reserves importantes avant une Beta sereine a plus grande echelle :

- experience mobile encore inegale selon les profils et les usages
- supervision manager / RH encore trop legere pour un vrai pilotage terrain
- regularisation de pointage encore backend/API mais pas encore pleinement visible en UX metier
- pre-paie / PDF / exports encore insuffisants pour certains clients
- alertes et automatisations metier pas encore produites

## 3. Ce qui est deja suffisamment bon pour la Beta

### 3.1 Socle technique

- multitenant PostgreSQL durci
- protection du search_path apres requete
- verrouillage transactionnel des pointages
- CI GitHub avec backend, mobile, gouvernance et audit
- deploy Render structure avec healthcheck

### 3.2 Parcours coeur

- login web
- login mobile
- session mobile et gestion de 401
- pointage check-in / check-out
- historique personnel
- borne / kiosque avec sync offline

### 3.3 Gouvernance

- changelog a jour
- backlog phase 2 existant
- runbooks borne et deploy disponibles
- audits de securite / CI deja amorces

## 4. Reserves Beta a traiter vite

### R-01 - Regularisation pointage visible metier

Etat :

- la correction de pointage existe cote API
- il manque encore une UX web/mobile simple et rassurante pour DRH / RH

Impact :

- fort pour BTP / industrie / equipes terrain

### R-02 - PDF / pre-paie encore trop limités

Etat :

- le PDF est legalement mieux cadre
- mais il ne couvre pas encore assez clairement les attentes comptables / RH clients

Impact :

- fort pour les clients qui attendent heures supp + cotisations + export

### R-03 - Supervision mobile et web encore insuffisante

Etat :

- P2-01 / P2-02 / P2-03 ont demarre
- mais les superviseurs ne disposent pas encore d un vrai cockpit metier complet

Impact :

- fort pour DRH / managers

### R-04 - Horaires complexes et nuit

Etat :

- les shifts de nuit et franchissements de date restent un vrai risque produit

Impact :

- critique pour pharmacies, securite, industrie, garde, restauration

### R-05 - Notifications metier absentes

Etat :

- pas encore d alertes automatiques pour absence ou oubli de sortie

Impact :

- tres forte valeur immediate pour les clients

## 5. Retours clients et lecture produit

## 5.1 Karim B. - DRH BTP, Alger, 85 employes, plan Business

### Retour 1

> Je peux pas corriger un pointage oublie le soir

Lecture :

- besoin metier reel et frequent
- confirme la priorite de `P2-11 Regularisations et corrections`

Action :

- ajouter ecran web DRH/RH de regularisation
- ajouter workflow avec motif, validation et trace
- recalcul automatique heures + pre-paie

### Retour 2

> Mon dashboard web est en anglais / libelles pas pro

Lecture :

- probleme de finition visible client
- nuit directement a la credibilite produit

Action :

- lancer un chantier de francisation et harmonisation UX web
- priorite haute avant Beta elargie

### Retour 3

> Le PDF ne montre pas assez les heures supp et les cotisations

Lecture :

- confirme que la pre-paie actuelle est encore trop “technique”

Action :

- enrichir PDF et export
- afficher clairement :
  - heures normales
  - heures supp
  - base brute
  - cotisations
  - net estime

### Retour 4

> La liste de 85 employes rame

Lecture :

- probleme de pagination / UX / perf

Action :

- paginer partout par defaut
- ajouter recherche / filtres / chargement progressif

### Retour 5

> Je veux un email si quelqu un n a pas pointe a 9h30

Lecture :

- besoin fort de supervision proactive

Action :

- ajouter moteur d alertes RH
- priorite haute phase 2

## 5.2 Amina T. - Pharmacie, Casablanca, 18 employes, plan Starter

### Retour 1

> L app est trop sombre

Lecture :

- confirme la pertinence de `T32`

Etat :

- le light mode a ete ajoute

Action :

- prevoir verification UX reelle iPhone / contraste / lisibilite

### Retour 2

> Mon employee de nuit est marquee absente a cause du changement de date

Lecture :

- besoin produit critique
- le systeme doit devenir “shift-aware”

Action :

- ajouter support complet des shifts chevauchant minuit
- priorite tres haute

### Retour 3

> Je veux demander un conge depuis l app

Lecture :

- confirme que le module conges est un vrai besoin phase suivante

Action :

- planifier module conges mobile/web

### Retour 4

> Carte debitee en EUR mais facture MAD

Lecture :

- besoin billing / facturation multi-devise

Action :

- separer devise de paiement et devise de facturation
- pas bloquant MVP, mais important commercialement

### Retour 5

> Le total estime change a chaque ouverture

Lecture :

- besoin de stabiliser le calcul et surtout la comprehension du calcul

Action :

- figer les hypotheses de calcul
- clarifier l origine du montant et la date de calcul
- distinguer estimation temps reel vs estimation arretee

## 5.3 Sofiane M. - Agence IT, Tunis, 22 employes, plan Business

### Retour 1

> L inscription self-service n existe pas

Lecture :

- vrai besoin onboarding B2B plus autonome

Action :

- ajouter onboarding self-service entreprise / essai

### Retour 2

> Le token expire et l app freeze

Lecture :

- besoin deja connu et en partie traite

Etat :

- la gestion session expiree a ete amelioree

Action :

- finaliser UX de recovery
- verifier qu aucun ecran ne reste bloque

### Retour 3

> Je veux exporter les pointages en Excel

Lecture :

- besoin fort comptable

Action :

- priorite haute sur export Excel / CSV

### Retour 4

> Pas de light mode

Etat :

- deja corrige techniquement

Action :

- valider visuellement en conditions reelles

### Retour 5

> Notification push si oubli de pointer le depart

Lecture :

- besoin de relance metier simple et tres rentable

Action :

- moteur de notifications push pour oubli check-out

## 6. Priorisation produit issue des retours reels

## Lot P-Beta-1 - A faire juste apres

Priorite critique :

1. regularisation de pointage complete web + mobile superviseur
2. gestion des shifts de nuit / franchissement minuit
3. dashboard web/mobile manager-RH plus pro et francise
4. export Excel / CSV des pointages
5. alertes d absence et d oubli de sortie

## Lot P-Beta-2 - A faire ensuite

Priorite haute :

6. PDF pre-paie enrichi
7. stabilisation du calcul du total estime
8. pagination / filtres / recherche employes
9. onboarding self-service
10. conges mobile/web

## Lot P-Beta-3 - A faire ensuite

Priorite commerciale :

11. facturation multi-devise
12. polish UX web et mobile
13. cockpit superviseur avance

## 7. Mapping avec la phase 2

Les retours clients confirment et enrichissent la phase 2.

### A renforcer dans le backlog existant

- `P2-02` dashboard manager
- `P2-03` dashboard RH
- `P2-06` pre-paie visible
- `P2-10` supervision borne / terrain
- `P2-11` regularisations et corrections

### Nouveaux items a ajouter explicitement

- `P2-13` gestion des shifts de nuit
- `P2-14` alertes RH automatiques
- `P2-15` export Excel / CSV comptable
- `P2-16` self-service onboarding entreprise
- `P2-17` facturation multi-devise

## 8. Decision recommandee

Decision recommande :

- **GO BETA SOUS RESERVE**

Reserves a annoncer clairement :

- les clients pilotes peuvent travailler sur le noyau produit
- mais les scenarios suivants doivent etre traites en priorite :
  - correction de pointage ergonomique
  - shifts de nuit
  - exports comptables
  - alertes automatiques
  - supervision plus mature

## 9. Conclusion

Les 3 premiers clients ont donne exactement le bon type de feedback :

- ils ne remettent pas en cause le socle
- ils confirment la valeur du produit
- ils montrent les fonctions qui feront passer Leopardo RH
  - d un MVP valide
  - a un produit Beta reellement convaincant

La bonne suite n est donc pas de repartir de zero.

La bonne suite est :

- consolider la Beta avec les retours terrain
- prioriser les gains metier visibles
- transformer la phase 2 en feuille de route client-centree
