<?php

declare(strict_types=1);

/**
 * Routes du module Showcase (BC-27 SHOWCASE).
 *
 * Chargé depuis routes/api.php à l'intérieur du groupe /v1 (jamais de
 * re-préfixe `v1`). Référence : docs/specifications/SOLUTION_SITE_VITRINE.md
 * (§6 API) et issues #6866/#6867/#6871/#6873/#6875.
 *
 * PRIVÉ (gestion tenant) — middlewares du groupe :
 *   - throttle:api, auth:sanctum, token.refresh, tenant, throttle:api-plan
 *     (hérités des modules, cf. crm.php) ;
 *   - module.showcase → feature flag companies.features.company_showcase
 *     (fail-closed : tenant sans le flag → 403) ;
 *   - api.manager:principal,rh → édition réservée au responsable du tenant
 *     (cohérent avec CompanyShowcasePolicy).
 *
 * PUBLIC (vitrine consultée sans auth) — groupe isolé `throttle:shop-public`
 * (aucun middleware tenant/utilisateur), DTO public dédié (#6867) :
 *   - `GET /public/vitrine/{slug}` (aperçu brouillon via `?token=`, #6871) ;
 *   - `POST /public/vitrine/{slug}/contact` (formulaire contact, #6875) ;
 *   - `GET /public/vitrine/sitemap.xml` + `robots.txt` (#6873).
 */

use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcaseController;
use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcasePublicContactController;
use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcasePublicController;
use App\Modules\Showcase\Interfaces\Api\V1\Controllers\ShowcaseSectionController;
use Illuminate\Support\Facades\Route;

// ── Publique (isolée, P0 #6867 / #6871 / #6873 / #6875) ─────────────────────
Route::middleware(['throttle:shop-public'])
    ->prefix('public/vitrine')
    ->group(function (): void {
        // #6873 — SEO : sitemap des vitrines PUBLIÉES uniquement + robots.txt.
        Route::get('/sitemap.xml', [ShowcasePublicController::class, 'sitemap'])
            ->name('showcase.public.sitemap');
        Route::get('/robots.txt', [ShowcasePublicController::class, 'robots'])
            ->name('showcase.public.robots');

        // #6875 — formulaire de contact public (rate limit renforcé + honeypot
        // + consentement RGPD ; notification tenant BC-13 via événement).
        Route::post('/{slug}/contact', [ShowcasePublicContactController::class, 'store'])
            ->middleware('throttle:showcase-contact')
            ->where('slug', '[A-Za-z0-9\-_]{1,160}')
            ->name('showcase.public.contact');

        // #6867 — vitrine publiée (aperçu brouillon via `?token=` #6871).
        Route::get('/{slug}', [ShowcasePublicController::class, 'show'])
            ->where('slug', '[A-Za-z0-9\-_]{1,160}')
            ->name('showcase.public.show');
    });

// ── Privée (gestion tenant, #6866) ──────────────────────────────────────────
Route::middleware(['throttle:api', 'auth:sanctum', 'token.refresh', 'tenant', 'throttle:api-plan', 'module.showcase', 'api.manager:principal,rh'])
    ->prefix('showcase')
    ->group(function (): void {
        // Vitrine du tenant (création 1-clic US1 — socle des issues #6866/#6870).
        Route::get('/', [ShowcaseController::class, 'show']);
        Route::post('/', [ShowcaseController::class, 'store']);

        // V-PUBLISH #6871 — workflow draft → published / published → draft +
        // jeton d'aperçu privé.
        Route::post('/publish', [ShowcaseController::class, 'publish']);
        Route::post('/unpublish', [ShowcaseController::class, 'unpublish']);
        Route::post('/preview-token', [ShowcaseController::class, 'previewToken']);

        // V-RGPD #6875 — variables de marque + bloc légal éditables.
        Route::patch('/settings', [ShowcaseController::class, 'updateSettings']);

        // Sections (contrat JSON Schema + CRUD, #6866).
        Route::get('/sections', [ShowcaseSectionController::class, 'index']);
        Route::post('/sections', [ShowcaseSectionController::class, 'store']);
        Route::post('/sections/reorder', [ShowcaseSectionController::class, 'reorder']);
        Route::patch('/sections/{section}', [ShowcaseSectionController::class, 'update'])->whereNumber('section');
        Route::delete('/sections/{section}', [ShowcaseSectionController::class, 'destroy'])->whereNumber('section');
    });
