<p>Bonjour {{ $recipient->first_name ?? 'Equipe RH' }},</p>

<p>
    Une alerte de pointage a été détectée pour <strong>{{ $company->name }}</strong>
    le <strong>{{ $localDate }}</strong> autour de <strong>{{ $thresholdTime }}</strong>.
</p>

<p>
    Type d'alerte :
    <strong>
        {{ $alertType === 'missing_check_out' ? 'sorties manquantes' : 'arrivées manquantes' }}
    </strong>
</p>

<ul>
    @foreach ($items as $item)
        <li>
            {{ $item['name'] }}
            @if (! empty($item['matricule']))
                ({{ $item['matricule'] }})
            @endif
            — {{ $item['context'] }}
        </li>
    @endforeach
</ul>

<p>Vous pouvez régulariser le pointage depuis Leopardo RH si nécessaire.</p>

<p>— Leopardo RH</p>
