<section class="showcase-section showcase-contact" data-section="contact">
    @if (! empty($content['title']))
        <h2 class="showcase-section__title">{{ $content['title'] }}</h2>
    @endif
    @if (! empty($content['email']))
        <p><a href="mailto:{{ $content['email'] }}">{{ $content['email'] }}</a></p>
    @endif
    @if (! empty($content['phone']))
        <p>{{ $content['phone'] }}</p>
    @endif
    @if (! empty($content['address']))
        <p class="showcase-muted">{{ $content['address'] }}</p>
    @endif
</section>
