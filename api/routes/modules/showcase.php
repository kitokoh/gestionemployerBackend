<?php

/**
 * Routes privées du module Site vitrine tenant (BC-27 SHOWCASE, #6866).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 — ne JAMAIS
 * re-préfixer `v1` (règle AGENTS.md).
 *
 * Middleware du groupe (convention modules, cf. catalog.php BC-28) :
 *   - throttle:api      → limite globale de l'API
 *   - auth:sanctum      → authentification (Sanctum)
 *   - token.refresh     → auto-refresh du token
 *   - tenant            → résolution de la company + garde-fous statut/archive
 *   - throttle:api-plan → limite selon le plan tarifaire
 *   - module.showcase   → feature flag companies.features.company_showcase
 *
 * Référence : docs/specifications/SOLUTION_SITE_VITRINE.md (§6 API privée).
 */

use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcaseSectionController;
use Illuminate\Support\Facades\Route;

Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.showcase'])
    ->prefix('showcase')
    ->group(function (): void {
        // Réordonnancement bulk (déclaré avant la route {section}).
        Route::put('/sections/order', [ShowcaseSectionController::class, 'reorder']);

        Route::get('/sections', [ShowcaseSectionController::class, 'index']);
        Route::post('/sections', [ShowcaseSectionController::class, 'store']);
        Route::get('/sections/{section}', [ShowcaseSectionController::class, 'show'])->whereNumber('section');
        Route::put('/sections/{section}', [ShowcaseSectionController::class, 'update'])->whereNumber('section');
        Route::delete('/sections/{section}', [ShowcaseSectionController::class, 'destroy'])->whereNumber('section');
    });
