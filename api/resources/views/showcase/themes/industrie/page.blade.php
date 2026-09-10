<!DOCTYPE html>
<html lang="{{ $vitrine['lang'] ?? app()->getLocale() }}" dir="{{ $vitrine['direction'] ?? 'ltr' }}">
<head>
    @include('showcase.partials.head')
    @include('showcase.partials.theme-styles')
    @include('showcase.partials.base-styles')
    <style>
        /* Thème Industrie (#6868) : dense, contrasté, rail latéral. */
        body.showcase--industrie { background: var(--showcase-surface); }
        .showcase--industrie .showcase-header { background: var(--showcase-primary); color: var(--showcase-on-primary); border-bottom: 4px solid var(--showcase-accent); }
        .showcase--industrie .showcase-shell { max-width: 1120px; margin: 0 auto; padding: 2rem 1.5rem; display: grid; grid-template-columns: minmax(0, 1fr) 19rem; gap: 2.5rem; align-items: start; }
        .showcase--industrie .showcase-aside { position: sticky; top: 1.5rem; padding: 1.25rem; border: 1px solid var(--showcase-border); border-top: 4px solid var(--showcase-accent); border-radius: var(--showcase-radius); }
        .showcase--industrie .showcase-hero__heading { text-transform: uppercase; letter-spacing: .01em; }
        .showcase--industrie .showcase-card { border-radius: 0; border-left: 3px solid var(--showcase-accent); }
        @media (max-width: 880px) {
            .showcase--industrie .showcase-shell { grid-template-columns: 1fr; }
            .showcase--industrie .showcase-aside { position: static; }
        }
    </style>
</head>
<body class="showcase showcase--industrie">
    <header class="showcase-header">
        <div class="showcase-header__inner">
            @if (! empty($variables['logo_url']))
                <img class="showcase-logo" src="{{ $variables['logo_url'] }}" alt="{{ $variables['brand_name'] }}" decoding="async">
            @endif
            <div>
                <p class="showcase-brand">{{ $variables['brand_name'] }}</p>
                @if (! empty($variables['tagline']))
                    <p class="showcase-tagline">{{ $variables['tagline'] }}</p>
                @endif
            </div>
        </div>
    </header>

    <div class="showcase-shell">
        <main class="showcase-main">
            @include('showcase.partials.sections')
            @include('showcase.partials.legal')
        </main>
        <aside class="showcase-aside">
            @include('showcase.partials.contact-form')
        </aside>
    </div>

    @include('showcase.partials.cookie-banner')
</body>
</html>
