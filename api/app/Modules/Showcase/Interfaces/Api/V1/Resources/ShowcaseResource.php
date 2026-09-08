<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape privée (gestion) d'une vitrine (BC-27 SHOWCASE, #6866/#6870).
 *
 * Exposé aux seuls gestionnaires du tenant (routes authentifiées +
 * Policies) — contrairement à la ressource publique (#6867) qui ne doit
 * JAMAIS exposer `company_id` ni `id` interne.
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

        return [
            'id' => $showcase->id,
            'slug' => $showcase->slug,
            'status' => $showcase->status->value,
            'theme' => $showcase->theme,
            'settings' => is_array($settings) ? $settings : new \stdClass(),
            'published_at' => $showcase->published_at?->toIso8601String(),
            'created_at' => $showcase->created_at?->toIso8601String(),
            'updated_at' => $showcase->updated_at?->toIso8601String(),
        ];
    }
}
