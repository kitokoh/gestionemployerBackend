@php
    $items = is_array($content['items'] ?? null) ? $content['items'] : [];
@endphp
<section class="showcase-section showcase-features" data-section="features">
    @if (! empty($content['title']))
        <h2 class="showcase-section__title">{{ $content['title'] }}</h2>
    @endif
    <ul class="showcase-features__list">
        @foreach ($items as $item)
            @if (is_array($item))
                <li class="showcase-card">
                    @if (! empty($item['title']))
                        <h3>{{ $item['title'] }}</h3>
                    @endif
                    @if (! empty($item['description']))
                        <p>{{ $item['description'] }}</p>
                    @endif
                </li>
            @endif
        @endforeach
    </ul>
</section>
