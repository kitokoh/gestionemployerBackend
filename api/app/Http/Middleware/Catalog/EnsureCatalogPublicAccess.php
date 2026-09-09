<?php

declare(strict_types=1);

namespace App\Http\Middleware\Catalog;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Accès public au catalogue B2B d'un tenant (BC-28 CATALOG, C-PUBLIC #6882).
 *
 * Résout le tenant par slug public (`/public/catalog/{companySlug}` — le
 * visiteur n'a AUCUN compte) puis pose le contexte tenant via
 * `TenantManager::withinTenant()` + marqueur `tenant_scope_required` :
 * le scope global BelongsToCompany s'applique → aucune fuite cross-tenant
 * (pattern calqué sur EnsureRestaurantPublicShopAccess, RESTO-805 #6226).
 *
 * Fail-closed uniforme 404 (pas de probing) : slug inconnu, company
 * suspendue/expirée, ou tenant sans feature flag `b2b_catalog` (le flag
 * n'est jamais divulgué publiquement).
 */
class EnsureCatalogPublicAccess
{
    public function __construct(private readonly TenantManager $tenants) {}

    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $slug = trim((string) $request->route('companySlug', ''));

        /** @var Company|null $company */
        $company = $slug === ''
            ? null
            : Company::query()->where('slug', $slug)->first();

        if (! $company instanceof Company
            || in_array($company->status, ['suspended', 'expired'], true)
            || ! $company->hasFeature('b2b_catalog')) {
            abort(404);
        }

        app()->instance('tenant_scope_required', true);

        try {
            return $this->tenants->withinTenant($company, fn (): Response => $next($request));
        } finally {
            app()->forgetInstance('tenant_scope_required');
        }
    }
}
