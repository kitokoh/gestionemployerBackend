@php
    $items = is_array($content['items'] ?? null) ? $content['items'] : [];
@endphp
<section class="showcase-section showcase-gallery" data-section="gallery">
    @if (! empty($content['title']))
        <h2 class="showcase-section__title">{{ $content['title'] }}</h2>
    @endif
    <div class="showcase-gallery__grid">
        @foreach ($items as $item)
            @if (is_array($item))
                <figure class="showcase-gallery__item">
                    @include('showcase.partials.media', [
                        'mediaId' => $item['image_id'] ?? null,
                        'mediaUrl' => $item['image_url'] ?? null,
                        'alt' => is_string($item['caption'] ?? null) ? $item['caption'] : '',
                        'class' => 'showcase-gallery__image',
                    ])
                    @if (! empty($item['caption']))
                        <figcaption>{{ $item['caption'] }}</figcaption>
                    @endif
                </figure>
            @endif
        @endforeach
    </div>
</section>
