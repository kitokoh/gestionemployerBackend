<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Catalog\Domain\Support\CatalogPublicCache;
use Illuminate\Support\Facades\Cache;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6882 C-PUBLIC) — catalogue PUBLIC isolé :
 * accès SANS auth, DTO strict (0 champ interne), seuls les produits
 * publiés sont visibles, isolation cross-tenant, 404 fail-closed
 * (slug inconnu / flag absent), cache + invalidation à la publication.
 */
class CatalogPublicApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyNoFlag;

    private Employee $principalA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyA->setFeature('b2b_catalog', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('b2b_catalog', true);
        $companyB->save();
        $this->companyB = $companyB;

        /** @var Company $companyNoFlag */
        $companyNoFlag = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->companyNoFlag = $companyNoFlag;

        $this->principalA = $this->employee($companyA, 'principal');
    }

    public function test_public_list_without_auth_returns_only_published_with_strict_dto(): void
    {
        $this->storeCategory($this->principalA, ['name' => 'Machines industrielles', 'slug' => 'machines']);
        $this->storeProduct($this->principalA, [
            'name' => 'Machine CNC 3 axes',
            'slug' => 'cnc-3-axes',
            'category_id' => $this->categoryId('machines'),
            'price_minor' => 1_250_000,
            'status' => 'published',
        ]);
        $this->storeProduct($this->principalA, [
            'name' => 'Tournevis pro (brouillon)',
            'slug' => 'tournevis-pro',
            'status' => 'draft',
        ]);

        // SANS token/auth aucune : la liste ne montre que le produit publié.
        $response = $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug);

        $response->assertStatus(200)
            ->assertJsonPath('data.company.slug', $this->companyA->slug)
            ->assertJsonCount(1, 'data.categories')
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.slug', 'cnc-3-axes')
            ->assertJsonPath('data.products.0.category.slug', 'machines')
            ->assertJsonPath('data.products.0.price_minor', 1_250_000)
            ->assertJsonPath('data.products.0.currency', 'DZD');

        // DTO strict : AUCUN champ interne (id, company_id, status, meta, dates).
        $this->assertSame(
            ['slug', 'name', 'description', 'price_minor', 'currency', 'unit', 'category'],
            array_keys($response->json('data.products.0'))
        );
        $this->assertSame(
            ['slug', 'name', 'position'],
            array_keys($response->json('data.categories.0'))
        );
        $this->assertSame(
            ['slug', 'name'],
            array_keys($response->json('data.company'))
        );
    }

    public function test_public_product_fiche_404_for_draft_and_unknown(): void
    {
        $this->storeProduct($this->principalA, ['slug' => 'cnc-3-axes', 'status' => 'draft']);

        // Fiche brouillon → 404 propre (jamais visible en public).
        $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug.'/products/cnc-3-axes')
            ->assertStatus(404);

        // Slug inconnu → 404.
        $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug.'/products/inexistant')
            ->assertStatus(404);
    }

    public function test_public_fiche_returns_published_product(): void
    {
        $this->storeProduct($this->principalA, [
            'slug' => 'cnc-3-axes',
            'status' => 'published',
            'description' => 'Fraiseuse 3 axes',
        ]);

        $response = $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug.'/products/cnc-3-axes');

        $response->assertStatus(200)
            ->assertJsonPath('data.slug', 'cnc-3-axes')
            ->assertJsonPath('data.description', 'Fraiseuse 3 axes')
            ->assertJsonPath('data.unit', 'piece');
        $this->assertSame(
            ['slug', 'name', 'description', 'price_minor', 'currency', 'unit', 'category'],
            array_keys($response->json('data'))
        );
    }

    public function test_fail_closed_404_unknown_slug_no_flag_and_suspended(): void
    {
        $this->getJson('/api/v1/public/catalog/tenant-qui-nexiste-pas')
            ->assertStatus(404);

        // Tenant existant mais flag b2b_catalog absent → 404 (pas de probing).
        $this->getJson('/api/v1/public/catalog/'.$this->companyNoFlag->slug)
            ->assertStatus(404);

        // Tenant suspendu avec flag → 404.
        /** @var Company $suspended */
        $suspended = Company::factory()->create(['status' => 'suspended']);
        $suspended->setFeature('b2b_catalog', true);
        $suspended->save();
        $this->getJson('/api/v1/public/catalog/'.$suspended->slug)
            ->assertStatus(404);
    }

    public function test_cross_tenant_isolation(): void
    {
        $principalB = $this->employee($this->companyB, 'principal');
        $this->storeProduct($principalB, ['slug' => 'produit-b', 'status' => 'published']);

        // Le catalogue de A ne contient pas les produits de B.
        $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug)
            ->assertStatus(200)
            ->assertJsonCount(0, 'data.products');

        // Fiche cross-tenant → 404.
        $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug.'/products/produit-b')
            ->assertStatus(404);
    }

    public function test_index_category_filter(): void
    {
        $this->storeCategory($this->principalA, ['name' => 'Machines', 'slug' => 'machines']);
        $this->storeCategory($this->principalA, ['name' => 'Outillage', 'slug' => 'outillage']);
        $this->storeProduct($this->principalA, ['slug' => 'cnc', 'category_id' => $this->categoryId('machines'), 'status' => 'published']);
        $this->storeProduct($this->principalA, ['slug' => 'visseuse', 'category_id' => $this->categoryId('outillage'), 'status' => 'published']);

        $response = $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug.'?category=machines');

        $response->assertStatus(200)
            ->assertJsonCount(1, 'data.products')
            ->assertJsonPath('data.products.0.slug', 'cnc');
    }

    public function test_cache_snapshot_invalidated_on_publish(): void
    {
        $this->storeProduct($this->principalA, ['slug' => 'cnc-3-axes', 'status' => 'draft']);

        // 1er appel : 0 produit publié → snapshot mis en cache.
        $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug)
            ->assertJsonCount(0, 'data.products');
        $this->assertTrue(Cache::has(CatalogPublicCache::snapshotKey($this->companyA->id)));

        // Publication via l'API privée (binding par id) → invalidation du cache.
        $product = $this->storeProduct($this->principalA, ['name' => 'Machine CNC 3 axes bis', 'slug' => 'cnc-bis', 'status' => 'draft']);
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')
            ->assertStatus(200);

        $this->assertFalse(Cache::has(CatalogPublicCache::snapshotKey($this->companyA->id)));

        // 2e appel SANS auth : le produit publié apparaît (snapshot reconstruit).
        $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug)
            ->assertStatus(200)
            ->assertJsonCount(1, 'data.products');
    }

    // ── helpers (miroir CatalogApiTest) ───────────────────────────────────

    private function employee(Company $company, string $managerRole = 'employee'): Employee
    {
        $attributes = ['company_id' => $company->id, 'status' => 'active'];

        if ($managerRole !== 'employee') {
            $attributes['role'] = 'manager';
            $attributes['manager_role'] = $managerRole;
        } else {
            $attributes['role'] = 'employee';
        }

        /** @var Employee $employee */
        $employee = Employee::factory()->create($attributes);

        return $employee;
    }

    private function actingAsUser(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeCategory(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/catalog/categories', array_merge([
            'name' => 'Machines industrielles',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeProduct(Employee $actor, array $overrides = []): array
    {
        $this->actingAsUser($actor);

        return $this->postJson('/api/v1/catalog/products', array_merge([
            'name' => 'Machine CNC 3 axes',
            'price_minor' => 1_250_000,
            'currency' => 'DZD',
            'unit' => 'piece',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    private function categoryId(string $slug): int
    {
        $categories = $this->getJson('/api/v1/catalog/categories')->json('data');

        foreach ($categories as $category) {
            if ($category['slug'] === $slug) {
                return (int) $category['id'];
            }
        }

        $this->fail("Catégorie de slug '$slug' introuvable.");
    }
}
