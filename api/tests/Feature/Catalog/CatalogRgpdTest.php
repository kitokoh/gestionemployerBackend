<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use App\Modules\CRM\Domain\Models\CrmLead;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6889 C-RGPD) — protection des données acheteur des
 * demandes de devis B2B : droit d'effacement (canal tenant : DELETE →
 * demande + leads CRM BC-11 liés supprimés), purge de la conservation
 * expirée (commande catalog:purge-expired-inquiries), revue de non-fuite
 * sur les routes publiques (le formulaire ne renvoie jamais les données
 * acheteur ; le DTO public ne contient aucun champ interne).
 */
class CatalogRgpdTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Employee $principalA;

    private Employee $employeeA;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyA->setFeature('b2b_catalog', true);
        $companyA->save();
        $this->companyA = $companyA;

        $this->principalA = $this->employee($companyA, 'principal');
        $this->employeeA = $this->employee($companyA, 'employee');

    }

    public function test_erasure_deletes_inquiry_and_linked_crm_leads(): void
    {
        $inquiryId = $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz');

        // Le lead CRM BC-11 a bien été créé à la soumission (contrat #6884).
        // NB : invariant robuste (pas de count exact — un double boot local
        // du provider peut dupliquer les listeners ; en CI, registre unique).
        $this->withinTenantA(function (): void {
            $this->assertGreaterThanOrEqual(1, CrmLead::query()->where('source', 'b2b_catalog')->count());
        });

        // Effacement par un manager du tenant.
        Sanctum::actingAs($this->principalA);
        $this->deleteJson('/api/v1/catalog/inquiries/'.$inquiryId)
            ->assertStatus(200)
            ->assertJsonPath('data', null);

        $this->withinTenantA(function (): void {
            $this->assertSame(0, CatalogInquiry::query()->count(), 'la demande doit être effacée');
            $this->assertSame(0, CrmLead::query()->count(), 'les leads BC-11 du même acheteur doivent être effacés');
        });
    }

    public function test_erasure_is_restricted_to_managers_and_tenant(): void
    {
        $inquiryId = $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz');

        // Employé simple → 403.
        Sanctum::actingAs($this->employeeA);
        $this->deleteJson('/api/v1/catalog/inquiries/'.$inquiryId)->assertStatus(403);

        // Manager d'un autre tenant → 404.
        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('b2b_catalog', true);
        $companyB->save();
        $principalB = $this->employee($companyB, 'principal');

        Sanctum::actingAs($principalB);
        $this->deleteJson('/api/v1/catalog/inquiries/'.$inquiryId)->assertStatus(404);
    }

    public function test_purge_command_removes_only_expired_inquiries(): void
    {
        $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz'); // retention_until = +90 j

        // Une demande à la rétention EXPIRÉE (force l'horodatage en base).
        $this->withinTenantA(function (): void {
            $expired = CatalogInquiry::query()->create([
                'company_id' => $this->companyA->id,
                'product_slug' => 'cnc-3-axes',
                'product_name' => 'Machine CNC 3 axes',
                'company_name' => 'Société Expirée',
                'email' => 'vieux@contact.dz',
                'status' => 'new',
                'consent_at' => Carbon::now()->subDays(100),
                'retention_until' => Carbon::today()->subDay(),
            ]);
        });

        // Dry-run : rien n'est supprimé.
        $this->artisan('catalog:purge-expired-inquiries', ['--company' => $this->companyA->slug, '--dry-run' => true]);

        $this->withinTenantA(function (): void {
            $this->assertSame(2, CatalogInquiry::query()->count());
        });

        // Exécution : seule l'expirée disparaît (l'active reste).
        $this->artisan('catalog:purge-expired-inquiries', ['--company' => $this->companyA->slug]);

        $this->withinTenantA(function (): void {
            $this->assertSame(1, CatalogInquiry::query()->count());
            $this->assertSame(0, CatalogInquiry::query()->where('email', 'vieux@contact.dz')->count());
        });
    }

    public function test_public_surfaces_never_leak_buyer_or_internal_data(): void
    {
        $this->seedInquiry('Ateliers Boussaid SARL', 'achat@boussaid.dz');

        // Le formulaire public ne renvoie que l'accusé (status + reference) —
        // jamais les données acheteur.
        $response = $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => 'Autre SARL',
            'email' => 'autre@autre.dz',
            'message' => 'Un message',
            'consent' => true,
        ])->assertStatus(201);

        $this->assertSame(['status', 'reference'], array_keys($response->json('data')));

        // Le catalogue public ne contient aucun champ interne (id/company_id/
        // status/notes/consentement/rétention) ; `meta` est le bloc SEO
        // dérivé du contenu (#6888).
        $catalogue = $this->getJson('/api/v1/public/catalog/'.$this->companyA->slug)
            ->assertStatus(200)
            ->json('data');
        $this->assertSame(['slug', 'name'], array_keys($catalogue['company']));
        $this->assertSame(
            ['slug', 'name', 'description', 'price_minor', 'currency', 'unit', 'photos', 'category', 'inquiry_path', 'meta'],
            array_keys($catalogue['products'][0])
        );
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

    private function seedInquiry(string $companyName, string $email): int
    {
        // Produit publié (une fois par test).
        Sanctum::actingAs($this->principalA);
        $product = $this->postJson('/api/v1/catalog/products', [
            'name' => 'Machine CNC 3 axes',
            'slug' => 'cnc-3-axes',
            'price_minor' => 1_250_000,
            'currency' => 'DZD',
            'unit' => 'piece',
        ])->assertStatus(201)->json('data');
        $this->postJson('/api/v1/catalog/products/'.$product['id'].'/publish')->assertStatus(200);

        $response = $this->postJson('/api/v1/public/catalog/'.$this->companyA->slug.'/inquiries', [
            'product_slug' => 'cnc-3-axes',
            'company_name' => $companyName,
            'email' => $email,
            'message' => 'Besoin d un devis.',
            'consent' => true,
        ])->assertStatus(201);

        return (int) $response->json('data.reference');
    }

    private function withinTenantA(callable $callback): mixed
    {
        /** @var TenantManager $tenants */
        $tenants = app(TenantManager::class);

        return $tenants->withinTenant($this->companyA, fn (): mixed => $callback());
    }
}
