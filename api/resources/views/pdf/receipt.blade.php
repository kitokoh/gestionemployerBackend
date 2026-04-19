<!doctype html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #111; }
        h1 { font-size: 18px; margin: 0 0 8px; }
        h2 { font-size: 14px; margin: 16px 0 8px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
        th { background: #f5f5f5; }
        .muted { color: #666; font-size: 11px; }
        .disclaimer { margin-top: 14px; font-weight: bold; font-size: 11px; padding: 10px; background-color: #fce4e4; border-left: 3px solid #d9534f; color: #d9534f; text-align: center; }
        .warning-box { padding: 10px; background-color: #fff3cd; color: #856404; border: 1px solid #ffeeba; margin-top: 15px; font-weight: bold; text-align: center; }
        .banner { background-color: #eee; padding: 5px; text-align: center; font-weight: bold; font-size: 10px; margin-bottom: 15px; border-bottom: 1px solid #ccc; }
        .text-right { text-align: right; }
    </style>
</head>
<body>
    <div class="banner">ESTIMATION INFORMATIVE — DOCUMENT NON CONTRACTUEL</div>
    
    <h1>Relevé d'heures et d'estimation</h1>
    <div class="muted">{{ $company->name }} — {{ $company->city }} — {{ $company->country }}</div>

    <h2>Employé</h2>
    <div>
        #{{ $employee->id }} — {{ trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')) }}
        @if($employee->matricule) (Matricule: {{ $employee->matricule }}) @endif
    </div>

    <h2>Période</h2>
    <div>{{ $estimate['period']['from'] }} → {{ $estimate['period']['to'] }} ({{ $estimate['period']['days_present'] }} jours de présence sur {{ $estimate['period']['working_days'] }} jours ouvrés)</div>

    @if(isset($estimate['flags']['salary_not_configured']) && $estimate['flags']['salary_not_configured'])
        <div class="warning-box">
            ⚠️ Salaire non configuré pour cet employé — Montants estimés à 0
        </div>
    @endif

    <h2>Détails journaliers</h2>
    <table>
        <thead>
        <tr>
            <th>Date</th>
            <th class="text-right">Heures</th>
            <th class="text-right">HS</th>
            <th class="text-right">Base</th>
            <th class="text-right">HS (Maj)</th>
            <th class="text-right">Journalier</th>
        </tr>
        </thead>
        <tbody>
        @foreach($estimate['breakdown'] as $row)
            <tr>
                <td>{{ $row['date'] }}</td>
                <td class="text-right">{{ number_format($row['hours'], 2, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($row['overtime_hours'], 2, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($row['base_gain'], 2, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($row['overtime_gain'], 2, ',', ' ') }}</td>
                <td class="text-right">{{ number_format($row['total'], 2, ',', ' ') }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>

    <h2>Récapitulatif financier</h2>
    <table>
        <tr>
            <td><strong>Brut estimé :</strong></td>
            <td class="text-right">{{ number_format($estimate['totals']['gross'], 2, ',', ' ') }} {{ $estimate['currency'] }}</td>
        </tr>
        <tr>
            <td><strong>Déductions estimées ({{ $company->country === 'DZ' ? 'CNAS/IRG' : $company->country }}) :</strong></td>
            <td class="text-right">- {{ number_format($estimate['totals']['deductions'], 2, ',', ' ') }} {{ $estimate['currency'] }}</td>
        </tr>
        <tr>
            <td><strong>Net à payer estimé :</strong></td>
            <td class="text-right"><strong>{{ number_format($estimate['totals']['net'], 2, ',', ' ') }} {{ $estimate['currency'] }}</strong></td>
        </tr>
    </table>

    @if(isset($estimate['flags']['deduction_rate_unavailable']) && $estimate['flags']['deduction_rate_unavailable'])
        <div class="muted" style="margin-top: 5px;">
            ℹ️ Les taux de cotisations pour {{ $company->country }} ne sont pas encore supportés (estimés à 0%).
        </div>
    @endif

    <div class="disclaimer">
        CE DOCUMENT N'EST PAS UN BULLETIN DE PAIE OFFICIEL<br>
        <span style="font-weight: normal; font-size: 10px;">
            Les montants et calculs de cotisations sociales affichés sont purement estimatifs et ne remplacent en aucun cas un calcul comptable officiel validant les charges exactes (CNAS, IRG ou autres organismes).<br>
            Généré automatiquement par Leopardo RH le {{ now('UTC')->setTimezone($company->timezone)->format('Y-m-d H:i') }}. Ce document n'a aucune valeur contractuelle ou légale.
        </span>
    </div>
</body>
</html>
