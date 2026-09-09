<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6885 C-BACKOFFICE) — back-office tenant des demandes de
 * devis B2B : liste + filtres, transitions de statut (matrice spec §7) +
 * notes internes horodatées, export CSV, RBAC principal/rh/manager et
 * isolation cross-tenant (404).
 */
class CatalogInquiryBackofficeTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

    private ?string $productSlugA = null;

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
        $this->principalA = $this->employee($companyA, 'principal');
        $this->employeeA = $this->employee($companyA, 'employee');
        $this->principalB = $this->employee($companyB, 'principal');
    }

    public function test_manager_can_list_and_filter_inquiries(): void
    {
        $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz');
        $this->seedInquiry('Machines Atlantique', 'contact@atlantique.ma');

        Sanctum::actingAs($this->principalA);
        $this->getJson('/api/v1/catalog/inquiries')
            ->assertStatus(200)
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.company_name', 'Machines Atlantique')
            ->assertJsonPath('data.0.status', 'new')
            ->assertJsonPath('data.0.product_name', 'Machine CNC 3 axes')
            ->assertJsonPath('data.0.consent_at', fn (mixed $v): bool => is_string($v) && $v !== '')
            ->assertJsonPath('data.0.retention_until', fn (mixed $v): bool => is_string($v) && $v !== '');

        // Filtre par recherche (société) et par statut.
        $this->getJson('/api/v1/catalog/inquiries?q=boussaid')
            ->assertStatus(200)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.company_name', 'Ateliers Boussaid SARL');

        $this->getJson('/api/v1/catalog/inquiries?status=closed')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');
    }

    public function test_status_transition_workflow_and_notes(): void
    {
        $inquiryId = $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz');

        Sanctum::actingAs($this->principalA);

        // nouveau → contacté (note horodatée ajoutée).
        $this->patchJson('/api/v1/catalog/inquiries/'.$inquiryId.'/status', [
            'status' => 'contacted',
            'note' => 'Rappel téléphonique effectué',
        ])->assertStatus(200)
            ->assertJsonPath('data.status', 'contacted')
            ->assertJsonPath('data.notes', fn (string $notes): bool => str_contains($notes, 'Rappel téléphonique effectué'));

        // contacté → devis envoyé.
        $this->patchJson('/api/v1/catalog/inquiries/'.$inquiryId.'/status', ['status' => 'quote_sent'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'quote_sent');

        // devis envoyé → clos.
        $this->patchJson('/api/v1/catalog/inquiries/'.$inquiryId.'/status', ['status' => 'closed'])
            ->assertStatus(200)
            ->assertJsonPath('data.status', 'closed');
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $inquiryId = $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz');

        Sanctum::actingAs($this->principalA);

        // Saut illégal nouveau → devis envoyé.
        $this->patchJson('/api/v1/catalog/inquiries/'.$inquiryId.'/status', ['status' => 'quote_sent'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_INQUIRY_STATUS_TRANSITION');

        // Statut inconnu → 422 (validation).
        $this->patchJson('/api/v1/catalog/inquiries/'.$inquiryId.'/status', ['status' => 'archived'])
            ->assertStatus(422);

        // Terminal : closed → immuable.
        $this->seedInquiryStatus($inquiryId, 'closed');
        $this->patchJson('/api/v1/catalog/inquiries/'.$inquiryId.'/status', ['status' => 'lost'])
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_INQUIRY_STATUS_TRANSITION');
    }

    public function test_employee_cannot_access_backoffice(): void
    {
        Sanctum::actingAs($this->employeeA);

        $this->getJson('/api/v1/catalog/inquiries')->assertStatus(403);
        $this->getJson('/api/v1/catalog/inquiries/export')->assertStatus(403);
    }

    public function test_cross_tenant_isolation(): void
    {
        $inquiryId = $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz');

        // Le manager du tenant B ne voit ni ne modifie les demandes de A.
        Sanctum::actingAs($this->principalB);

        $this->getJson('/api/v1/catalog/inquiries')
            ->assertStatus(200)
            ->assertJsonCount(0, 'data');

        $this->patchJson('/api/v1/catalog/inquiries/'.$inquiryId.'/status', ['status' => 'contacted'])
            ->assertStatus(404);
    }

    public function test_csv_export_contains_header_and_rows(): void
    {
        $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz');

        Sanctum::actingAs($this->principalA);

        $response = $this->get('/api/v1/catalog/inquiries/export');

        $response->assertStatus(200)
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');

        $body = $response->streamedContent();
        $this->assertStringContainsString('product_name', $body);
        $this->assertStringContainsString('Ateliers Boussaid SARL', $body);
        $this->assertStringContainsString('achat@boussaid.dz', $body);
        $this->assertStringContainsString('new', $body);
    }

    // ── helpers ───────────────────────────────────────────────────────────

    private function employee(Company $company, string $managerRole): Employee
    {
        $attributes = ['company_id' => $company->id, 'status' => 'active'];

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

    /**
     * Crée une demande via le formulaire public (SANS auth) puis retourne son id.
     */
    private function seedInquiry(string $companyName, string $email): int
    {
        // Produit publié du tenant A créé UNE fois par test (slug stable).
        if ($this->productSlugA === null) {
            Sanctum::actingAs($this->principalA);
            $product = $this->postJson('/api/v1/catalog/products', [
                'name' => 'Machine CNC 3 axes',
                'slug' => 'cnc-3-axes',
                'price_minor' => 1_250_000,
                'currency' => 'DZD',
                'unit' => 'piece',
            ])->assertStatus(201)->json('data');
            $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);
            $this->productSlugA = (string) $product['slug'];
        }

        // Soumission publique (SANS auth).
        $response = $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => $this->productSlugA,
            'company_name' => $companyName,
            'email' => $email,
            'message' => 'Besoin d un devis.',
            'consent' => true,
        ])->assertStatus(201);

        return (int) $response->json('data.reference');
    }

    private function seedInquiryStatus(int $inquiryId, string $status): void
    {
        $this->withinTenantA(function () use ($inquiryId, $status): void {
            /** @var CatalogInquiry $inquiry */
            $inquiry = CatalogInquiry::query()->findOrFail($inquiryId);
            $inquiry->forceFill(['status' => $status])->save();
        });
    }

    private function withinTenantA(callable $callback): mixed
    {
        /** @var TenantManager $tenants */
        $tenants = app(TenantManager::class);

        return $tenants->withinTenant($this->companyA, fn (): mixed => $callback());
    }
}
