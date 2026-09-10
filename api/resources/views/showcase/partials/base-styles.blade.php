{{--
    Styles de base partagés par les 3 thèmes v1 (BC-27 SHOWCASE, #6868).

    Toutes les couleurs/typo/arrondis passent par les variables CSS définies
    dans `showcase.partials.theme-styles` : le même contenu de sections est
    rendu différemment selon le thème, sans dupliquer le markup.
--}}
<style>
    *, *::before, *::after { box-sizing: border-box; }
    body.showcase {
        margin: 0;
        font-family: var(--showcase-font);
        color: var(--showcase-ink);
        background: var(--showcase-surface);
        line-height: 1.6;
        -webkit-font-smoothing: antialiased;
    }
    .showcase img { max-width: 100%; height: auto; display: block; }
    .showcase a { color: var(--showcase-accent); }

    .showcase-header { padding: 1.5rem; }
    .showcase-header__inner { max-width: 1080px; margin: 0 auto; display: flex; align-items: center; gap: 1rem; }
    .showcase-logo { max-height: 48px; width: auto; }
    .showcase-brand { margin: 0; font-size: 1.35rem; font-weight: 700; letter-spacing: .01em; }
    .showcase-tagline { margin: .15rem 0 0; opacity: .85; font-size: .95rem; }

    .showcase-main { min-width: 0; }
    .showcase-aside { min-width: 0; }
    .showcase-section { padding: 2rem 0; border-bottom: 1px solid var(--showcase-border); }
    .showcase-section:last-child { border-bottom: 0; }
    .showcase-section__title { margin: 0 0 1.25rem; font-size: 1.5rem; line-height: 1.25; }

    .showcase-hero { padding-top: 1rem; }
    .showcase-hero__heading { margin: 0 0 .5rem; font-size: clamp(1.8rem, 4vw, 2.75rem); line-height: 1.15; }
    .showcase-hero__subheading { margin: 0 0 1.25rem; font-size: 1.1rem; color: var(--showcase-muted); }
    .showcase-hero__image { border-radius: var(--showcase-radius); margin: 1.25rem 0; width: 100%; object-fit: cover; }

    .showcase-cta {
        display: inline-block;
        padding: .65rem 1.2rem;
        background: var(--showcase-primary);
        color: var(--showcase-on-primary);
        border-radius: var(--showcase-radius);
        text-decoration: none;
        font-weight: 600;
    }

    .showcase-features__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
    .showcase-card { padding: 1.1rem 1.2rem; border: 1px solid var(--showcase-border); border-radius: var(--showcase-radius); background: var(--showcase-surface); }
    .showcase-card h3 { margin: 0 0 .35rem; font-size: 1.05rem; }
    .showcase-card p { margin: 0; color: var(--showcase-muted); font-size: .95rem; }

    .showcase-gallery__grid { display: grid; gap: .85rem; grid-template-columns: repeat(auto-fit, minmax(13rem, 1fr)); }
    .showcase-gallery__item { margin: 0; }
    .showcase-gallery__image { border-radius: var(--showcase-radius); width: 100%; aspect-ratio: 4 / 3; object-fit: cover; }
    .showcase-gallery__item figcaption { margin-top: .4rem; font-size: .85rem; color: var(--showcase-muted); }

    .showcase-testimonials__list { list-style: none; margin: 0; padding: 0; display: grid; gap: 1rem; grid-template-columns: repeat(auto-fit, minmax(15rem, 1fr)); }
    .showcase-testimonials blockquote { margin: 0; padding: 1rem 1.2rem; border-left: 3px solid var(--showcase-accent); background: var(--showcase-surface); border-radius: var(--showcase-radius); }
    .showcase-testimonials blockquote p { margin: 0 0 .5rem; }
    .showcase-testimonials cite { font-style: normal; color: var(--showcase-muted); font-size: .85rem; }

    .showcase-contact p { margin: .25rem 0; }
    .showcase-footer { display: flex; flex-wrap: wrap; gap: .75rem 1.25rem; align-items: center; }
    .showcase-footer a { margin-right: .5rem; }

    .showcase-legal { font-size: .9rem; color: var(--showcase-muted); padding: 1.5rem 0; }
    .showcase-legal h2 { font-size: 1.1rem; }
    .showcase-legal h3 { font-size: 1rem; }

    .showcase-cookie { position: fixed; inset: auto 1rem 1rem 1rem; background: var(--showcase-primary); color: var(--showcase-on-primary); padding: 1rem 1.25rem; border-radius: var(--showcase-radius); display: none; z-index: 20; box-shadow: 0 10px 30px rgba(15, 23, 42, .25); }
    .showcase-cookie.is-visible { display: block; }
    .showcase-cookie button { margin-top: .5rem; padding: .45rem .9rem; border: 0; border-radius: var(--showcase-radius); background: var(--showcase-accent); color: var(--showcase-on-primary); font: inherit; cursor: pointer; }

    .showcase form { display: grid; gap: .75rem; }
    .showcase input, .showcase textarea { padding: .55rem .65rem; border: 1px solid var(--showcase-border); border-radius: var(--showcase-radius); font: inherit; background: var(--showcase-surface); color: var(--showcase-ink); }
    .showcase button[type="submit"] { padding: .6rem 1.1rem; border: 0; border-radius: var(--showcase-radius); background: var(--showcase-primary); color: var(--showcase-on-primary); font: inherit; font-weight: 600; cursor: pointer; }
    .honeypot { position: absolute; left: -9999px; width: 1px; height: 1px; overflow: hidden; }
    [role="status"] { font-size: .9rem; color: var(--showcase-muted); }
</style>
