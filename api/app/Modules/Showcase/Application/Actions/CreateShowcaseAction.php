<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Application\Actions;

use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Support\ShowcaseThemeRegistry;

/**
 * BC-27 SHOWCASE (#6866/#6870) — création 1-clic de la vitrine du tenant
 * (US1 de la spec SOLUTION_SITE_VITRINE.md).
 *
 * Une seule vitrine par tenant (`company_id` unique) : la création est
 * idempotente côté lecture — si la vitrine existe déjà, elle est retournée
 * telle quelle (l'appelant distingue via le statut HTTP : 201 vs 200).
 *
 * - `slug` = slug du tenant (`companies.slug`, unique global) → URL publique
 *   `/vitrine/{companySlug}` résolvable sans registre public (V-PUBLIC-API
 *   #6867) et unicité globale gratuite ;
 * - statut `draft` (jamais publié implicitement), thème `default` (les
 *   3 thèmes v1 arrivent en V-THEMES #6868) ;
 * - settings nuls : les variables de marque (couleurs, logo) sont posées
 *   par l'édition (#6868/#6872).
 */
final class CreateShowcaseAction
{
    public function execute(Company $company): CompanyShowcase
    {
        /** @var CompanyShowcase|null $existing */
        $existing = CompanyShowcase::query()->where('company_id', $company->id)->first();

        if ($existing instanceof CompanyShowcase) {
            return $existing;
        }

        /** @var CompanyShowcase $showcase */
        $showcase = CompanyShowcase::query()->create([
            'company_id' => $company->id,
            'slug' => $company->slug,
            'status' => CompanyShowcaseStatus::Draft,
            'theme' => ShowcaseThemeRegistry::DEFAULT_THEME,
        ]);

        return $showcase;
    }
}
