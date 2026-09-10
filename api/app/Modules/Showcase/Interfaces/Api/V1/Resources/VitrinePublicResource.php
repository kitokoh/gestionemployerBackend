<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionContentResolver;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO PUBLIC d'une vitrine publiée (BC-27 SHOWCASE, #6867 — étendu #6871,
 * #6873, #6875).
 *
 * RÈGLE ABSOLUE : cette ressource n'expose QUE ce qu'un visiteur public a le
 * droit de voir — jamais `id`, `company_id`, `showcase_id`,
 * `created_at/updated_at`, `preview_token`, ni aucun champ interne. Les
 * `settings` sont filtrés par allowlist (variables de marque scalaires).
 *
 * Étendue par les lots v1 :
 *   - V-PUBLISH #6871 : le jeton d'aperçu n'est JAMAIS exposé ;
 *   - V-SEO #6873 : `meta` (title/description/og_image/canonical) ;
 *   - V-RGPD #6875 : `legal` (mentions légales + confidentialité) et
 *     `cookies` (aucun cookie tiers) ;
 *   - V-THEMES #6868 : `settings.colors` (palette `#RRGGBB` sous allowlist)
 *     et `logo_url` — variables de présentation éditables consommées par le
 *     rendu serveur (`ShowcaseThemeRenderer`) ;
 *   - V-MEDIA #6872 : `media` (médias réellement référencés, minimisation) —
 *     `logo_id` interne et chemins disque ne sortent jamais.
 *
 * Le rendu SSR (`/vitrine/{slug}`) consomme ce même DTO : API et page HTML
 * ne peuvent pas diverger. Le test de non-fuite ShowcasePublicApiTest
 * verrouille cette shape.
 */
final class VitrinePublicResource extends JsonResource
{
    /**
     * Clés autorisées dans `settings` exposées au public (allowlist — anti-
     * fuite si un jour une clé interne s'y glisse).
     *
     * @var list<string>
     */
    public const PUBLIC_SETTINGS_KEYS = ['colors', 'brand_name', 'tagline', 'og_image', 'logo_url'];

    /**
     * Sous-clés autorisées de `settings.colors` (variables de présentation
     * éditables #6868) — filtrées en plus par format `#RRGGBB`. Une valeur
     * non conforme est ignorée (jamais exposée, jamais injectée en CSS).
     *
     * @var list<string>
     */
    public const PUBLIC_COLOR_KEYS = ['primary', 'accent', 'surface', 'on_primary'];

