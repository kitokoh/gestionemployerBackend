<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Resources;

use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shape privée (gestion) d'un média de vitrine (BC-27 SHOWCASE, #6872).
 *
 * `id` est l'`uuid` public stable (référence à poser dans
 * `settings.logo_id` / `content.image_id`) — jamais l'id interne, jamais un
 * chemin. L'URL publique de service est
 * `/api/v1/public/vitrine/{slug}/media/{uuid}` (le slug vient de GET
 * `/showcase`).
 *
 * @mixin ShowcaseMedia
 */
final class ShowcaseMediaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var ShowcaseMedia $media */
        $media = $this->resource;

        return [
            'id' => $media->uuid,
            'kind' => $media->kind,
            'section_id' => $media->section_id,
            'original_name' => $media->original_name,
            'mime_type' => $media->mime_type,
            'size' => $media->size,
            'width' => $media->width,
            'height' => $media->height,
            'created_at' => $media->created_at?->toIso8601String(),
        ];
    }
}
