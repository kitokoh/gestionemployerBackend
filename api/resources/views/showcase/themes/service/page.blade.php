<!DOCTYPE html>
<html lang="{{ $vitrine['lang'] ?? app()->getLocale() }}">
<head>
    @include('showcase.partials.head')
    @include('showcase.partials.theme-styles')
    @include('showcase.partials.base-styles')
    <style>
        /* Thème Service (#6868) : centré, aéré, cartes arrondies. */
        .showcase--service .showcase-header { text-align: center; background: var(--showcase-surface); border-bottom: 1px solid var(--showcase-border); }
        .showcase--service .showcase-header__inner { flex-direction: column; align-items: center; gap: .35rem; }
        .showcase--service .showcase-shell { max-width: 920px; margin: 0 auto; padding: 2.5rem 1.5rem; }
        .showcase--service .showcase-hero { text-align: center; }
        .showcase--service .showcase-hero__image { margin-left: auto; margin-right: auto; }
        .showcase--service .showcase-card { box-shadow: 0 8px 24px rgba(15, 23, 42, .06); border-color: transparent; }
        .showcase--service .showcase-aside { margin-top: 2rem; padding: 1.5rem; border: 1px solid var(--showcase-border); border-radius: var(--showcase-radius); }
        .showcase--service .showcase-cta { border-radius: 999px; }
    </style>
</head>
<body class="showcase showcase--service">
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
