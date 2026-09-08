<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * DTO PUBLIC d'une vitrine publiée (BC-27 SHOWCASE, #6867 — P0).
 *
 * RÈGLE ABSOLUE : cette ressource n'expose QUE ce qu'un visiteur public a
 * le droit de voir — jamais `id`, `company_id`, `showcase_id`,
 * `created_at/updated_at`, ni aucun champ interne. Les `settings` sont
 * filtrés par allowlist (variables de marque scalaires : `colors`,
 * `brand_name`, `tagline`) ; logo/médias internes arrivent via V-MEDIA
 * #6872. Le test de non-fuite ShowcasePublicApiTest verrouille cette shape.
 *
 * Consommation : rendu SSR des thèmes (V-THEMES #6868), site vitrine public.
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
    public function __construct(CompanyShowcase $showcase, private readonly array $sections, private readonly string $companyName)
    {
        parent::__construct($showcase);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CompanyShowcase $showcase */
        $showcase = $this->resource;

        return [
            'slug' => $showcase->slug,
            'company_name' => $this->companyName,
            'theme' => $showcase->theme,
            'published_at' => $showcase->published_at?->toIso8601String(),
            'settings' => $this->publicSettings($showcase),
            'sections' => array_map(
                static fn (CompanyShowcaseSection $section): array => [
                    'type' => $section->type->value,
                    'schema_version' => $section->schema_version,
                    'content' => $section->content ?? new \stdClass(),
                ],
                $this->sections
            ),
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
