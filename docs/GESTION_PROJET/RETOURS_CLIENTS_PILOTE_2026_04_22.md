# Retours clients pilote — 2026-04-22

Statut: entree terrain post-GO MVP
But: transformer les retours des premiers clients en priorites produit verifiables, sans rouvrir le scope au hasard.

## Synthese executive

Trois profils pilotes confirment que le coeur MVP interesse le marche, mais les irritants se regroupent en cinq themes:

1. Presence: corrections de pointage, oublis de pointage, nuit et alertes.
2. Web manager/RH: professionnalisation du dashboard, pagination et libelles.
3. Recu/paie: heures sup, cotisations locales, devise/facture.
4. Mobile: light mode, lisibilite, erreurs 401, stabilite des estimations.
5. Croissance SaaS: self-service signup, exports Excel, API/integrations.

Decision de pilotage: avant toute nouvelle feature commerciale, traiter les P0/P1 qui touchent pointage, securite, lisibilite mobile et exports.

## Karim B. — DRH BTP, 85 employes, Alger

Contexte: Plan Business, pointage biometrique ZKTeco deja en place, paie manuelle Excel.

| # | Retour client | Statut actuel | Decision |
|---|---|---|---|
| K1 | "Je peux pas corriger un pointage oublie le soir..." | Correction manager existe via API, UX web/mobile manager a verifier | P1: exposer/valider correction manuelle manager avec audit |
| K2 | "Dashboard web en anglais / boutons 'Creer RH' pas professionnel" | Corrige dans cette branche pour le dashboard manager | P1 UX: continuer la revue des autres pages web |
| K3 | "PDF recu ne montre pas heures sup et cotisations CNAS" | PDF recu existe, contenu paie detaille a auditer | P1 paie: enrichir recu avec heures sup + cotisations |
| K4 | "85 employes, la liste s'affiche d'un coup, ca rame" | Corrige dans cette branche: dashboard pagine par 25 | P1 performance: verifier ressenti sur PC client |
| K5 | "Email quand quelqu'un n'a pas pointe a 9h30" | Commande alertes pointage manquant existe en tests, config horaire a valider | P1 ops: activer/configurer alerte check-in matin |

## Amina T. — Directrice pharmacie, 18 employes, Casablanca

Contexte: Plan Starter, horaires decales, 3 equipes, paie en MAD, iPhone.

| # | Retour client | Statut actuel | Decision |
|---|---|---|---|
| A1 | "L'app mobile est toute noire et verte..." | Corrige dans cette branche: theme clair systeme ajoute | P1 mobile: verifier iPhone en plein soleil |
| A2 | "Employee de nuit rentre a 6h, systeme dit absente" | Tests nuit existent, cas client a rejouer avec 3 equipes | P0/P1: verifier shift overnight multi-equipes |
| A3 | "Demande de conge depuis l'app" | Absences existent cote schema, UX mobile non MVP | Backlog P2 apres stabilisation |
| A4 | "Carte debitee EUR, facture MAD" | Billing/facturation devise non prioritaire MVP | Backlog P2/P3 billing localisation |
| A5 | "Total estime change a chaque ouverture" | Estimation depend des donnees de presence/periode; stabilite a verifier | P1: verrouiller periode/cache/explication estimation |

## Sofiane M. — PDG agence IT, 22 employes, Tunis

Contexte: Plan Business, teletravail partiel, equipe tech-savvy, souhaite API + integrations.

| # | Retour client | Statut actuel | Decision |
|---|---|---|---|
| S1 | "Inscription self-service n'existe pas" | Provisioning super-admin existe, self-service public absent | Backlog P2 SaaS acquisition |
| S2 | "Token expire et Flutter freeze sans message" | Ameliore dans cette branche: message session expiree + logout token | P1 mobile: verifier scenario expiration reel |
| S3 | "Exporter pointages en Excel" | Export CSV compatible Excel existe cote estimation, UX manager a verifier | P1: exposer export pointages clairement |
| S4 | "Pas de light mode, ingerable en plein soleil" | Corrige dans cette branche: theme clair systeme ajoute | P1 mobile: verifier chantier/soleil |
| S5 | "Notification push depart oublie a 18h" | FCM/device table existe, push depart non implemente | Backlog P2 notification soir |

## Priorites consolidees

### P0/P1 avant prochain pilote large

1. Verifier la correction de pointage oublie et la rendre accessible manager/RH.
2. Activer ou documenter les alertes email de pointage manquant le matin.
3. Enrichir recu/export avec heures sup et cotisations locales.
4. Verifier le light mode mobile sur iPhone/chantier.
5. Verifier le 401 mobile avec message clair sur API reelle.

### Backlog apres stabilisation

- Demande de conge mobile.
- Self-service signup public.
- Factures SaaS multi-devise MAD/EUR.
- Notifications push depart oublie.
- API publique/integrations Business.
