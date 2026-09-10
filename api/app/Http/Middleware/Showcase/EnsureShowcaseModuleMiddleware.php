<?php

declare(strict_types=1);

namespace App\Http\Middleware\Showcase;

use App\Modules\Showcase\Domain\Support\ShowcaseFeatures;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate de la vitrine entreprise (BC-27 SHOWCASE, #6865/#6866).
 *
 * Exige que la company courante ait le feature flag `company_showcase`
 * activé (companies.features.company_showcase = true) — pattern calqué sur
 * EnsureRestaurantManagerModuleMiddleware (module.restaurantmanager, #6159)
 * et EnsureTravelAgencyModuleMiddleware (module.travelagency).
 *
 * Placé APRÈS le middleware `tenant` (company courante déjà résolue) ;
 * kill switch opérationnel : désactiver le flag → 403 immédiat, sans
 * toucher aux données. La consultation publique (#6867) n'emprunte PAS ce
 * middleware : une vitrine publiée reste en ligne même si le flag est
 * coupé (décision PM — la route publique ne dépend que du statut publié).
 */
class EnsureShowcaseModuleMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $company = app()->bound('current_company') ? currentCompany() : null;

        if ($company === null) {
            return new JsonResponse([
                'error' => 'COMPANY_NOT_FOUND',
                'message' => 'COMPANY_NOT_FOUND',
            ], 403);
        }

        if (! $company->hasFeature(ShowcaseFeatures::COMPANY_SHOWCASE)) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the Showcase module.',
            ], 403);
        }

        return $next($request);
    }
}
