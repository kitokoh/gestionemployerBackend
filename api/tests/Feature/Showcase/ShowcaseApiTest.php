<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6866/#6870) — création 1-clic de la vitrine (US1) :
 * GET /showcase 404 tant que non créée, POST /showcase → 201 (draft, slug =
 * slug tenant), idempotence (200 à la seconde création), RBAC
 * (principal/rh uniquement) et feature flag fail-closed.
 */
class ShowcaseApiTest extends TestCase
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

    private function rh(Company $company): Employee
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $company->id,
            'role' => 'manager',
            'manager_role' => 'rh',
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

    public function test_show_returns_404_before_creation_then_200_after(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->getJson('/api/v1/showcase')->assertStatus(404);

        $this->postJson('/api/v1/showcase')->assertStatus(201);

        $this->getJson('/api/v1/showcase')
            ->assertOk()
            ->assertJsonPath('data.slug', $company->slug)
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.theme', 'industrie'); // ShowcaseThemeRegistry::DEFAULT_THEME (#6868)
    }

    public function test_creation_is_idempotent_and_slug_matches_company_slug(): void
    {
        $company = $this->company();
        $this->principal($company);

        $this->postJson('/api/v1/showcase')->assertStatus(201);
        $this->postJson('/api/v1/showcase')->assertStatus(200);

        $this->assertSame(1, CompanyShowcase::query()->count());
        $this->assertSame($company->slug, CompanyShowcase::query()->first()?->slug);
    }

    public function test_rh_manager_can_create(): void
    {
        $company = $this->company();
        $this->rh($company);

        $this->postJson('/api/v1/showcase')->assertStatus(201);
    }

    public function test_employee_cannot_create(): void
    {
        $company = $this->company();
        $this->employee($company);

        $this->postJson('/api/v1/showcase')->assertStatus(403);
    }

    public function test_feature_flag_disabled_blocks_creation(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->principal($company);

        $this->postJson('/api/v1/showcase')->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }
}
