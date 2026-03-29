# PROMPT P07 : MODULE PAIE & AVANCES
# Dossier : /api

La partie la plus sensible : le calcul de la rémunération.

### Objectifs :
1. Calcul automatique du brut au net (Service `PayrollService`).
2. Gestion des tranches d'IR et taux de cotisations par pays (`hr_model_templates`).
3. Workflow des `SalaryAdvances` : approbation et clôture automatique lors du calcul de paie.
4. Simulation de paie mensuelle pour validation par le gestionnaire.
5. Génération asynchrone des bulletins PDF (DomPDF) via Redis queue.

Respecte strictement les formules de `09_REGLES_METIER_COMPLETES.md`.
