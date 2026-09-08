<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\CompanyShowcaseSection;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6866) — API privée des sections : CRUD + réordonnancement,
 * validation par schéma (422), RBAC (création vitrine + édition sections
 * réservées principal/rh), feature flag `company_showcase` (403 fail-closed)
 * et isolation tenant (id étranger → 404).
 */
class ShowcaseSectionApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private function company(string $slug = 'acme-industries'): Company
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD', 'slug' => $slug]);
        $company->setFeature('company_showcase', true);
        $company->save();

        return $company;
    }

    private function principal(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function employee(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        return $employee;
    }

    private function showcase(Company $company, string $status = CompanyShowcaseStatus::Draft): CompanyShowcase
    {
        return app(TenantManager::class)->withinTenant($company, function () use ($company, $status): CompanyShowcase {
            /** @var CompanyShowcase $showcase */
            $showcase = CompanyShowcase::query()->create([
                'company_id' => $company->id,
                'slug' => $company->slug,
                'status' => $status,
                'theme' => 'default',
            ]);

            return $showcase;
        });
    }

    private function heroContent(): array
    {
        return ['heading' => 'Acme Industries'];
    }

    public function test_principal_can_create_showcase_and_manage_sections(): void
    {
        $company = $this->company();
        $this->principal($company);

        // Vitrine absente → sections en 404 tant que POST /showcase n'a pas été fait.
        $this->getJson('/api/v1/showcase/sections')->assertStatus(404);

        // Création 1-clic.
        $this->postJson('/api/v1/showcase')
            ->assertStatus(201)
            ->assertJsonPath('data.slug', $company->slug)
            ->assertJsonPath('data.status', 'draft');

        // Nouvelle section hero.
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => $this->heroContent(),
        ])->assertStatus(201)
            ->assertJsonPath('data.type', 'hero')
            ->assertJsonPath('data.content.heading', 'Acme Industries')
            ->assertJsonPath('data.schema_version', 1);

        // Liste ordonnée.
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'footer',
            'content' => ['text' => '© 2026'],
        ])->assertStatus(201);

        $this->getJson('/api/v1/showcase/sections')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.type', 'hero')
            ->assertJsonPath('data.1.type', 'footer');

        // Mise à jour (PATCH partiel).
        $heroId = $this->json('GET', '/api/v1/showcase/sections')->json('data.0.id');

        $this->patchJson("/api/v1/showcase/sections/{$heroId}", [
            'content' => ['heading' => 'Acme Industries SAS'],
        ])->assertOk()
            ->assertJsonPath('data.content.heading', 'Acme Industries SAS');

        // Réordonnancement (footer en premier).
        $ids = collect($this->json('GET', '/api/v1/showcase/sections')->json('data'))->pluck('id')->reverse()->values()->all();

        $this->postJson('/api/v1/showcase/sections/reorder', ['ids' => $ids])
            ->assertOk()
            ->assertJsonPath('data.0.type', 'footer')
            ->assertJsonPath('data.1.type', 'hero');

        // Suppression.
        $this->deleteJson("/api/v1/showcase/sections/{$heroId}")->assertStatus(204);
        $this->getJson('/api/v1/showcase/sections')->assertJsonCount(1, 'data');
    }

    public function test_invalid_section_content_is_rejected_with_422(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->postJson('/api/v1/showcase')->assertStatus(201);

        // Type inconnu.
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'products', // BC-28 #6891, hors v1
            'content' => [],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('type');

        // Schéma invalide (heading requis).
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['evil' => true],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('content');

        // Schéma invalide (clé inconnue).
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['heading' => 'X', 'script' => '<script>alert(1)</script>'],
        ])->assertStatus(422)
            ->assertJsonValidationErrors('content');
    }

    public function test_sections_require_principal_or_rh_role(): void
    {
        $company = $this->company();
        $this->showcase($company);
        $this->employee($company);

        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => $this->heroContent(),
        ])->assertStatus(403);

        $this->getJson('/api/v1/showcase/sections')->assertStatus(403);
    }

    public function test_feature_flag_disabled_blocks_management_routes(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']); // flag absent
        $this->showcase($company);
        $this->principal($company);

        $this->getJson('/api/v1/showcase/sections')->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }

    public function test_cross_tenant_section_is_never_addressable(): void
    {
        $companyA = $this->company('tenant-a');
        $companyB = $this->company('tenant-b');

        $showcaseA = $this->showcase($companyA);
        $sectionBId = app(TenantManager::class)->withinTenant($companyB, function () use ($companyB): int {
            $showcaseB = CompanyShowcase::query()->create([
                'company_id' => $companyB->id,
                'slug' => $companyB->slug,
                'status' => CompanyShowcaseStatus::Draft,
                'theme' => 'default',
            ]);
            /** @var CompanyShowcaseSection $section */
            $section = CompanyShowcaseSection::query()->create([
                'company_id' => $companyB->id,
                'showcase_id' => $showcaseB->id,
                'type' => 'hero',
                'content' => ['heading' => 'Tenant B'],
                'sort_order' => 10,
            ]);

            return $section->id;
        });

        $this->principal($companyA);

        // L'id d'une section d'un autre tenant est introuvable → 404.
        $this->patchJson("/api/v1/showcase/sections/{$sectionBId}", ['content' => ['heading' => 'X']])->assertStatus(404);
        $this->deleteJson("/api/v1/showcase/sections/{$sectionBId}")->assertStatus(404);
        $this->getJson('/api/v1/showcase/sections')
            ->assertOk()
            ->assertJsonCount(0, 'data');

        $this->assertTrue($showcaseA->company_id !== $companyB->id);
    }

    public function test_reorder_requires_exact_known_set_of_ids(): void
    {
        $company = $this->company();
        $this->principal($company);
        $this->showcase($company);

        $first = $this->postJson('/api/v1/showcase/sections', ['type' => 'hero', 'content' => $this->heroContent()])->json('data');
        $this->postJson('/api/v1/showcase/sections', ['type' => 'footer', 'content' => ['text' => 'x']])->assertStatus(201);

        // Liste partielle → 422.
        $this->postJson('/api/v1/showcase/sections/reorder', ['ids' => [$first['id']]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');

        // Id étranger mélangé → 422.
        $this->postJson('/api/v1/showcase/sections/reorder', ['ids' => [$first['id'], 999_999]])
            ->assertStatus(422)
            ->assertJsonValidationErrors('ids');
    }
}
