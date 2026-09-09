<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6882, C-PUBLIC) — catalogue public B2B :
 * accès SANS auth, DTO public strict (0 donnée interne), isolation
 * cross-tenant, 404 propre (tenant inconnu/suspendu/sans flag) et
 * invalidation du cache à la publication.
 */
class CatalogPublicTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyNoFlag;

    private Employee $principalA;

    private Employee $principalB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => 'usine-techcorp-dz']);
        $companyA->setFeature('b2b_catalog', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD', 'slug' => 'usine-pharmaplus-ma']);
        $companyB->setFeature('b2b_catalog', true);
        $companyB->save();
        $this->companyB = $companyB;

        /** @var Company $companyNoFlag */
        $companyNoFlag = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF', 'slug' => 'usine-sans-flag-sn']);
        $this->companyNoFlag = $companyNoFlag;

        $this->principalA = $this->employee($this->companyA, 'principal');
        $this->principalB = $this->employee($this->companyB, 'principal');
    }

    private function employee(Company $company, string $managerRole = 'employee'): Employee
    {
        $attributes = [
            'company_id' => $company->id,
            'status' => 'active',
        ];

        if ($managerRole === 'employee') {
            $attributes['role'] = 'employee';
        } else {
            $attributes['role'] = 'manager';
            $attributes['manager_role'] = $managerRole;
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

    /**
     * @return array<string, mixed>
     */
    private function publicCatalog(string $companySlug): array
    {
        return $this->getJson('/api/v1/public/catalog/'.$companySlug)
            ->assertStatus(200)
            ->json('data');
    }

    public function test_public_catalog_requires_no_auth_and_lists_only_published_products(): void
    {
        $category = $this->storeCategory($this->principalA, ['slug' => 'machines']);

        $published = $this->storeProduct($this->principalA, [
            'category_id' => $category['id'],
            'slug' => 'cnc-3-axes',
        ]);
        $this->storeProduct($this->principalA, ['slug' => 'cnc-5-axes-draft']);

        // Publication du premier produit (statut draft par défaut).
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/products/'.$published['id'].'/publish')->assertStatus(200);

        // Aucun token, aucun header d'auth — la route est publique.
        $data = $this->publicCatalog('usine-techcorp-dz');

        $this->assertSame('usine-techcorp-dz', $data['company']['slug']);
        $this->assertCount(1, $data['categories']);
        $this->assertSame('machines', $data['categories'][0]['slug']);

        $slugs = array_column($data['products'], 'slug');
        $this->assertContains('cnc-3-axes', $slugs);
        $this->assertNotContains('cnc-5-axes-draft', $slugs);
    }

    public function test_public_payload_is_a_strict_allowlist_without_internal_fields(): void
    {
        $category = $this->storeCategory($this->principalA, ['slug' => 'machines']);
        $product = $this->storeProduct($this->principalA, [
            'category_id' => $category['id'],
            'slug' => 'cnc-3-axes',
            'meta' => ['internal_note' => 'secret', 'supplier' => 'X SARL'],
        ]);

        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);

        $data = $this->publicCatalog('usine-techcorp-dz');

        $this->assertSame(['name', 'slug'], array_keys($data['company']));
        $this->assertSame(['slug', 'name'], array_keys($data['categories'][0]));
        $this->assertSame(
            ['slug', 'name', 'description', 'price_minor', 'currency', 'unit', 'category'],
            array_keys($data['products'][0])
        );
        // Jamais de données internes/sensibles dans le payload public.
        $this->assertArrayNotHasKey('company_id', $data['products'][0]);
        $this->assertArrayNotHasKey('meta', $data['products'][0]);
        $this->assertArrayNotHasKey('status', $data['products'][0]);
        $this->assertArrayNotHasKey('id', $data['products'][0]);
        $this->assertArrayNotHasKey('internal_note', $data['products'][0]);
        // Prix indicatif en minor units + devise + unité, catégorie embarquée.
        $this->assertSame(1_250_000, $data['products'][0]['price_minor']);
        $this->assertSame('DZD', $data['products'][0]['currency']);
        $this->assertSame('piece', $data['products'][0]['unit']);
        $this->assertSame(['slug' => 'machines', 'name' => 'Machines industrielles'], $data['products'][0]['category']);
    }

    public function test_catalog_is_isolated_per_tenant_slug(): void
    {
        $catA = $this->storeCategory($this->principalA, ['slug' => 'machines']);
        $prodA = $this->storeProduct($this->principalA, ['category_id' => $catA['id'], 'slug' => 'cnc-a']);
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/products/'.$prodA['id'].'/publish')->assertStatus(200);

        $catB = $this->storeCategory($this->principalB, ['slug' => 'pharma']);
        $prodB = $this->storeProduct($this->principalB, [
            'category_id' => $catB['id'],
            'slug' => 'gelule-b',
            'currency' => 'MAD',
            'price_minor' => 500,
        ]);
        $this->actingAsUser($this->principalB);
        $this->postJson('/api/v1/catalog/products/'.$prodB['id'].'/publish')->assertStatus(200);

        $dataA = $this->publicCatalog('usine-techcorp-dz');
        $this->assertContains('cnc-a', array_column($dataA['products'], 'slug'));
        $this->assertNotContains('gelule-b', array_column($dataA['products'], 'slug'));
        $this->assertNotContains('pharma', array_column($dataA['categories'], 'slug'));

        $dataB = $this->publicCatalog('usine-pharmaplus-ma');
        $this->assertContains('gelule-b', array_column($dataB['products'], 'slug'));
        $this->assertNotContains('cnc-a', array_column($dataB['products'], 'slug'));
    }

    public function test_unknown_suspended_or_flagless_company_returns_404(): void
    {
        $this->getJson('/api/v1/public/catalog/usine-inexistante')->assertStatus(404);

        $suspended = Company::factory()->create([
            'country' => 'DZ',
            'slug' => 'usine-suspendue',
            'status' => 'suspended',
        ]);
        $suspended->setFeature('b2b_catalog', true);
        $suspended->save();
        $this->getJson('/api/v1/public/catalog/usine-suspendue')->assertStatus(404);

        // Tenant existant mais sans le flag b2b_catalog → 404 (pas de fuite).
        $this->getJson('/api/v1/public/catalog/usine-sans-flag-sn')->assertStatus(404);
    }

    public function test_publish_invalidates_public_cache(): void
    {
        // 1er appel : liste vide, mise en cache.
        $empty = $this->publicCatalog('usine-techcorp-dz');
        $this->assertCount(0, $empty['products']);

        // Publication d'un produit côté API privée → le cache doit être purgé.
        $product = $this->storeProduct($this->principalA, ['slug' => 'cnc-cache']);
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);

        $fresh = $this->publicCatalog('usine-techcorp-dz');
        $this->assertContains('cnc-cache', array_column($fresh['products'], 'slug'));
    }

    public function test_unpublish_removes_product_from_public_catalog(): void
    {
        $product = $this->storeProduct($this->principalA, ['slug' => 'cnc-vitrine']);
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);

        $this->assertContains('cnc-vitrine', array_column($this->publicCatalog('usine-techcorp-dz')['products'], 'slug'));

        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/unpublish')->assertStatus(200);

        $after = $this->publicCatalog('usine-techcorp-dz');
        $this->assertNotContains('cnc-vitrine', array_column($after['products'], 'slug'));
    }

    public function test_product_without_category_is_listed_with_null_category(): void
    {
        $product = $this->storeProduct($this->principalA, ['slug' => 'cnc-nu']);
        $this->actingAsUser($this->principalA);
        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);

        $data = $this->publicCatalog('usine-techcorp-dz');
        $productPayload = collect($data['products'])->firstWhere('slug', 'cnc-nu');
        $this->assertNotNull($productPayload);
        $this->assertNull($productPayload['category']);
    }

    public function test_draft_status_enum_matches_public_visibility(): void
    {
        $this->assertSame('draft', CatalogProductStatus::Draft->value);
        $this->assertSame('published', CatalogProductStatus::Published->value);
    }
}
