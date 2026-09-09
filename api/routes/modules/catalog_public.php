<?php

/**
 * Routes PUBLIQUES du module Catalog B2B (BC-28 CATALOG, C-PUBLIC #6882).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1, juste après
 * catalog.php — ne JAMAIS re-préfixer `v1` (règle AGENTS.md). URLs réelles :
 *   GET /api/v1/public/catalog/{companySlug}
 *   GET /api/v1/public/catalog/{companySlug}/products/{productSlug}
 *
 * Isolées des routes privées (spec §"Public vs privé strictement séparés",
 * pattern BC-27) : PAS d'auth Sanctum ni de middleware tenant — le tenant
 * est résolu par slug public dans `catalog.public`
 * (EnsureCatalogPublicAccess, 404 fail-closed), throttling renforcé
 * `throttle:shop-public` (anti-scraping par IP, TRAVEL-1001/#6114).
 *
 * DTO public dédié (CatalogPublicController) : 0 donnée interne
 * (ni stocks/marges/fournisseurs/company_id/meta). Cache Redis TTL
 * invalidé à la publication (CatalogPublicCache).
 *
 * Référence : docs/specifications/SOLUTION_CATALOGUE_B2B.md (§7 public),
 * issue #6882 (C-PUBLIC).
 */

use App\Modules\Catalog\Interfaces\Api\V1\Controllers\CatalogPublicController;
use App\Modules\Catalog\Interfaces\Api\V1\Controllers\CatalogPublicInquiryController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:shop-public', 'catalog.public'])
    ->prefix('public/catalog')
    ->group(function (): void {
        Route::get('/{companySlug}', [CatalogPublicController::class, 'index'])
            ->name('catalog.public.index');
        Route::get('/{companySlug}/products/{productSlug}', [CatalogPublicController::class, 'show'])
            ->name('catalog.public.product');
        Route::post('/{companySlug}/inquiries', [CatalogPublicInquiryController::class, 'store'])
            ->name('catalog.public.inquiries.store');
    });
