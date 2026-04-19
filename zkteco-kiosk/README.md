# ZKTeco Kiosk Bridge

Ce dossier contient le socle du poste de pointage a placer a l entree d une entreprise cliente.

## Objectif

Le poste d entree joue 3 roles :

1. afficher une interface de pointage simple et visible sur un ecran tactile
2. recevoir un identifiant employe depuis un lecteur ZKTeco ou un scanner HID
3. transmettre l evenement au backend Leopardo RH via l endpoint kiosque

## Parcours produit vise

1. Le manager / RH cree un employe dans Leopardo RH
2. L employe recoit un email d invitation
3. L employe telecharge l application mobile et se connecte
4. L employe soumet ses donnees biometrie depuis le mobile
5. Le manager ou le RH approuve
6. L employe peut alors pointer :
   - via son mobile
   - ou via la borne d entree ZKTeco

## Fichiers

- `index.html` : interface locale de borne
- `app.js` : logique d interaction et envoi vers l API
- `config.example.json` : configuration a copier en `config.json`

## Integration ZKTeco

La plupart des lecteurs ZKTeco sur PC / mini-PC exposent soit :

- une sortie clavier HID
- un SDK local / service local
- ou un webhook / push cote reseau selon le modele

Le socle ici supporte 2 approches :

1. **HID clavier**
   - le lecteur injecte directement le matricule / zkteco_id dans le champ
   - l agent appuie automatiquement sur entree ou l operateur clique sur le bouton

2. **Bridge JS local**
   - un service local peut appeler :
   - `window.ZKTecoBridge.submitIdentifier("FP-00042", "check_in")`

## Configuration

1. Copier `config.example.json` vers `config.json`
2. Renseigner :
   - `apiBaseUrl`
   - `deviceCode`
   - `companyName`
3. Servir le dossier en local sur la machine de la borne

## Important

Le gabarit biometrie brut ne doit pas etre gere par ce dossier si le lecteur ZKTeco le conserve deja.
Leopardo RH travaille ici avec :

- `zkteco_id`
- etat d approbation biometrie
- evenement de pointage

Ce dossier est donc le **poste d interface et de transmission**, pas un remplacement du firmware ZKTeco.
