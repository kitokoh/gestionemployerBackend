<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape privée (gestion) d'une section de vitrine (BC-27 SHOWCASE, #6866).
 *
 * `content` est le contenu validé par schéma (le contrat exact par type est
 * porté par `schema_version` + ShowcaseSectionSchemaRegistry).
 *
 * @mixin CompanyShowcaseSection
 */
final class ShowcaseSectionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var CompanyShowcaseSection $section */
        $section = $this->resource;

        return [
            'id' => $section->id,
            'showcase_id' => $section->showcase_id,
            'type' => $section->type->value,
            'content' => $section->content ?? new \stdClass,
            'sort_order' => $section->sort_order,
            'schema_version' => $section->schema_version,
        ];
    }
}
