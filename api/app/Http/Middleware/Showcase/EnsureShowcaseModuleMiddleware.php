<?php

declare(strict_types=1);

namespace App\Http\Middleware\Showcase;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Gate du module Site vitrine tenant (BC-27 SHOWCASE, #6866).
 *
 * Exige que la company courante ait le feature flag `company_showcase`
 * activé (companies.features.company_showcase = true, mécanisme Core/
 * Feature — ShowcaseFeatures::COMPANY_SHOWCASE, #6865). Pattern calqué sur
 * EnsureCatalogModuleMiddleware (BC-28 #6881) et
 * EnsureRestaurantManagerModuleMiddleware (BC-25 #6159).
 *
 * Placé APRÈS le middleware `tenant`, qui a déjà résolu la company courante.
 * Kill switch opérationnel : désactiver le flag → 403 immédiat.
 */
class EnsureShowcaseModuleMiddleware
{
    /**
     * @param  Closure(Request): (Response)  $next
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

        if (! $company->hasFeature('company_showcase')) {
            return new JsonResponse([
                'error' => 'FEATURE_NOT_ENABLED',
                'message' => 'Your plan does not include the company showcase module.',
            ], 403);
        }

        return $next($request);
    }
}
