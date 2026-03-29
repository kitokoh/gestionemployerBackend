# PROMPT P05 : MODULE ABSENCES & CONGÉS
# Dossier : /api

Gère le workflow des demandes d'absence.

### Objectifs :
1. Soumission de demande par l'employé avec justificatif.
2. Workflow d'approbation/refus par le gestionnaire.
3. Calcul automatique des jours ouvrables (hors weekends et jours fériés).
4. Mise à jour automatique du solde de congés lors de l'approbation.
5. Commande cron pour l'acquisition mensuelle des congés.

Utilise `AbsenceService` pour centraliser la logique de calcul.
