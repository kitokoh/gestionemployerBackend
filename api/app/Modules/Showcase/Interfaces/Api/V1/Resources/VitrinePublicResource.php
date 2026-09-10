<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
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
 *     `cookies` (aucun cookie tiers).
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
    public const PUBLIC_SETTINGS_KEYS = ['colors', 'brand_name', 'tagline', 'og_image'];

    /**
     * @param  list<CompanyShowcaseSection>  $sections
     */
    public function __construct(
        CompanyShowcase $showcase,
        private readonly array $sections,
        private readonly string $companyName,
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
            'published_at' => $showcase->published_at?->toIso8601String(),
            'settings' => $this->publicSettings($showcase),
            'legal' => $this->legal($showcase),
            'cookies' => $this->cookies($showcase),
            'meta' => $this->meta($sections),
            'sections' => $sections,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function publicSections(): array
    {
        return array_map(
            static fn (CompanyShowcaseSection $section): array => [
                'type' => $section->type->value,
                'schema_version' => $section->schema_version,
                'content' => $section->content ?? new \stdClass,
            ],
            $this->sections
        );
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
            if (array_key_exists($key, $settings)) {
                $value = $settings[$key];

                // Scalaires uniquement (couleurs, nom, slogan…) — toute
                // structure interne (logo_path, ids…) reste exclue.
                if (is_scalar($value) || $value === null) {
                    $public[$key] = $value;
                }
            }
        }

        return $public;
    }
}
