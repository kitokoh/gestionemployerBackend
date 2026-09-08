<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;

/**
 * BC-27 SHOWCASE (#6866) — suppression d'une section.
 *
 * Les `sort_order` restants ne sont pas réécrits (pas de compaction en
 * masse) : l'ordre d'affichage reste stable via `ordered()` (sort_order,
 * puis id). Cache public invalidé si la vitrine est publiée.
 */
final class DeleteShowcaseSectionAction
{
    public function __construct(private readonly ShowcasePublicCache $publicCache)
    {
    }

    public function execute(CompanyShowcase $showcase, CompanyShowcaseSection $section): void
    {
        $section->delete();

        if ($showcase->status === CompanyShowcaseStatus::Published) {
            $this->publicCache->forget($showcase->slug);
        }
    }
}
