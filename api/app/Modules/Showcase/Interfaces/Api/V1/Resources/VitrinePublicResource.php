<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcaseLegalDefaults;
use App\Modules\Showcase\Domain\Support\ShowcaseLocales;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;
use App\Modules\Showcase\Infrastructure\Services\ShowcaseProductsResolver;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO PUBLIC d'une vitrine servie (BC-27 SHOWCASE).
 *
 * RÈGLE ABSOLUE : cette ressource n'expose QUE ce qu'un visiteur public a
 * le droit de voir — jamais `id`, `company_id`, `showcase_id`,
 * `created_at/updated_at`, ni aucun champ interne. Les `settings` sont
 * filtrés par allowlist (variables de marque scalaires : `colors`,
 * `brand_name`, `tagline`) ; le jeton d'aperçu n'est JAMAIS exposé.
 *
 * Étendue par les lots v1 :
 *   - V-THEMES #6868 : `theme_config.variables` (couleurs/typo résolues) ;
 *   - V-I18N #6874 : `lang` + contenu de section résolu par locale
 *     (`content_i18n` avec repli sur `content`) ;
 *   - V-SEO #6873 : `meta` (title/description/og_image/canonical) ;
 *   - V-RGPD #6875 : `legal` (mentions + confidentialité) + `cookies`
 *     (aucun cookie tiers) ;
 *   - C-VITRINE #6891 : sections `products` résolues via le catalogue BC-28
 *     (section omise si catalogue absent/vide — dépendance optionnelle).
 *
 * Le test de non-fuite ShowcasePublicApiTest verrouille cette shape.
 */
final class VitrinePublicResource extends JsonResource
{
    /**
     * Clés autorisées dans `settings` exposées au public (allowlist —
     * anti-fuite si un jour une clé interne s'y glisse).
     *
     * @var list<string>
     */
    public const PUBLIC_SETTINGS_KEYS = ['colors', 'brand_name', 'tagline'];

    /**
     * @param  list<CompanyShowcaseSection>  $sections
     */
    public function __construct(
        CompanyShowcase $showcase,
        private readonly array $sections,
        private readonly string $companyName,
        private readonly string $locale = ShowcaseLocales::DEFAULT,
        private readonly ?ShowcaseProductsResolver $productsResolver = null,
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

        $sections = $this->resolvedSections();

        return [
            'slug' => $showcase->slug,
            'company_name' => $this->companyName,
            'lang' => $this->locale,
            'locales' => ShowcaseLocales::supported(),
            'theme' => $showcase->theme,
            'theme_config' => [
                'id' => $showcase->theme,
                'variables' => ShowcaseThemeRegistry::resolvedVariables($showcase->theme, $showcase->settings ?? []),
            ],
            'published_at' => $showcase->published_at?->toIso8601String(),
            'settings' => $this->publicSettings($showcase),
            'legal' => ShowcaseLegalDefaults::resolved($showcase->legal),
            'cookies' => [
                'third_party' => false,
                'banner_required' => false,
                'policy' => ShowcaseLegalDefaults::resolved($showcase->legal)['privacy'],
            ],
            'meta' => $this->meta($sections),
            'sections' => $sections,
        ];
    }

    /**
     * Sections résolues : contenu localisé + produits BC-28 (section omise
     * si la dépendance catalogue est absente ou vide).
     *
     * @return list<array<string, mixed>>
     */
    private function resolvedSections(): array
    {
        $resolved = [];

        foreach ($this->sections as $section) {
            $content = ShowcaseLocales::resolveContent($section->content ?? [], $section->content_i18n, $this->locale);

            if ($section->type === ShowcaseSectionType::Products) {
                $items = $this->productsResolver?->resolve($content) ?? [];

                if ($items === []) {
                    continue; // dépendance BC-28 optionnelle — section absente
                }

                $resolved[] = [
                    'type' => $section->type->value,
                    'schema_version' => $section->schema_version,
                    'content' => $content + ['items' => $items],
                ];

                continue;
            }

            $resolved[] = [
                'type' => $section->type->value,
                'schema_version' => $section->schema_version,
                'content' => $content,
            ];
        }

        return $resolved;
    }

    /**
     * Méta SEO dérivées du contenu (V-SEO #6873) — jamais de champ interne.
     *
     * @param  list<array<string, mixed>>  $sections
     * @return array<string, mixed>
     */
    private function meta(array $sections): array
    {
        /** @var CompanyShowcase $showcase */
        $showcase = $this->resource;

        $settings = $showcase->settings ?? [];
        $brand = is_string($settings['brand_name'] ?? null) ? $settings['brand_name'] : $this->companyName;
        $tagline = is_string($settings['tagline'] ?? null) ? $settings['tagline'] : null;

        $heroHeading = null;
        $heroSubheading = null;
        $ogImage = null;

        foreach ($sections as $section) {
            if (($section['type'] ?? null) !== ShowcaseSectionType::Hero->value) {
                continue;
            }

            $content = is_array($section['content'] ?? null) ? $section['content'] : [];
            $heroHeading = is_string($content['heading'] ?? null) ? $content['heading'] : null;
            $heroSubheading = is_string($content['subheading'] ?? null) ? $content['subheading'] : null;
            $ogImage = is_string($content['image_url'] ?? null) ? $content['image_url'] : null;

            break;
        }

        return [
            'title' => trim(($heroHeading ?? $brand).($tagline !== null ? ' | '.$tagline : '')),
            'description' => $heroSubheading ?? $tagline,
            'og_image' => $ogImage,
            'canonical_path' => '/public/vitrine/'.$showcase->slug,
            'indexable' => true,
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

                // Scalaires uniquement (couleurs, nom, slogan) — toute
                // structure interne (logo_path, ids…) reste exclue.
                if (is_scalar($value) || $value === null) {
                    $public[$key] = $value;
                }
            }
        }

        return $public;
    }
}
