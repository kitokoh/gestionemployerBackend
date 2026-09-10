<div class="showcase-cookie cookie" data-cookie-banner role="dialog" aria-live="polite">
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
    })();
</script>
