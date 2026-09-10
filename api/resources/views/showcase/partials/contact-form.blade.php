<section class="showcase-contact" id="contact" data-section="contact">
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

<script>
    (function () {
        "use strict";

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
