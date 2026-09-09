<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6885, C-BACKOFFICE) — back-office des demandes de devis :
 * liste (filtres statut/recherche), transitions de statut bornées, notes
 * internes, export CSV, RBAC (responsable uniquement) et isolation tenant.
 */
class CatalogQuoteBackofficeTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

    private string $quoteReference;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => 'usine-bo-dz']);
        $companyA->setFeature('b2b_catalog', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD', 'slug' => 'usine-bo-ma']);
        $companyB->setFeature('b2b_catalog', true);
        $companyB->save();
        $this->companyB = $companyB;

        $this->principalA = $this->employee($companyA, 'principal');
        $this->employeeA = $this->employee($companyA, 'employee');
        $this->principalB = $this->employee($companyB, 'principal');

        $this->quoteReference = $this->createQuoteFor($companyA, $this->principalA);
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

    private function createQuoteFor(Company $company, Employee $principal): string
    {
        Mail::fake();

        // Un produit publié est requis par le flux public (#6884).
        Sanctum::actingAs($principal);
        $slug = 'machine-bo-'.substr((string) $company->slug, -2);
        $product = $this->postJson('/api/v1/catalog/products', [
            'name' => 'Machine BO '.$company->slug,
            'slug' => $slug,
            'price_minor' => 1000,
            'currency' => $company->currency ?? 'DZD',
        ])->assertStatus(201)->json('data');
        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);

        $response = $this->postJson('/api/v1/public/catalog/'.$company->slug.'/leads', [
            'product_slug' => $product['slug'],
            'quantity' => 3,
            'buyer_company' => 'Acheteur Backoffice SARL',
            'contact_name' => 'Lina Haddad',
            'email' => 'achat-bo@acheteur.dz',
            'message' => 'Demande pour devis.',
            'consent_processing' => true,
        ])->assertStatus(201);

        return $response->json('data.reference');
    }

    private function actingAsUser(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    public function test_only_manager_can_list_quotes(): void
    {
        $this->actingAsUser($this->employeeA);
        $this->getJson('/api/v1/catalog/quotes')->assertStatus(403);

        $this->actingAsUser($this->principalA);
        $this->getJson('/api/v1/catalog/quotes')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('data.0.reference', $this->quoteReference)
            ->assertJsonPath('data.0.status', 'new')
            ->assertJsonPath('data.0.buyer.company', 'Acheteur Backoffice SARL')
            ->assertJsonPath('data.0.product.name', 'Machine BO');
    }

    public function test_quote_list_is_isolated_per_tenant(): void
    {
        $this->createQuoteFor($this->companyB, $this->principalB);

        $this->actingAsUser($this->principalB);
        $this->getJson('/api/v1/catalog/quotes')
            ->assertStatus(200)
            ->assertJsonPath('meta.total', 1);

        $this->actingAsUser($this->principalA);
        $this->getJson('/api/v1/catalog/quotes')->assertJsonPath('meta.total', 1);
    }

    public function test_status_transitions_are_bounded(): void
    {
        $this->actingAsUser($this->principalA);

        // new → contacted → quote_sent → closed : chemin nominal.
        $quoteId = $this->quoteIdFromReference($this->quoteReference);
        $this->patchJson('/api/v1/catalog/quotes/'.$quoteId, ['status' => 'contacted'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'contacted');
        $this->patchJson('/api/v1/catalog/quotes/'.$quoteId, ['status' => 'quote_sent'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'quote_sent');
        $this->patchJson('/api/v1/catalog/quotes/'.$quoteId, ['status' => 'closed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');

        // Statut terminal : plus aucune transition.
        $this->patchJson('/api/v1/catalog/quotes/'.$quoteId, ['status' => 'lost'])
            ->assertStatus(422)
            ->assertJsonValidationErrors('status');

        // Saut non autorisé (new → closed) et statut inconnu.
        $this->createQuoteFor($this->companyA, $this->principalA);
        $this->actingAsUser($this->principalA);
        $second = $this->getJson('/api/v1/catalog/quotes?status=new')->json('data.0.id');
        $this->patchJson('/api/v1/catalog/quotes/'.$second, ['status' => 'closed'])->assertStatus(422);
        $this->patchJson('/api/v1/catalog/quotes/'.$second, ['status' => 'inconnu'])->assertStatus(422);
    }

    public function test_internal_notes_can_be_updated(): void
    {
        $quoteId = $this->quoteIdFromReference($this->quoteReference);
        $this->actingAsUser($this->principalA);

        $this->patchJson('/api/v1/catalog/quotes/'.$quoteId, [
            'internal_notes' => 'Relancé par téléphone le 09/09.',
        ])
            ->assertStatus(200)
            ->assertJsonPath('data.internal_notes', 'Relancé par téléphone le 09/09.');

        $this->getJson('/api/v1/catalog/quotes')->assertJsonPath('data.0.internal_notes', 'Relancé par téléphone le 09/09.');
    }

    public function test_employee_cannot_access_quote_detail_actions_or_export(): void
    {
        $quoteId = $this->quoteIdFromReference($this->quoteReference);

        $this->actingAsUser($this->employeeA);
        $this->patchJson('/api/v1/catalog/quotes/'.$quoteId, ['status' => 'contacted'])->assertStatus(403);
        $this->getJson('/api/v1/catalog/quotes/export')->assertStatus(403);
    }

    public function test_cross_tenant_quote_is_not_accessible(): void
    {
        $quoteId = $this->quoteIdFromReference($this->quoteReference);

        $this->actingAsUser($this->principalB);
        $this->patchJson('/api/v1/catalog/quotes/'.$quoteId, ['status' => 'contacted'])->assertStatus(404);
    }

    public function test_csv_export_contains_headers_and_quote_rows(): void
    {
        $this->actingAsUser($this->principalA);

        $response = $this->get('/api/v1/catalog/quotes/export');
        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'text/csv; charset=UTF-8');

        $csv = $response->streamedContent();
        $this->assertStringContainsString('reference,created_at,status,product_name', $csv);
        $this->assertStringContainsString($this->quoteReference, $csv);
        $this->assertStringContainsString('Acheteur Backoffice SARL', $csv);
    }

    private function quoteIdFromReference(string $reference): int
    {
        return (int) \Illuminate\Support\Facades\DB::table('catalog_quote_requests')
            ->where('reference', $reference)
            ->value('id');
    }
}
