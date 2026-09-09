<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Domain\Support\CatalogFeatures;
use App\Modules\Catalog\Domain\Support\CatalogPublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Catalogue public B2B (BC-28 CATALOG, #6882) — C-PUBLIC.
 *
 * SANS authentification : le tenant est résolu depuis le slug d'entreprise
 * (pattern PublicCareerController #1325, boutiques publiques #6114/#6226).
 * Expose UNIQUEMENT les catégories et produits PUBLIÉS, via un DTO public
 * explicite — jamais de modèles internes, ni stocks réels, ni données
 * RH/fournisseur, ni meta interne (0 donnée interne, spec C-PUBLIC).
 *
 * Tenant sans flag `b2b_catalog`, inconnu ou suspendu → 404 propre
 * (fail-closed : pas de fuite d'existence). Cache Redis TTL court
 * (CatalogPublicCache), invalidé à chaque mutation côté API privée.
 */
class CatalogPublicController extends Controller
{
    public function __construct(private readonly TenantManager $tenantManager) {}

    /**
     * GET /api/v1/public/catalog/{companySlug}
     *
     * Liste publique : company (nom/slug) + catégories + produits publiés.
     */
    public function index(Request $request, string $companySlug): JsonResponse
    {
        $company = $this->resolveCompany($companySlug);

        $payload = CatalogPublicCache::remember(
            $companySlug,
            fn (): array => $this->tenantManager->withinTenant(
                $company,
                fn (): array => $this->buildPublicPayload($company),
            ),
        );

        return response()->json(['data' => $payload]);
    }

    private function resolveCompany(string $companySlug): Company
    {
        $company = Company::query()
            ->where('slug', $companySlug)
            ->where('status', '!=', 'suspended')
            ->first();

        if ($company === null) {
            abort(404);
        }

        // Fail-closed : un tenant sans le flag n'expose aucun catalogue
        // (404 — pas de fuite d'existence du flag lui-même).
        if (! $company->hasFeature(CatalogFeatures::B2B_CATALOG)) {
            abort(404);
        }

        return $company;
    }

    /**
     * DTO public — liste blanche stricte de champs. Aucun id interne,
     * company_id, meta, statut ou horodatage n'est exposé.
     *
     * @return array{company: array{name: string, slug: string}, categories: list<array{slug: string, name: string}>, products: list<array{slug: string, name: string, description: string|null, price_minor: int, currency: string, unit: string, category: array{slug: string, name: string}|null}>}
     */
    private function buildPublicPayload(Company $company): array
    {
        /** @var list<CatalogCategory> $categories */
        $categories = CatalogCategory::query()
            ->where('company_id', $company->id)
            ->orderBy('name')
            ->get();

        /** @var list<CatalogProduct> $products */
        $products = CatalogProduct::query()
            ->where('company_id', $company->id)
            ->where('status', CatalogProductStatus::Published->value)
            ->orderBy('name')
            ->limit(500)
            ->get();

        $categoriesByKey = $categories->keyBy('id');

        return [
            'company' => [
                'name' => $company->name,
                'slug' => $company->slug,
            ],
            'categories' => $categories
                ->map(fn (CatalogCategory $category): array => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                ])
                ->values()
                ->all(),
            'products' => $products
                ->map(function (CatalogProduct $product) use ($categoriesByKey): array {
                    $category = $categoriesByKey->get($product->category_id);

                    return [
                        'slug' => $product->slug,
                        'name' => $product->name,
                        'description' => $product->description,
                        'price_minor' => $product->price_minor,
                        'currency' => $product->currency,
                        'unit' => $product->unit,
                        'category' => $category instanceof CatalogCategory
                            ? ['slug' => $category->slug, 'name' => $category->name]
                            : null,
                    ];
                })
                ->values()
                ->all(),
        ];
    }
}