    /**
     * @param  list<CompanyShowcaseSection>  $sections
     * @param  list<ShowcaseMedia>  $media
     */
    public function __construct(
        CompanyShowcase $showcase,
        private readonly array $sections,
        private readonly string $companyName,
        private readonly array $media = [],
        private readonly string $locale = ShowcaseSectionSchemaRegistry::DEFAULT_LOCALE,
    ) {
        parent::__construct($showcase);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CompanyShowcase $showcase */
        $showcase = $this->resource;

        $sections = $this->publicSections();

        return [
            'slug' => $showcase->slug,
            'company_name' => $this->companyName,
            'theme' => $showcase->theme,
            // #6874 — locale de rendu et direction d'écriture (RTL arabe) :
            // consommées par le gabarit SSR (`<html lang dir>`).
            'lang' => $this->locale,
            'direction' => ShowcaseSectionSchemaRegistry::directionFor($this->locale),
            'available_locales' => $this->availableLocales(),
            'published_at' => $showcase->published_at?->toIso8601String(),
            'settings' => $this->publicSettings($showcase),
            'legal' => $this->legal($showcase),
            'cookies' => $this->cookies($showcase),
            'meta' => $this->meta($sections),
            'media' => $this->publicMedia($showcase),
            'sections' => $sections,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicSections(): array
    {
        return array_map(
            function (CompanyShowcaseSection $section): array {
                $content = is_array($section->content) ? $section->content : [];
                $translations = is_array($section->translations) ? $section->translations : null;

                $resolved = ShowcaseSectionContentResolver::resolve($content, $translations, $this->locale);

                return [
                    'type' => $section->type->value,
                    'schema_version' => $section->schema_version,
                    'content' => $resolved !== [] ? $resolved : new \stdClass,
                ];
            },
            $this->sections
        );
    }

    /**
     * Locales réellement disponibles sur la vitrine (union des surcouches de
     * sections + locale de référence) — alimente le sélecteur de langue
     * exposé côté public. Aucune donnée interne (juste des codes de locale).
     *
     * @return list<string>
     */
    private function availableLocales(): array
    {
        $present = [ShowcaseSectionSchemaRegistry::defaultLocale() => true];

        foreach ($this->sections as $section) {
            $translations = is_array($section->translations) ? $section->translations : null;

            foreach (ShowcaseSectionContentResolver::availableLocales($translations) as $locale) {
                $present[$locale] = true;
            }
        }

        $locales = [];

        foreach (ShowcaseSectionSchemaRegistry::supportedLocales() as $locale) {
            if (isset($present[$locale])) {
                $locales[] = $locale;
            }
        }

        return $locales;
    }

    /**
     * Méta SEO/partage dérivées du contenu (V-SEO #6873) — jamais de champ
     * interne. `canonical_path` est un chemin public stable.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function meta(array $sections): array
    {
        /** @var CompanyShowcase $showcase */
        $showcase = $this->resource;

        $settings = $showcase->settings ?? [];

        $brandName = $settings['brand_name'] ?? null;
        $brand = is_string($brandName) && trim($brandName) !== ''
            ? $brandName
            : $this->companyName;

        $taglineValue = $settings['tagline'] ?? null;
        $tagline = is_string($taglineValue) && trim($taglineValue) !== ''
            ? $taglineValue
            : null;

        $ogImageValue = $settings['og_image'] ?? null;

        $heroHeading = null;
        $heroSubheading = null;
        $ogImage = is_string($ogImageValue) ? $ogImageValue : null;

        foreach ($sections as $section) {
            if (($section['type'] ?? null) !== ShowcaseSectionType::Hero->value) {
                continue;
            }

            $content = is_array($section['content'] ?? null) ? $section['content'] : [];
            $heroHeading = is_string($content['heading'] ?? null) ? $content['heading'] : null;
            $heroSubheading = is_string($content['subheading'] ?? null) ? $content['subheading'] : null;
            $ogImage = is_string($content['image_url'] ?? null) ? $content['image_url'] : $ogImage;

            break;
        }

        $title = trim((string) ($heroHeading ?? $brand));
        if ($tagline !== null) {
            $title = trim($title.' | '.$tagline);
        }

        return [
            'title' => $title,
            'description' => $heroSubheading ?? $tagline,
            'og_image' => $ogImage,
            'canonical_path' => '/vitrine/'.$showcase->slug,
            'indexable' => true,
        ];
    }

    /**
     * Bloc légal public (V-RGPD #6875) : mentions légales + politique de
     * confidentialité éditables, avec textes génériques par défaut (la page
     * publiée n'est jamais servie sans mentions). `contact_email` est un
     * point de contact public optionnel.
     *
     * @return array<string, mixed>
     */
    private function legal(CompanyShowcase $showcase): array
    {
        $legal = $showcase->legal ?? [];

        $noticeValue = $legal['notice'] ?? null;
        $notice = is_string($noticeValue) && trim($noticeValue) !== ''
            ? $noticeValue
            : (string) __('showcase.legal_notice_default');

        $privacyValue = $legal['privacy'] ?? null;
        $privacy = is_string($privacyValue) && trim($privacyValue) !== ''
            ? $privacyValue
            : (string) __('showcase.legal_privacy_default');

        $contactEmailValue = $legal['contact_email'] ?? null;
        $contactEmail = is_string($contactEmailValue) && trim($contactEmailValue) !== ''
            ? $contactEmailValue
            : null;

        return [
            'notice' => $notice,
            'privacy' => $privacy,
            'contact_email' => $contactEmail,
        ];
    }

    /**
     * Déclaration cookies publique (V-RGPD #6875) : la vitrine ne dépose
     * AUCUN cookie tiers (bannière informative légère, préférence stockée
     * côté client uniquement).
     *
     * @return array<string, mixed>
     */
    private function cookies(CompanyShowcase $showcase): array
    {
        return [
            'third_party' => false,
            'banner_required' => false,
            'policy' => $this->legal($showcase)['privacy'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function publicSettings(CompanyShowcase $showcase): array
    {
        // `settings` est un cast JSON `array` sur le modèle (nullable → ?? []).
        $settings = $showcase->settings ?? [];

        $public = [];

        foreach (self::PUBLIC_SETTINGS_KEYS as $key) {
            if ($key === 'colors') {
                // Traité séparément : sous-allowlist + validation de format
                // (tableau, donc exclu du filtre scalaire ci-dessous).
                continue;
            }

            if (array_key_exists($key, $settings)) {
                $value = $settings[$key];

                // Scalaires uniquement (nom, slogan…) — toute structure
                // interne (ids, chemins…) reste exclue.
                if (is_scalar($value) || $value === null) {
                    $public[$key] = $value;
                }
            }
        }

        // Variables de présentation #6868 : palette éditable, exposée au
        // rendu public sous allowlist stricte (`#RRGGBB`).
        $colors = $this->publicColors($settings);

        if ($colors !== []) {
            $public['colors'] = $colors;
        }

        // Variable de marque `logo` : résolue côté serveur (uuid média →
        // URL publique de service #6872), jamais un chemin client. Le
        // `logo_id` brut, lui, reste INTERNE (hors allowlist).
        $logoUrl = $this->logoUrl($showcase);

        if ($logoUrl !== null) {
            $public['logo_url'] = $logoUrl;
        }

        return $public;
    }

    /**
     * Palette publique d'une vitrine (#6868) : sous-allowlist de
     * `settings.colors`, valeurs strictement `#RRGGBB` (majuscules). Toute
     * autre valeur (injection CSS, couleur nommée, `rgba()`) est ignorée —
     * le rendu retombe alors sur la palette du thème.
     *
     * @param  array<string, mixed>  $settings
     * @return array<string, string>
     */
    private function publicColors(array $settings): array
    {
        $colors = $settings['colors'] ?? null;

        if (! is_array($colors)) {
            return [];
        }

        $public = [];

        foreach (self::PUBLIC_COLOR_KEYS as $key) {
            $value = $colors[$key] ?? null;

            if (is_string($value) && preg_match('/^#[0-9A-Fa-f]{6}$/', $value) === 1) {
                $public[$key] = strtoupper($value);
            }
        }

        return $public;
    }

    /**
     * Médias effectivement référencés par la vitrine (logo + `image_id` des
     * sections) — minimisation : un média orphelin n'est pas exposé.
     *
     * @return list<array<string, mixed>>
     */
    private function publicMedia(CompanyShowcase $showcase): array
    {
        $referenced = $this->referencedMediaIds($showcase);

        if ($referenced === []) {
            return [];
        }

        $media = [];

        foreach ($this->media as $item) {
            if (! in_array($item->uuid, $referenced, true)) {
                continue;
            }

            $media[] = [
                'id' => $item->uuid,
                'kind' => $item->kind,
                'url' => $this->mediaUrl($showcase, $item->uuid),
                'width' => $item->width,
                'height' => $item->height,
            ];
        }

        return $media;
    }

    /**
     * @return list<string>
     */
    private function referencedMediaIds(CompanyShowcase $showcase): array
    {
        $settings = $showcase->settings ?? [];
        $ids = [];

        $logoId = $settings['logo_id'] ?? null;

        if (is_string($logoId) && $logoId !== '') {
            $ids[] = $logoId;
        }

        foreach ($this->sections as $section) {
            $contents = [is_array($section->content) ? $section->content : []];

            // #6874 — une surcouche de locale peut référencer un média
            // (image_id) : il doit rester exposé même s'il n'apparaît pas
            // dans le contenu de référence.
            if (is_array($section->translations)) {
                foreach ($section->translations as $translation) {
                    if (is_array($translation)) {
                        $contents[] = $translation;
                    }
                }
            }

            foreach ($contents as $content) {
                $imageId = $content['image_id'] ?? null;

                if (is_string($imageId) && $imageId !== '') {
                    $ids[] = $imageId;
                }

                $items = $content['items'] ?? null;

                if (! is_array($items)) {
                    continue;
                }

                foreach ($items as $item) {
                    if (! is_array($item)) {
                        continue;
                    }

                    $itemImageId = $item['image_id'] ?? null;

                    if (is_string($itemImageId) && $itemImageId !== '') {
                        $ids[] = $itemImageId;
                    }
                }
            }
        }

        /** @var list<string> $unique */
        $unique = array_values(array_unique($ids));

        return $unique;
    }

    private function logoUrl(CompanyShowcase $showcase): ?string
    {
        $settings = $showcase->settings ?? [];

        $logoId = $settings['logo_id'] ?? null;

        if (is_string($logoId) && $logoId !== '') {
            foreach ($this->media as $item) {
                if ($item->uuid === $logoId) {
                    return $this->mediaUrl($showcase, $item->uuid);
                }
            }
        }

        // Repli : URL de logo fournie telle quelle (externe) si valide.
        $stored = $settings['logo_url'] ?? null;

        if (is_string($stored) && trim($stored) !== '') {
            return $stored;
        }

        return null;
    }

    private function mediaUrl(CompanyShowcase $showcase, string $uuid): string
    {
        return url('/api/v1/public/vitrine/'.$showcase->slug.'/media/'.$uuid);
    }
}
