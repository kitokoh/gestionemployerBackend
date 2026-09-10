@php
    $heading = is_string($content['heading'] ?? null) ? $content['heading'] : null;
    $subheading = is_string($content['subheading'] ?? null) ? $content['subheading'] : null;
    $ctaLabel = is_string($content['cta_label'] ?? null) ? $content['cta_label'] : null;
    $ctaUrl = is_string($content['cta_url'] ?? null) ? $content['cta_url'] : null;
@endphp
<section class="showcase-section showcase-hero" data-section="hero">
    @if ($heading !== null)
        <h1 class="showcase-hero__heading">{{ $heading }}</h1>
    @endif
    @if ($subheading !== null)
        <p class="showcase-hero__subheading">{{ $subheading }}</p>
    @endif
    @include('showcase.partials.media', [
        'mediaId' => $content['image_id'] ?? null,
        'mediaUrl' => $content['image_url'] ?? null,
        'alt' => $heading ?? ($variables['brand_name'] ?? ''),
        'class' => 'showcase-hero__image',
    ])
    @if ($ctaLabel !== null && $ctaUrl !== null)
        <p><a class="showcase-cta" href="{{ $ctaUrl }}">{{ $ctaLabel }}</a></p>
    @endif
</section>
