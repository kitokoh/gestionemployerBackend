<!DOCTYPE html>
<html lang="{{ $vitrine['lang'] ?? 'fr' }}" dir="{{ $vitrine['direction'] ?? 'ltr' }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $vitrine['meta']['title'] ?? $vitrine['company_name'] }}</title>
    @if (! empty($vitrine['meta']['description']))
        <meta name="description" content="{{ $vitrine['meta']['description'] }}">
    @endif
    @php($canonicalLang = ($vitrine['lang'] ?? 'fr') !== 'fr' ? '?lang='.$vitrine['lang'] : '')
    <link rel="canonical" href="{{ url('/vitrine/'.$vitrine['slug'].$canonicalLang) }}">
    {{-- #6874 — alternatives de langue (multilingue fr/en/ar/tr) --}}
    @foreach (($vitrine['available_locales'] ?? []) as $alternateLocale)
        <link rel="alternate" hreflang="{{ $alternateLocale }}" href="{{ url('/vitrine/'.$vitrine['slug'].'?lang='.$alternateLocale) }}">
    @endforeach
    <meta property="og:type" content="website">
    <meta property="og:site_name" content="{{ $vitrine['company_name'] }}">
    <meta property="og:title" content="{{ $vitrine['meta']['title'] ?? $vitrine['company_name'] }}">
    @if (! empty($vitrine['meta']['description']))
        <meta property="og:description" content="{{ $vitrine['meta']['description'] }}">
    @endif
    @if (! empty($vitrine['meta']['og_image']))
        <meta property="og:image" content="{{ $vitrine['meta']['og_image'] }}">
    @endif
    <meta property="og:url" content="{{ url('/vitrine/'.$vitrine['slug']) }}">
    <meta name="twitter:card" content="summary_large_image">
    <style>
        :root { --ink: #0f172a; --muted: #475569; --line: #e2e8f0; --bg: #ffffff; }
        * { box-sizing: border-box; }
        body { margin: 0; font-family: system-ui, -apple-system, "Segoe UI", Roboto, sans-serif; color: var(--ink); background: var(--bg); line-height: 1.55; }
        main { max-width: 960px; margin: 0 auto; padding: 1.5rem; }
        section { padding: 1.5rem 0; border-bottom: 1px solid var(--line); }
        h1, h2 { line-height: 1.2; }
        img { max-width: 100%; height: auto; }
        ul { padding-left: 1.25rem; }
        .muted { color: var(--muted); }
        .legal { font-size: .9rem; }
        .cookie { position: fixed; inset: auto 1rem 1rem 1rem; background: var(--ink); color: #fff; padding: 1rem; border-radius: .5rem; display: none; }
        .cookie.is-visible { display: block; }
        .cookie button { margin-top: .5rem; }
        form { display: grid; gap: .75rem; max-width: 32rem; }
        input, textarea { padding: .5rem; border: 1px solid var(--line); border-radius: .25rem; font: inherit; }
        .honeypot { position: absolute; left: -9999px; }
        [role="status"] { font-size: .9rem; }
    </style>
</head>
<body>
<main>
    <header>
        <h1>{{ $vitrine['company_name'] }}</h1>
        @if (! empty($vitrine['settings']['tagline']))
            <p class="muted">{{ $vitrine['settings']['tagline'] }}</p>
        @endif
    </header>

    @foreach ($vitrine['sections'] as $section)
        @php($content = is_array($section['content'] ?? null) ? $section['content'] : [])
        <section data-section="{{ $section['type'] }}">
            @switch($section['type'])
                @case('hero')
                    @if (! empty($content['heading']))
                        <h2>{{ $content['heading'] }}</h2>
                    @endif
                    @if (! empty($content['subheading']))
                        <p>{{ $content['subheading'] }}</p>
                    @endif
                    @if (! empty($content['image_url']))
                        <img src="{{ $content['image_url'] }}" alt="{{ $content['heading'] ?? $vitrine['company_name'] }}">
                    @endif
                    @if (! empty($content['cta_label']) && ! empty($content['cta_url']))
                        <p><a href="{{ $content['cta_url'] }}">{{ $content['cta_label'] }}</a></p>
                    @endif
                    @break
                @case('features')
                    @if (! empty($content['title']))
                        <h2>{{ $content['title'] }}</h2>
                    @endif
                    <ul>
                        @foreach (($content['items'] ?? []) as $item)
                            @if (is_array($item))
                                <li>
                                    <strong>{{ $item['title'] ?? '' }}</strong>
                                    @if (! empty($item['description']))
                                        <span class="muted"> — {{ $item['description'] }}</span>
                                    @endif
                                </li>
                            @endif
                        @endforeach
                    </ul>
                    @break
                @case('gallery')
                    @if (! empty($content['title']))
                        <h2>{{ $content['title'] }}</h2>
                    @endif
                    @foreach (($content['items'] ?? []) as $item)
                        @if (is_array($item) && ! empty($item['image_url']))
                            <figure>
                                <img src="{{ $item['image_url'] }}" alt="{{ $item['caption'] ?? '' }}">
                                @if (! empty($item['caption']))
                                    <figcaption class="muted">{{ $item['caption'] }}</figcaption>
                                @endif
                            </figure>
                        @endif
                    @endforeach
                    @break
                @case('testimonials')
                    @if (! empty($content['title']))
                        <h2>{{ $content['title'] }}</h2>
                    @endif
                    @foreach (($content['items'] ?? []) as $item)
                        @if (is_array($item) && ! empty($item['quote']))
                            <blockquote>
                                <p>{{ $item['quote'] }}</p>
                                <cite>{{ $item['author'] ?? '' }}@if (! empty($item['role'])) — {{ $item['role'] }}@endif</cite>
                            </blockquote>
                        @endif
                    @endforeach
                    @break
                @case('contact')
                    @if (! empty($content['title']))
                        <h2>{{ $content['title'] }}</h2>
                    @endif
                    @if (! empty($content['email']))
                        <p><a href="mailto:{{ $content['email'] }}">{{ $content['email'] }}</a></p>
                    @endif
                    @if (! empty($content['phone']))
                        <p class="muted">{{ $content['phone'] }}</p>
                    @endif
                    @if (! empty($content['address']))
                        <p class="muted">{{ $content['address'] }}</p>
                    @endif
                    @break
                @case('footer')
                    @if (! empty($content['text']))
                        <p class="muted">{{ $content['text'] }}</p>
                    @endif
                    @foreach (($content['links'] ?? []) as $link)
                        @if (is_array($link) && ! empty($link['url']))
                            <a href="{{ $link['url'] }}">{{ $link['label'] ?? $link['url'] }}</a>
                        @endif
                    @endforeach
                    @break
            @endswitch
        </section>
    @endforeach

    <section id="contact" data-contact>
        <h2>{{ __('showcase.contact_title') }}</h2>
        <form id="showcase-contact" method="post" action="{{ $contactAction }}">
            <input type="hidden" name="company_website" value="" class="honeypot" tabindex="-1" autocomplete="off">
            <input type="text" name="name" required maxlength="150" placeholder="{{ __('showcase.contact_name') }}" aria-label="{{ __('showcase.contact_name') }}">
            <input type="email" name="email" required maxlength="255" placeholder="{{ __('showcase.contact_email') }}" aria-label="{{ __('showcase.contact_email') }}">
            <textarea name="message" required maxlength="5000" rows="5" placeholder="{{ __('showcase.contact_message') }}" aria-label="{{ __('showcase.contact_message') }}"></textarea>
            <label>
                <input type="checkbox" name="consent" value="1" required>
                {{ __('showcase.contact_consent_label') }}
            </label>
            <button type="submit">{{ __('showcase.contact_submit') }}</button>
            <p role="status" data-contact-status></p>
        </form>
    </section>

    <section class="legal" id="legal">
        <h2>{{ __('showcase.legal_title') }}</h2>
        <p>{{ $vitrine['legal']['notice'] }}</p>
        <h3>{{ __('showcase.legal_privacy_title') }}</h3>
        <p>{{ $vitrine['legal']['privacy'] }}</p>
        @if (! empty($vitrine['legal']['contact_email']))
            <p><a href="mailto:{{ $vitrine['legal']['contact_email'] }}">{{ $vitrine['legal']['contact_email'] }}</a></p>
        @endif
    </section>
</main>

<div class="cookie" data-cookie-banner role="dialog" aria-live="polite">
    <p>{{ __('showcase.cookie_notice') }}</p>
    <button type="button" data-cookie-accept>{{ __('showcase.cookie_accept') }}</button>
</div>

<script>
    (function () {
        "use strict";

        var cookieKey = "leopardo_showcase_cookie_notice";
        var banner = document.querySelector("[data-cookie-banner]");
        var accept = document.querySelector("[data-cookie-accept]");

        // Aucun cookie : preference purement locale, aucun tracker tiers.
        try {
            if (banner && window.localStorage.getItem(cookieKey) !== "1") {
                banner.classList.add("is-visible");
            }
        } catch (e) {
            // stockage indisponible : la banniere reste masquee, aucun cookie pose
        }

        if (accept && banner) {
            accept.addEventListener("click", function () {
                try { window.localStorage.setItem(cookieKey, "1"); } catch (e) { /* noop */ }
                banner.classList.remove("is-visible");
            });
        }

        var form = document.getElementById("showcase-contact");
        if (! form) {
            return;
        }

        var status = form.querySelector("[data-contact-status]");
        var messages = {
            success: @json(__('showcase.contact_success')),
            error: @json(__('showcase.contact_error'))
        };

        form.addEventListener("submit", function (event) {
            event.preventDefault();

            var payload = {
                name: form.elements.namedItem("name") ? form.elements.namedItem("name").value : "",
                email: form.elements.namedItem("email") ? form.elements.namedItem("email").value : "",
                message: form.elements.namedItem("message") ? form.elements.namedItem("message").value : "",
                company_website: form.elements.namedItem("company_website") ? form.elements.namedItem("company_website").value : "",
                consent: form.elements.namedItem("consent") && form.elements.namedItem("consent").checked ? 1 : 0
            };

            window.fetch(form.getAttribute("action"), {
                method: "POST",
                headers: { "Content-Type": "application/json", "Accept": "application/json" },
                body: JSON.stringify(payload)
            }).then(function (response) {
                if (status) { status.textContent = response.ok ? messages.success : messages.error; }
                if (response.ok) { form.reset(); }
            }).catch(function () {
                if (status) { status.textContent = messages.error; }
            });
        });
    })();
</script>
</body>
</html>
