<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use App\Modules\Showcase\Infrastructure\Services\ShowcasePublicCache;
use App\Modules\Showcase\Interfaces\Api\V1\Resources\VitrinePublicResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * BC-27 SHOWCASE (#6867 — P0) — API publique d'une vitrine.
 *
 * Route isolée SANS auth (groupe `throttle:shop-public`), zéro donnée
 * interne : le tenant est résolu par le slug (pattern PublicCareerController
 * #1325 — `companies.slug` en schéma public), puis lecture dans le schéma
 * tenant via TenantManager::withinTenant avec le scope company_id. Le DTO
 * public (VitrinePublicResource) est le SEUL contrat exposé — jamais les
 * modèles Eloquent bruts.
 *
 * - 404 si slug inconnu / société suspendue / vitrine absente ou non
 *   publiée (brouillon jamais indexable) ;
 * - cache Redis TTL (ShowcasePublicCache, clé = slug) invalidé à chaque
 *   mutation d'une vitrine publiée (sections #6866, publication #6871).
 */
final class ShowcasePublicController extends Controller
{
    public function __construct(
        private readonly TenantManager $tenantManager,
        private readonly ShowcasePublicCache $cache,
    ) {}

    public function show(Request $request, string $slug): JsonResponse
    {
        $payload = $this->cache->remember($slug, function () use ($request, $slug): ?array {
            /** @var Company|null $company */
            $company = Company::query()
                ->where('slug', $slug)
                ->where('status', '!=', 'suspended')
                ->first();

            if (! $company instanceof Company) {
                return null;
            }

            return $this->tenantManager->withinTenant($company, function () use ($request, $slug, $company): ?array {
                /** @var CompanyShowcase|null $showcase */
                $showcase = CompanyShowcase::query()
                    ->where('slug', $slug)
                    ->first();

                if (! $showcase instanceof CompanyShowcase || $showcase->status !== CompanyShowcaseStatus::Published) {
                    return null;
                }

                /** @var list<CompanyShowcaseSection> $sections */
                $sections = CompanyShowcaseSection::query()
                    ->where('showcase_id', $showcase->id)
                    ->ordered()
                    ->get()
                    ->all();

                $resource = new VitrinePublicResource($showcase, $sections, $company->name);

                return $resource->resolve($request);
            });
        });

        if ($payload === null) {
            abort(Response::HTTP_NOT_FOUND);
        }

        return response()->json(['data' => $payload]);
    }
}
