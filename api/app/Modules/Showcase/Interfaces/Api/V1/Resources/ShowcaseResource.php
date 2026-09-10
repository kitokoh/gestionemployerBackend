<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Support\ShowcaseLocales;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape privée (gestion) d'une vitrine (BC-27 SHOWCASE, #6866/#6870).
 *
 * Exposé aux seuls gestionnaires du tenant (routes authentifiées +
 * Policies) — contrairement à la ressource publique (#6867) qui ne doit
 * JAMAIS exposer `company_id`, `id` interne ni le jeton d'aperçu.
 *
 * Étendue v1 :
 *   - V-PUBLISH #6871 : `preview_token` + `preview_path` (l'éditeur admin
 *     construit le lien d'aperçu) ;
 *   - V-THEMES #6868 : `theme_config` (variables résolues du thème) ;
 *   - V-RGPD #6875 : `legal` (bloc légal éditable) ;
 *   - V-I18N #6874 : `locales` supportées par le rendu public.
 *
 * @mixin CompanyShowcase
 */
final class ShowcaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CompanyShowcase $showcase */
        $showcase = $this->resource;

        $settings = $showcase->settings;
        $legal = $showcase->legal;

        return [
            'id' => $showcase->id,
            'slug' => $showcase->slug,
            'status' => $showcase->status->value,
            'theme' => $showcase->theme,
            'theme_config' => [
                'id' => $showcase->theme,
                'variables' => ShowcaseThemeRegistry::resolvedVariables($showcase->theme, is_array($settings) ? $settings : []),
            ],
            'settings' => is_array($settings) ? $settings : new \stdClass,
            'legal' => is_array($legal) ? $legal : new \stdClass,
            'locales' => ShowcaseLocales::supported(),
            'preview_token' => $showcase->preview_token,
            'preview_path' => $showcase->preview_token !== null
                ? '/public/vitrine/'.$showcase->slug.'?token='.$showcase->preview_token
                : null,
            'published_at' => $showcase->published_at?->toIso8601String(),
            'created_at' => $showcase->created_at?->toIso8601String(),
            'updated_at' => $showcase->updated_at?->toIso8601String(),
        ];
    }
}
