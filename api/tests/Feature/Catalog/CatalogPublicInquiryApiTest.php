<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\Notification\Domain\Models\Notification;
use App\Modules\Notification\Domain\Models\NotificationPreference;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6884 C-LEAD) — formulaire public « Demander un devis » :
 * soumission sans auth → demande persistée (tenant correct, consentement
 * horodaté, rétention bornée) + lead CRM BC-11 (source b2b_catalog) +
 * notification in-app des managers ; honeypot/spam bloqué ; consentement
 * requis ; produit publié exigé ; isolation cross-tenant.
 */
class CatalogPublicInquiryApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyA->setFeature('b2b_catalog', true);
        $companyA->save();
        $this->companyA = $companyA;

        $this->principalA = $this->employee($companyA, 'principal');
    }

    public function test_valid_inquiry_creates_inquiry_lead_and_notification(): void
    {
        $this->seedPublishedProduct();

        $response = $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'quantity' => 3,
            'company_name' => 'Ateliers Boussaid SARL',
            'email' => 'achat@boussaid.dz',
            'message' => 'Besoin d un devis pour une livraison à Alger.',
            'consent' => true,
        ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'received');

        $this->withinTenantA(function (): void {
            // 1) Demande persistée côté Catalog (tenant A, consentement + rétention).
            /** @var CatalogInquiry $inquiry */
            $inquiry = CatalogInquiry::query()->where('email', 'achat@boussaid.dz')->firstOrFail();
            $this->assertSame('cnc-3-axes', $inquiry->product_slug);
            $this->assertSame('Ateliers Boussaid SARL', $inquiry->company_name);
            $this->assertSame(3, $inquiry->quantity);
            $this->assertNotNull($inquiry->consent_at);
            $this->assertNotNull($inquiry->retention_until);
            $this->assertNull($inquiry->ip_hash);

            // 2) Lead CRM BC-11 créé (source b2b_catalog, tenant correct).
            /** @var CrmLead $lead */
            $lead = CrmLead::query()->where('email', 'achat@boussaid.dz')->firstOrFail();
            $this->assertSame('b2b_catalog', $lead->source);
            $this->assertSame('new', $lead->status);
            $this->assertStringContainsString('cnc-3-axes', (string) $lead->notes);

            // 3) Notification in-app du manager (BC-13).
            $notification = Notification::query()
                ->where('employee_id', $this->principalA->id)
                ->first();
            $this->assertNotNull($notification, 'le manager doit recevoir une notification in-app');
        });
    }

    public function test_honeypot_filled_is_swallowed_without_persisting(): void
    {
        $this->seedPublishedProduct();

        $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => 'Bot SARL',
            'email' => 'bot@spam.example',
            'consent' => true,
            'company_website' => 'http://spam.example',
        ])->assertStatus(201)->assertJsonPath('data.status', 'received');

        $this->withinTenantA(function (): void {
            $this->assertSame(0, CatalogInquiry::query()->count());
            $this->assertSame(0, CrmLead::query()->count());
            $this->assertSame(0, Notification::query()->count());
        });
    }

    public function test_consent_is_required(): void
    {
        $this->seedPublishedProduct();

        $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => 'Ateliers SARL',
            'email' => 'achat@ateliers.dz',
        ])->assertStatus(422);

        $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => 'Ateliers SARL',
            'email' => 'achat@ateliers.dz',
            'consent' => false,
        ])->assertStatus(422);
    }

    public function test_inquiry_requires_published_product(): void
    {
        // Produit en brouillon : le formulaire doit le rejeter (422).
        $this->storeProduct($this->principalA, ['slug' => 'cnc-3-axes', 'status' => 'draft']);

        $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => 'Ateliers SARL',
            'email' => 'achat@ateliers.dz',
            'consent' => true,
        ])->assertStatus(422);

        // Slug inconnu → 422 aussi (validation stricte).
        $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'inexistant',
            'company_name' => 'Ateliers SARL',
            'email' => 'achat@ateliers.dz',
            'consent' => true,
        ])->assertStatus(422);
    }

    public function test_validation_rejects_bad_fields(): void
    {
        $this->seedPublishedProduct();

        $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => '',
            'email' => 'pas-un-email',
            'consent' => true,
        ])->assertStatus(422);

        $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => 'Ateliers SARL',
            'email' => 'achat@ateliers.dz',
            'quantity' => 0,
            'consent' => true,
        ])->assertStatus(422);
    }

    public function test_unknown_company_slug_returns_404(): void
    {
        $this->postJson('/api/v1/public/catalog/tenant-inconnu/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => 'Ateliers SARL',
            'email' => 'achat@ateliers.dz',
            'consent' => true,
        ])->assertStatus(404);
    }

    // ── helpers ────────────────────────────────────────────────────────────

    private function employee(Company $company, string $managerRole): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'status' => 'active',
            'role' => 'manager',
            'manager_role' => $managerRole,
        ]);

        // Canal in-app activé pour la notification de test.
        $this->withinTenantA(function () use ($employee): void {
            NotificationPreference::query()->updateOrCreate(
                ['company_id' => $this->companyA->id, 'employee_id' => $employee->id],
                ['app_enabled' => true]
            );
        });

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeProduct(Employee $actor, array $overrides = []): array
    {
        Sanctum::actingAs($actor);

        return $this->postJson('/api/v1/catalog/products', array_merge([
            'name' => 'Machine CNC 3 axes',
            'price_minor' => 1_250_000,
            'currency' => 'DZD',
            'unit' => 'piece',
        ], $overrides))
            ->assertStatus(201)
            ->json('data');
    }

    private function seedPublishedProduct(): void
    {
        $product = $this->storeProduct($this->principalA, ['slug' => 'cnc-3-axes', 'status' => 'draft']);
        Sanctum::actingAs($this->principalA);
        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);
    }

    private function withinTenantA(callable $callback): mixed
    {
        /** @var TenantManager $tenants */
        $tenants = app(TenantManager::class);

        return $tenants->withinTenant($this->companyA, fn (): mixed => $callback());
    }
}
