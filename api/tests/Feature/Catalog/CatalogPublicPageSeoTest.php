<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogCategory;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use Illuminate\Support\Facades\Cache;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG — fiche produit publique (#6883 C-PRODUCT-PAGE) et SEO
 * (#6888 C-SEO).
 *
 * La fiche publique expose photos (médias publiés), méta SEO dérivées du
 * contenu et CTA devis, avec produits liés publiés (jamais de brouillon) ;
 * le catalogue expose sa propre méta ; le sitemap ne liste que les produits
 * publiés.
 */
class CatalogPublicPageSeoTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'slug' => 'catalog-page-seo',
            'country' => 'DZ',
            'currency' => 'DZD',
        ]);
        $company->setFeature('b2b_catalog', true);
        $company->save();

        $this->company = $company;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function product(array $overrides = []): CatalogProduct
    {
        return app(TenantManager::class)->withinTenant($this->company, function () use ($overrides): CatalogProduct {
            /** @var CatalogProduct $product */
            $product = CatalogProduct::query()->create(array_merge([
                'company_id' => $this->company->id,
                'name' => 'Machine CNC',
                'slug' => 'machine-cnc',
                'description' => 'Fraiseuse 3 axes industrielle',
                'price_minor' => 1250000,
                'currency' => 'DZD',
                'unit' => 'piece',
                'status' => CatalogProductStatus::Published,
                'meta' => ['photos' => ['https://cdn.example.test/cnc-1.webp', 'https://cdn.example.test/cnc-2.webp', 42]],
            ], $overrides));

            return $product;
        });
    }

    private function collection(string $path): string
    {
        $response = $this->getJson($path)->assertOk();
        $content = $response->getContent();

        return is_string($content) ? $content : '';
    }

    public function test_product_page_exposes_photos_meta_and_inquiry_cta(): void
    {
        $this->product();

        $this->getJson('/api/v1/public/catalog/catalog-page-seo/products/machine-cnc')
            ->assertOk()
            ->assertJsonPath('data.slug', 'machine-cnc')
            ->assertJsonPath('data.photos.0', 'https://cdn.example.test/cnc-1.webp')
            // Seules les chaînes valides sortent (42 filtré).
            ->assertJsonCount(2, 'data.photos')
            ->assertJsonPath('data.meta.title', 'Machine CNC')
            ->assertJsonPath('data.meta.og_image', 'https://cdn.example.test/cnc-1.webp')
            ->assertJsonPath('data.meta.canonical_path', '/public/catalog/catalog-page-seo/products/machine-cnc')
            ->assertJsonPath('data.inquiry_path', '/public/catalog/catalog-page-seo/inquiries')
            ->assertJsonPath('data.related', []);
    }

    public function test_related_products_exclude_drafts_and_self(): void
    {
        $category = app(TenantManager::class)->withinTenant($this->company, function (): CatalogCategory {
            /** @var CatalogCategory $category */
            $category = CatalogCategory::query()->create([
                'company_id' => $this->company->id,
                'name' => 'Machines',
                'slug' => 'machines',
                'position' => 1,
            ]);

            return $category;
        });

        $this->product(['category_id' => $category->id]);
        $this->product(['name' => 'Perceuse', 'slug' => 'perceuse', 'category_id' => $category->id]);
        $this->product(['name' => 'Brouillon', 'slug' => 'brouillon', 'category_id' => $category->id, 'status' => CatalogProductStatus::Draft]);

        $response = $this->getJson('/api/v1/public/catalog/catalog-page-seo/products/machine-cnc')->assertOk();

        $response->assertJsonPath('data.related.0.slug', 'perceuse');
        $response->assertJsonCount(1, 'data.related');
        $this->assertStringNotContainsString('brouillon', $response->getContent() ?: '');
    }

    public function test_catalog_index_exposes_its_own_meta(): void
    {
        $this->product();

        $this->getJson('/api/v1/public/catalog/catalog-page-seo')
            ->assertOk()
            ->assertJsonPath('data.meta.title', $this->company->name)
            ->assertJsonPath('data.meta.canonical_path', '/public/catalog/catalog-page-seo')
            ->assertJsonPath('data.meta.indexable', true);
    }

    public function test_catalog_sitemap_lists_published_products_only(): void
    {
        $this->product();
        $this->product(['name' => 'Brouillon SEO', 'slug' => 'brouillon-seo', 'status' => CatalogProductStatus::Draft]);

        Cache::forget('catalog:public:sitemap');

        $xml = $this->collection('/api/v1/public/catalog/sitemap.xml');

        $this->assertStringContainsString('/public/catalog/catalog-page-seo/products/machine-cnc', $xml);
        $this->assertStringNotContainsString('brouillon-seo', $xml);
    }
}
