<!DOCTYPE html>
<html lang="{{ $vitrine['lang'] ?? app()->getLocale() }}">
<head>
    @include('showcase.partials.head')
    @include('showcase.partials.theme-styles')
    @include('showcase.partials.base-styles')
    <style>
        /* Thème Commerce (#6868) : vitrine produit, bandeau chaud, grille large. */
        body.showcase--commerce { background: var(--showcase-surface); }
        .showcase--commerce .showcase-header { background: linear-gradient(135deg, var(--showcase-primary) 0%, var(--showcase-accent) 100%); color: var(--showcase-on-primary); }
        .showcase--commerce .showcase-header__inner { justify-content: space-between; }
        .showcase--commerce .showcase-shell { max-width: 1180px; margin: 0 auto; padding: 2rem 1.5rem; }
        .showcase--commerce .showcase-features__list { grid-template-columns: repeat(auto-fit, minmax(12rem, 1fr)); }
        .showcase--commerce .showcase-gallery__grid { grid-template-columns: repeat(auto-fit, minmax(11rem, 1fr)); }
        .showcase--commerce .showcase-cta { box-shadow: 0 8px 20px rgba(245, 158, 11, .35); }
        .showcase--commerce .showcase-aside { margin-top: 2rem; padding: 1.5rem; border: 2px dashed var(--showcase-primary); border-radius: var(--showcase-radius); }
    </style>
</head>
<body class="showcase showcase--commerce">
    <header class="showcase-header">
        <div class="showcase-header__inner">
            <div>
                <p class="showcase-brand">{{ $variables['brand_name'] }}</p>
                @if (! empty($variables['tagline']))
                    <p class="showcase-tagline">{{ $variables['tagline'] }}</p>
                @endif
            </div>
            @if (! empty($variables['logo_url']))
                <img class="showcase-logo" src="{{ $variables['logo_url'] }}" alt="{{ $variables['brand_name'] }}" decoding="async">
            @endif
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
