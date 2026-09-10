<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Infrastructure\Support\CatalogPublicCache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

/**
 * Catalogue B2B PUBLIC d'un tenant (BC-28 CATALOG, C-PUBLIC #6882).
 *
 * Routes isolées (`throttle:shop-public` + `catalog.public`, SANS auth) :
 *   GET /public/catalog/{companySlug}                        → liste publique
 *   GET /public/catalog/{companySlug}/products/{productSlug} → fiche publique
 *
 * Le tenant est résolu par slug dans EnsureCatalogPublicAccess (404
 * fail-closed si slug inconnu / company suspendue-expirée / flag absent).
 *
 * DTO public STRICT — jamais de modèles internes : seuls nom, slug,
 * description, prix indicatif (minor units) + devise ISO + unité, et
 * catégorie {slug, name} sont exposés. Ni company_id, ni id, ni status,
 * ni `meta` (attributs/specs internes — revue RGPD spec §"public vs privé"),
 * ni stocks/marges/fournisseurs. Les médias arrivent avec C-MEDIA (#6885).
 *
 * Cache Redis TTL (`catalog.public_cache_ttl`, 600 s) : snapshot liste +
 * fiches produits, invalidés à la publication/dépublication et sur chaque
 * mutation tenant (CatalogPublicCache). 404 propre pour draft/inconnu.
 */
class CatalogPublicController extends Controller
{
    /**
     * GET /public/catalog/{companySlug} — catégories + produits publiés.
     * Filtre optionnel `?category={categorySlug}` appliqué après cache.
     */
    public function index(Request $request): JsonResponse
    {
        $company = currentCompany();

        /** @var array<string, mixed> $snapshot */
        $snapshot = Cache::remember(
            CatalogPublicCache::snapshotKey($company->id),
            (int) config('catalog.public_cache_ttl', 600),
            fn (): array => $this->buildSnapshot($company)
        );

        $categorySlug = trim((string) $request->query('category', ''));

        if ($categorySlug !== '') {
            $snapshot['products'] = array_values(array_filter(
                $snapshot['products'],
                fn (array $product): bool => ($product['category']['slug'] ?? null) === $categorySlug
            ));
        }

        return response()->json(['data' => $snapshot]);
    }

    /**
     * GET /public/catalog/{companySlug}/products/{productSlug} — fiche
     * publique d'un produit publié (404 si draft, inconnu ou cross-tenant).
     */
    public function show(string $companySlug, string $productSlug): JsonResponse
    {
        $company = currentCompany();

        /** @var array<string, mixed>|null $product */
        $product = Cache::remember(
            CatalogPublicCache::productKey($company->id, $productSlug),
            (int) config('catalog.public_cache_ttl', 600),
            fn (): ?array => $this->buildProduct($productSlug)
        );

        if ($product === null) {
            abort(404);
        }

        return response()->json(['data' => $product]);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildSnapshot(Company $company): array
    {
        $categories = CatalogCategory::query()
            ->orderBy('position')
            ->orderBy('name')
            ->get();

        $products = CatalogProduct::query()
            ->where('status', CatalogProductStatus::Published->value)
            ->orderBy('name')
            ->get();

        return [
            'company' => [
                'slug' => $company->slug,
                'name' => $company->name,
            ],
            'categories' => $categories
                ->map(fn (CatalogCategory $category): array => [
                    'slug' => $category->slug,
                    'name' => $category->name,
                    'position' => $category->position,
                ])
                ->values()
                ->all(),
            'products' => $products
                ->map(fn (CatalogProduct $product): array => $this->productPayload($product, $categories))
                ->values()
                ->all(),
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function buildProduct(string $productSlug): ?array
    {
        $product = CatalogProduct::query()
            ->where('slug', $productSlug)
            ->where('status', CatalogProductStatus::Published->value)
            ->first();

        if (! $product instanceof CatalogProduct) {
            return null;
        }

        $category = $product->category_id !== null
            ? CatalogCategory::query()->find($product->category_id)
            : null;

        return $this->productPayload($product, $category === null ? collect() : collect([$category]));
    }

    /**
     * @param  iterable<CatalogCategory>  $categories
     * @return array<string, mixed>
     */
    private function productPayload(CatalogProduct $product, iterable $categories): array
    {
        $category = $product->category_id !== null
            ? collect($categories)->first(fn (CatalogCategory $c): bool => $c->id === $product->category_id)
            : null;

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
    }
}
