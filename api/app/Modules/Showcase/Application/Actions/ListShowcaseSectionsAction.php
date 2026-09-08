<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;

/**
 * BC-27 SHOWCASE (#6866) — liste ordonnée des sections d'une vitrine.
 *
 * Ordre canonique : `sort_order` puis `id` (stable après réordonnancement
 * ou suppression). L'isolation tenant est portée par le scope
 * BelongsToCompany (company_id) ; le paramètre vitrine est vérifié par le
 * contrôleur (Policy).
 *
 * @return list<CompanyShowcaseSection>
 */
final class ListShowcaseSectionsAction
{
    public function execute(CompanyShowcase $showcase): array
    {
        return CompanyShowcaseSection::query()
            ->where('showcase_id', $showcase->id)
            ->ordered()
            ->get()
            ->all();
    }
}
