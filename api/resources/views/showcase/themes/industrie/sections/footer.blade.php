@php
    $links = is_array($content['links'] ?? null) ? $content['links'] : [];
@endphp
<section class="showcase-section showcase-footer" data-section="footer">
    @if (! empty($content['text']))
        <p class="showcase-footer__text">{{ $content['text'] }}</p>
    @endif
    @foreach ($links as $link)
        @if (is_array($link) && ! empty($link['url']))
            <a href="{{ $link['url'] }}">{{ $link['label'] ?? $link['url'] }}</a>
        @endif
    @endforeach
</section>
