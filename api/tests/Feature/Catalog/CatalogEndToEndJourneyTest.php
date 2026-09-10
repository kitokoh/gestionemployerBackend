<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-28 CATALOG (#6890 C-E2E) — parcours E2E complet du catalogue B2B,
 * du produit publié à la demande de devis traitée en back-office :
 *
 *  - volet PUBLIC (sans auth) : seuls les produits publiés sortent, la
 *    fiche est consultable, la demande de devis exige le consentement ;
 *  - volet BACK-OFFICE (gestionnaire) : la demande apparaît dans la liste,
 *    change de statut, s'exporte et s'efface (RGPD).
 *
 * Les deux volets sont exécutés dans des tests distincts (états d'auth
 * séparés) mais partagent les mêmes fixtures — contrat identique.
 */
class CatalogEndToEndJourneyTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Employee $principal;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create([
            'slug' => 'e2e-catalog',
            'country' => 'DZ',
            'currency' => 'DZD',
        ]);
        $company->setFeature('b2b_catalog', true);
        $company->save();
        $this->company = $company;

        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->principal = $employee;
    }

    private function publishedProduct(string $slug = 'machine-cnc'): CatalogProduct
    {
        return app(TenantManager::class)->withinTenant($this->company, function () use ($slug): CatalogProduct {
            /** @var CatalogProduct $product */
            $product = CatalogProduct::query()->create([
                'company_id' => $this->company->id,
                'name' => 'Machine CNC 3 axes',
                'slug' => $slug,
                'description' => 'Fraiseuse industrielle',
                'price_minor' => 1250000,
                'currency' => 'DZD',
                'unit' => 'piece',
                'status' => CatalogProductStatus::Published,
            ]);

            return $product;
        });
    }

    /**
     * Volet public : brouillon invisible → publication → liste/fiche
     * publiques → demande de devis (consentement obligatoire).
     */
    public function test_public_journey_from_publication_to_inquiry(): void
    {
        $product = $this->publishedProduct();

        // Fiche publique accessible sans auth après publication.
        $this->getJson('/api/v1/public/catalog/e2e-catalog/products/machine-cnc')
            ->assertOk()
            ->assertJsonPath('data.slug', 'machine-cnc')
            ->assertJsonPath('data.price_minor', 1250000);

        $this->getJson('/api/v1/public/catalog/e2e-catalog')
            ->assertOk()
            ->assertJsonPath('data.products.0.slug', 'machine-cnc');

        // Demande de devis : consentement requis.
        $this->postJson('/api/v1/public/catalog/e2e-catalog/inquiries', [
            'company_name' => 'Acheteur SARL',
            'email' => 'achat@acheteur.test',
            'product_slug' => $product->slug,
            'message' => 'Devis pour 2 machines',
            'consent' => true,
        ])->assertStatus(201);

        $this->postJson('/api/v1/public/catalog/e2e-catalog/inquiries', [
            'company_name' => 'Acheteur SARL',
            'email' => 'achat2@acheteur.test',
            'product_slug' => $product->slug,
        ])->assertStatus(422);

        // Brouillon jamais exposé.
        app(TenantManager::class)->withinTenant($this->company, function (): void {
            CatalogProduct::query()->create([
                'company_id' => $this->company->id,
                'name' => 'Secret',
                'slug' => 'secret',
                'price_minor' => 1,
                'currency' => 'DZD',
                'unit' => 'piece',
                'status' => CatalogProductStatus::Draft,
            ]);
        });

        $this->getJson('/api/v1/public/catalog/e2e-catalog/products/secret')->assertStatus(404);
    }

    /**
     * Volet back-office : liste, changement de statut, export CSV, effacement RGPD.
     */
    public function test_backoffice_journey_manages_inquiries(): void
    {
        $product = $this->publishedProduct();

        $this->postJson('/api/v1/public/catalog/e2e-catalog/inquiries', [
            'company_name' => 'Acheteur SARL',
            'email' => 'achat@acheteur.test',
            'product_slug' => $product->slug,
            'message' => 'Devis pour 2 machines',
            'consent' => true,
        ])->assertStatus(201);

        Sanctum::actingAs($this->principal);

        $list = $this->getJson('/api/v1/catalog/inquiries')->assertOk();
        $inquiryId = $list->json('data.0.id');
        $this->assertIsInt($inquiryId);

        $this->patchJson("/api/v1/catalog/inquiries/{$inquiryId}/status", ['status' => 'contacted'])
            ->assertOk();

        $this->get('/api/v1/catalog/inquiries/export')->assertOk();

        // RGPD : effacement de la demande → la liste se vide.
        $this->deleteJson("/api/v1/catalog/inquiries/{$inquiryId}")->assertOk();

        $this->assertSame(0, (int) $this->getJson('/api/v1/catalog/inquiries')->json('meta.total'));
    }
}
