<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6884, C-LEAD) — demande de devis/contact B2B :
 * lead CRM créé dans le bon tenant, honeypot anti-spam, consentement
 * RGPD requis, produit publié exigé, isolation cross-tenant.
 */
class CatalogQuoteLeadTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Employee $principalA;

    private array $productA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => 'usine-lead-dz']);
        $companyA->setFeature('b2b_catalog', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD', 'slug' => 'usine-lead-ma']);
        $companyB->setFeature('b2b_catalog', true);
        $companyB->save();
        $this->companyB = $companyB;

        $this->principalA = $this->manager($companyA);
        $this->productA = $this->storeAndPublishProduct($this->principalA, [
            'name' => 'Machine CNC 3 axes',
            'slug' => 'cnc-3-axes',
            'price_minor' => 1_250_000,
            'currency' => 'DZD',
        ]);
    }

    private function manager(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        return $employee;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function storeAndPublishProduct(Employee $actor, array $overrides = []): array
    {
        Sanctum::actingAs($actor);

        $product = $this->postJson('/api/v1/catalog/products', array_merge([
            'name' => 'Produit lead',
            'price_minor' => 1000,
            'currency' => 'DZD',
        ], $overrides))->assertStatus(201)->json('data');

        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);

        return $product;
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return \Illuminate\Testing\TestResponse
     */
    private function submitQuote(string $companySlug, array $overrides = [])
    {
        return $this->postJson('/api/v1/public/catalog/'.$companySlug.'/leads', array_merge([
            'product_slug' => 'cnc-3-axes',
            'quantity' => 2,
            'buyer_company' => 'Acheteur Industriel SARL',
            'contact_name' => 'Karim Benali',
            'email' => 'achat@acheteur.dz',
            'message' => 'Besoin pour livraison Q1.',
            'consent_processing' => true,
        ], $overrides));
    }

    public function test_valid_submission_creates_lead_in_the_correct_tenant(): void
    {
        Mail::fake();

        $response = $this->submitQuote('usine-lead-dz');

        $response->assertStatus(201)
            ->assertJsonPath('data.status', 'received')
            ->assertJsonStructure(['data' => ['reference']]);

        $this->assertDatabaseHas('crm_leads', [
            'company_id' => (string) $this->companyA->id,
            'email' => 'achat@acheteur.dz',
            'company_name' => 'Acheteur Industriel SARL',
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'source' => 'catalog',
            'status' => 'new',
        ]);

        // Aucun lead chez le tenant B.
        $this->assertDatabaseMissing('crm_leads', ['company_id' => (string) $this->companyB->id]);
    }

    public function test_submission_without_rgpd_consent_is_rejected(): void
    {
        $this->submitQuote('usine-lead-dz', ['consent_processing' => false])
            ->assertStatus(422)
            ->assertJsonValidationErrors('consent_processing');

        $this->submitQuote('usine-lead-dz', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('consent_processing');

        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_honeypot_filled_returns_fake_success_without_creating_lead(): void
    {
        $response = $this->submitQuote('usine-lead-dz', ['website' => 'http://spam.example']);

        $response->assertStatus(201);

        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_unpublished_or_unknown_product_returns_404(): void
    {
        $this->submitQuote('usine-lead-dz', ['product_slug' => 'produit-brouillon'])
            ->assertStatus(404);

        $this->submitQuote('usine-lead-dz', ['product_slug' => 'inexistant'])
            ->assertStatus(404);

        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_unknown_or_flagless_company_returns_404(): void
    {
        $this->submitQuote('usine-inconnue')->assertStatus(404);
        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_quote_against_company_b_product_slug_under_company_a_returns_404(): void
    {
        // Produit publié chez B, demandé sous le slug du tenant A → 404
        // (le produit doit appartenir au tenant du slug).
        $principalB = $this->manager($this->companyB);
        $this->storeAndPublishProduct($principalB, [
            'name' => 'Gélules B',
            'slug' => 'gelules-b',
            'currency' => 'MAD',
            'price_minor' => 500,
        ]);

        $this->submitQuote('usine-lead-dz', ['product_slug' => 'gelules-b'])->assertStatus(404);
        $this->assertDatabaseCount('crm_leads', 0);
    }

    public function test_lead_payload_keeps_consent_trace(): void
    {
        Mail::fake();
        $this->submitQuote('usine-lead-dz')->assertStatus(201);

        $lead = (array) DB::table('crm_leads')
            ->where('company_id', $this->companyA->id)
            ->where('email', 'achat@acheteur.dz')
            ->first();

        $this->assertNotNull($lead);
        $this->assertStringContainsString('Consentement RGPD explicite', (string) ($lead['notes'] ?? ''));
        $this->assertStringContainsString('Machine CNC 3 axes', (string) ($lead['notes'] ?? ''));
        $this->assertSame(['b2b_catalog', 'quote'], json_decode((string) ($lead['tags'] ?? '[]'), true));
    }

    public function test_contact_without_last_name_creates_lead_with_empty_last_name(): void
    {
        Mail::fake();
        $this->submitQuote('usine-lead-dz', ['contact_name' => 'Karim'])->assertStatus(201);

        $this->assertDatabaseHas('crm_leads', [
            'company_id' => (string) $this->companyA->id,
            'first_name' => 'Karim',
            'last_name' => '',
        ]);
    }
}
