<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use Illuminate\Validation\ValidationException;

/**
 * BC-27 SHOWCASE (#6866) — réordonnancement complet des sections d'une
 * vitrine (PATCH bulk : `ids` = ordre cible, liste complète attendue).
 *
 * Contrat strict : les ids transmis doivent correspondre EXACTEMENT aux
 * sections existantes de la vitrine (même ensemble, pas de doublon, pas
 * d'id étranger) — sinon 422 : le réordonnancement partiel (glisser un
 * élément) reste possible côté client en renvoyant la liste complète.
 * Écriture en une transaction ; cache public invalidé si publié.
 */
final class ReorderShowcaseSectionsAction
{
    public function __construct(private readonly ShowcasePublicCache $publicCache) {}

    /**
     * @param  list<int>  $orderedIds
     */
    public function execute(CompanyShowcase $showcase, array $orderedIds): void
    {
        $existingIds = CompanyShowcaseSection::query()
            ->where('showcase_id', $showcase->id)
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        sort($existingIds);
        $target = $orderedIds;
        sort($target);

        if ($existingIds !== $target) {
            throw ValidationException::withMessages([
                'ids' => ['La liste des ids doit couvrir exactement les sections existantes de la vitrine (même ensemble, sans doublon).'],
            ]);
        }

        foreach ($orderedIds as $index => $id) {
            CompanyShowcaseSection::query()
                ->whereKey((int) $id)
                ->where('showcase_id', $showcase->id)
                ->update(['sort_order' => ($index + 1) * 10]);
        }

        if ($showcase->status === CompanyShowcaseStatus::Published) {
            $this->publicCache->forget($showcase->slug);
        }
    }
}
