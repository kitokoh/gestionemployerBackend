<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6886 C-CURRENCY) — devises & unités v1 :
 * whitelists strictes (devise ISO non listée → 422, unité non canonique →
 * 422), devise par défaut = devise du tenant quand absente, conservation à
 * l'update. Prix toujours en minor units (int), aucun flottant exposé.
 */
class CatalogCurrencyRulesTest extends TestCase
{
    use RefreshTenantDatabase;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $company->setFeature('b2b_catalog', true);
        $company->save();
        $this->principal = $this->employee($company);
    }

    public function test_currency_defaults_to_tenant_currency_when_omitted(): void
    {
        $product = $this->storeProduct(['price_minor' => 1_250_000])
            ->assertStatus(201)
            ->json('data');

        // Devise non fournie → devise du tenant (DZD), pas un défaut global.
        $this->assertSame('DZD', $product['currency']);
    }

    public function test_non_whitelisted_currency_is_rejected(): void
    {
        $this->storeProduct(['currency' => 'BTC'])
            ->assertStatus(422);

        $this->storeProduct(['currency' => 'USD'])
            ->assertStatus(201); // USD est dans la whitelist v1.
    }

    public function test_non_canonical_unit_is_rejected(): void
    {
        $this->storeProduct(['unit' => 'boite'])
            ->assertStatus(422);

        $this->storeProduct(['unit' => 'tonne'])
            ->assertStatus(201);
    }

    public function test_update_keeps_currency_when_omitted(): void
    {
        $product = $this->storeProduct(['currency' => 'EUR', 'price_minor' => 999])
            ->assertStatus(201)
            ->json('data');

        Sanctum::actingAs($this->principal);
        $updated = $this->putJson('/api/v1/catalog/products/'.$product['id'], [
            'name' => 'Machine CNC 3 axes v2',
            'price_minor' => 1_199,
        ])->assertStatus(200)->json('data');

        $this->assertSame('EUR', $updated['currency']);
        $this->assertSame(1_199, $updated['price_minor']);
        $this->assertIsInt($updated['price_minor']);
    }

    // ── helpers (miroir CatalogApiTest) ───────────────────────────────────

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => 'principal',
        ]);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function storeProduct(array $overrides = []): mixed
    {
        Sanctum::actingAs($this->principal);

        return $this->postJson('/api/v1/catalog/products', array_merge([
            'name' => 'Machine CNC 3 axes',
            'price_minor' => 1_250_000,
            'currency' => 'DZD',
            'unit' => 'piece',
        ], $overrides));
    }
}
