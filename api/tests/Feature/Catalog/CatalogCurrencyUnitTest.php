<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Catalog\Domain\Support\CatalogUnitsCurrencies;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6886, C-CURRENCY) — devises & unités v1 :
 * devise ISO supportée (registre pays + EUR/USD), défaut = devise du
 * tenant, unité en liste blanche, stockage minor units sans flottant,
 * formatage canonique sans arrondi.
 */
class CatalogCurrencyUnitTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'country' => 'SN',
            'currency' => 'XOF',
            'slug' => 'usine-currency-sn',
        ]);
        $company->setFeature('b2b_catalog', true);
        $company->save();
        $this->company = $company;

        $this->principal = $this->employee($company, 'principal');
    }

    private function employee(Company $company, string $managerRole = 'principal'): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => $managerRole,
            'status' => 'active',
        ]);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function storeProduct(array $payload): array
    {
        Sanctum::actingAs($this->principal);

        return $this->postJson('/api/v1/catalog/products', $payload)->json('data');
    }

    public function test_currency_defaults_to_tenant_currency_when_omitted(): void
    {
        $data = $this->storeProduct(['name' => 'Tissu coton', 'price_minor' => 15000]);

        $this->assertSame('XOF', $data['currency']);
        $this->assertSame('piece', $data['unit']);
    }

    public function test_explicit_supported_currency_and_unit_are_accepted(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/v1/catalog/products', [
            'name' => 'Machines',
            'price_minor' => 5000,
            'currency' => 'EUR',
            'unit' => 'tonne',
        ])->assertStatus(201)->assertJsonPath('data.currency', 'EUR')->assertJsonPath('data.unit', 'tonne');
    }

    public function test_unsupported_currency_is_rejected(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/v1/catalog/products', [
            'name' => 'Machines',
            'price_minor' => 100,
            'currency' => 'ZZZ',
        ])->assertStatus(422)->assertJsonValidationErrors('currency');
    }

    public function test_unknown_unit_is_rejected(): void
    {
        $this->actingAsUser();
        $this->postJson('/api/v1/catalog/products', [
            'name' => 'Machines',
            'price_minor' => 100,
            'unit' => 'machin',
        ])->assertStatus(422)->assertJsonValidationErrors('unit');
    }

    public function test_supported_currencies_cover_registry_and_international(): void
    {
        $currencies = CatalogUnitsCurrencies::supportedCurrencies();

        foreach (['DZD', 'MAD', 'TND', 'XOF', 'XAF', 'EUR', 'USD'] as $expected) {
            $this->assertContains($expected, $currencies);
        }
    }

    public function test_format_minor_units_is_float_free_and_deterministic(): void
    {
        $this->assertSame('1 250.00 DZD', CatalogUnitsCurrencies::formatMinorUnits(125_000, 'DZD'));
        $this->assertSame('0.05 XOF', CatalogUnitsCurrencies::formatMinorUnits(5, 'XOF'));
        $this->assertSame('2 000 000.99 EUR', CatalogUnitsCurrencies::formatMinorUnits(200_000_099, 'eur'));
    }

    public function test_update_preserves_currency_and_unit_when_omitted(): void
    {
        $created = $this->storeProduct([
            'name' => 'Moule injection',
            'price_minor' => 800_000,
            'currency' => 'MAD',
            'unit' => 'set',
        ]);

        $this->actingAsUser();
        $this->putJson('/api/v1/catalog/products/'.$created['id'], [
            'name' => 'Moule injection v2',
            'price_minor' => 900_000,
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.currency', 'MAD')
            ->assertJsonPath('data.unit', 'set');
    }

    private function actingAsUser(): void
    {
        Sanctum::actingAs($this->principal);
    }
}
