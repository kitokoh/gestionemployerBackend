<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaValidator;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (#6866) — ajout d'une section en fin de vitrine.
 *
 * Le `content` est validé contre le JSON Schema du type demandé AVANT
 * insertion (`ShowcaseSectionSchemaValidator` → 422 si invalide) ; le type
 * doit être connu du registre v1. `sort_order` = max existant + 10 (pas de
 * réécriture en masse : le réordonnancement est un use case dédié,
 * ReorderShowcaseSectionsAction). Si la vitrine est publiée, le cache
 * public est invalidé immédiatement (V-PUBLIC-API #6867).
 */
final class CreateShowcaseSectionAction
{
    public function __construct(
        private readonly ShowcaseSectionSchemaValidator $validator,
        private readonly ShowcasePublicCache $publicCache,
    ) {
    }

    public function execute(CompanyShowcase $showcase, string $type, array $content): CompanyShowcaseSection
    {
        if (! ShowcaseSectionSchemaRegistry::isKnownType($type)) {
            throw ValidationException::withMessages([
                'type' => [sprintf('Type de section inconnu : « %s ».', $type)],
            ]);
        }

        $content = $this->validator->validateOrFail($type, $content);

        $maxOrder = (int) CompanyShowcaseSection::query()
            ->where('showcase_id', $showcase->id)
            ->max('sort_order');

        /** @var CompanyShowcaseSection $section */
        $section = CompanyShowcaseSection::query()->create([
            'company_id' => $showcase->company_id,
            'showcase_id' => $showcase->id,
            'type' => $type,
            'content' => $content,
            'sort_order' => $maxOrder + 10,
            'schema_version' => ShowcaseSectionSchemaRegistry::SCHEMA_VERSION,
        ]);

        $this->invalidateIfPublished($showcase);

        return $section;
    }

    private function invalidateIfPublished(CompanyShowcase $showcase): void
    {
        if ($showcase->status === CompanyShowcaseStatus::Published) {
            $this->publicCache->forget($showcase->slug);
        }
    }
}
