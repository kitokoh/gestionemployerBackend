@php
    $items = is_array($content['items'] ?? null) ? $content['items'] : [];
@endphp
<section class="showcase-section showcase-testimonials" data-section="testimonials">
    @if (! empty($content['title']))
        <h2 class="showcase-section__title">{{ $content['title'] }}</h2>
    @endif
    <div class="showcase-testimonials__list">
        @foreach ($items as $item)
            @if (is_array($item) && ! empty($item['quote']))
                <blockquote>
                    <p>{{ $item['quote'] }}</p>
                    <cite>
                        {{ $item['author'] ?? '' }}@if (! empty($item['role'])) — {{ $item['role'] }}@endif
                    </cite>
                </blockquote>
            @endif
        @endforeach
    </div>
</section>
