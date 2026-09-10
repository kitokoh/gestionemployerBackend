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
