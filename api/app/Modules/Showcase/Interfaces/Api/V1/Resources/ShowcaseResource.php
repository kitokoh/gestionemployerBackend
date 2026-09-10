<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape privée (gestion) d'une vitrine (BC-27 SHOWCASE, #6866/#6870).
 *
 * Exposé aux seuls gestionnaires du tenant (routes authentifiées +
 * Policies) — contrairement à la ressource publique (#6867) qui ne doit
 * JAMAIS exposer `company_id`, `id` interne ni le jeton d'aperçu.
 *
 * Étendue par les lots v1 :
 *   - V-PUBLISH #6871 : `preview_token` + `preview_path` (lien d'aperçu) ;
 *   - V-RGPD #6875 : `legal` (bloc mentions légales / confidentialité).
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
            'settings' => is_array($settings) ? $settings : new \stdClass,
            'legal' => is_array($legal) ? $legal : new \stdClass,
            'preview_token' => $showcase->preview_token,
            'preview_path' => $showcase->preview_token !== null
                ? '/public/vitrine/'.$showcase->slug.'?token='.$showcase->preview_token
                : null,
            // #6870/#6874 — contrat de contenu exposé à l'éditeur admin : liste
            // des types de sections v1, champs localisables par type et locales
            // supportées. Source unique = ShowcaseSectionSchemaRegistry (le
            // jour où `products` BC-28 #6891 entre dans le registre, l'éditeur
            // le propose sans changement de code front).
            'editor_contract' => [
                'schema_version' => ShowcaseSectionSchemaRegistry::SCHEMA_VERSION,
                'section_types' => ShowcaseSectionSchemaRegistry::knownTypes(),
                'localizable_fields' => ShowcaseSectionSchemaRegistry::localizableFields(),
                'supported_locales' => ShowcaseSectionSchemaRegistry::supportedLocales(),
                'default_locale' => ShowcaseSectionSchemaRegistry::defaultLocale(),
                'rtl_locales' => ShowcaseSectionSchemaRegistry::RTL_LOCALES,
            ],
            'published_at' => $showcase->published_at?->toIso8601String(),
            'created_at' => $showcase->created_at?->toIso8601String(),
            'updated_at' => $showcase->updated_at?->toIso8601String(),
        ];
    }
}
