<section class="showcase-legal legal" id="legal" data-section="legal">
    <h2>{{ __('showcase.legal_title') }}</h2>
    <p>{{ $vitrine['legal']['notice'] ?? '' }}</p>
    <h3>{{ __('showcase.legal_privacy_title') }}</h3>
    <p>{{ $vitrine['legal']['privacy'] ?? '' }}</p>
    @if (! empty($vitrine['legal']['contact_email']))
        <p><a href="mailto:{{ $vitrine['legal']['contact_email'] }}">{{ $vitrine['legal']['contact_email'] }}</a></p>
    @endif
</section>
