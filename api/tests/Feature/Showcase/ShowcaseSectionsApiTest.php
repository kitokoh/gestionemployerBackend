<?php

declare(strict_types=1);

namespace Tests\Feature\Showcase;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Showcase\Domain\Enums\CompanyShowcaseStatus;
use App\Modules\Showcase\Domain\Models\CompanyShowcase;
use App\Modules\Showcase\Domain\Models\ShowcaseSection;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * BC-27 SHOWCASE (#6866) — V-SECTIONS-API : CRUD des sections typées
 * (JSON Schema versionné), RBAC deny-by-default (gestion principal/rh),
 * isolation tenant (404 cross-tenant), gate feature flag company_showcase
 * et réordonnancement bulk.
 */
class ShowcaseSectionsApiTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $companyA;

    private Company $companyB;

    private Company $companyNoFlag;

    private Employee $principalA;

    private Employee $employeeA;

    private Employee $principalB;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $companyA */
        $companyA = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $companyA->setFeature('company_showcase', true);
        $companyA->save();
        $this->companyA = $companyA;

        /** @var Company $companyB */
        $companyB = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $companyB->setFeature('company_showcase', true);
        $companyB->save();
        $this->companyB = $companyB;

        /** @var Company $companyNoFlag */
        $companyNoFlag = Company::factory()->create(['country' => 'SN', 'currency' => 'XOF']);
        $this->companyNoFlag = $companyNoFlag;

        $this->principalA = $this->employee($this->companyA, 'principal');
        $this->employeeA = $this->employee($this->companyA, 'employee');
        $this->principalB = $this->employee($this->companyB, 'principal');
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

    private function actAs(Employee $employee): void
    {
        Sanctum::actingAs($employee);
    }

    /**
     * @param  array<string, mixed>  $content
     * @return array<string, mixed>
     */
    private function heroPayload(array $content = [], ?string $type = 'hero'): array
    {
        return [
            'type' => $type,
            'content' => $content === [] ? ['title' => 'Nous embauchons des gens formidables'] : $content,
        ];
    }

    private function storeSection(Employee $actor, array $payload): int
    {
        $this->actAs($actor);

        return (int) $this->postJson('/api/v1/showcase/sections', $payload)
            ->assertStatus(201)
            ->json('data.id');
    }

    public function test_index_returns_empty_list_without_sections(): void
    {
        $this->actAs($this->principalA);

        $this->getJson('/api/v1/showcase/sections')
            ->assertOk()
            ->assertJson(['data' => []]);
    }

    public function test_store_rejects_employee_role(): void
    {
        $this->actAs($this->employeeA);

        $this->postJson('/api/v1/showcase/sections', $this->heroPayload())
            ->assertForbidden();
    }

    public function test_store_rejects_tenant_without_feature_flag(): void
    {
        /** @var Employee $managerNoFlag */
        $managerNoFlag = $this->employee($this->companyNoFlag, 'principal');
        $this->actAs($managerNoFlag);

        $this->postJson('/api/v1/showcase/sections', $this->heroPayload())
            ->assertStatus(403)
            ->assertJsonPath('error', 'FEATURE_NOT_ENABLED');
    }

    public function test_store_creates_section_and_default_draft_showcase(): void
    {
        $this->actAs($this->principalA);

        $response = $this->postJson('/api/v1/showcase/sections', $this->heroPayload())
            ->assertStatus(201);

        $response->assertJsonPath('data.type', 'hero')
            ->assertJsonPath('data.schema_version', 1)
            ->assertJsonPath('data.position', 0);

        // La vitrine du tenant est créée en draft (création 1-clic) si absente.
        $this->assertDatabaseHas('company_showcases', [
            'company_id' => $this->companyA->id,
            'status' => CompanyShowcaseStatus::Draft->value,
        ]);
        $showcase = CompanyShowcase::query()->where('company_id', $this->companyA->id)->firstOrFail();
        $this->assertSame(1, ShowcaseSection::query()->where('showcase_id', $showcase->id)->count());
    }

    public function test_store_rejects_content_violating_schema(): void
    {
        $this->actAs($this->principalA);

        // Clé inconnue au niveau racine → 422 (additionalProperties: false).
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['title' => 'OK', 'bogus_key' => true],
        ])->assertStatus(422)->assertJsonValidationErrors('content');

        // Type inconnu → 422.
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'mosaic',
            'content' => ['title' => 'OK'],
        ])->assertStatus(422);

        // Champ requis manquant (features.items) → 422.
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'features',
            'content' => ['title' => 'Sans items'],
        ])->assertStatus(422)->assertJsonValidationErrors('content');

        // URL invalide (format uri) → 422.
        $this->postJson('/api/v1/showcase/sections', [
            'type' => 'hero',
            'content' => ['title' => 'OK', 'cta_href' => 'pas une url'],
        ])->assertStatus(422)->assertJsonValidationErrors('content');
    }

    public function test_sections_are_ordered_and_readable_by_tenant_member(): void
    {
        $first = $this->storeSection($this->principalA, $this->heroPayload(['title' => 'Section 1']));
        $second = $this->storeSection($this->principalA, [
            'type' => 'features',
            'content' => [
                'items' => [
                    ['title' => 'Pointage mobile', 'description' => 'GPS + biométrie'],
                    ['title' => 'Paie multi-pays', 'description' => 'CNAS/CNSS/DSN'],
                ],
            ],
        ]);

        // Lecture par un membre non-manager du tenant → 200.
        $this->actAs($this->employeeA);
        $this->getJson('/api/v1/showcase/sections')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.id', $first)
            ->assertJsonPath('data.1.id', $second);

        // show() d'une section du tenant → 200 (membre).
        $this->getJson("/api/v1/showcase/sections/{$second}")
            ->assertOk()
            ->assertJsonPath('data.type', 'features');
    }

    public function test_update_changes_content_and_position(): void
    {
        $id = $this->storeSection($this->principalA, $this->heroPayload(['title' => 'Avant']));
        $this->actAs($this->principalA);

        $this->putJson("/api/v1/showcase/sections/{$id}", [
            'content' => ['title' => 'Après', 'subtitle' => 'Mis à jour'],
            'position' => 3,
        ])->assertOk()
            ->assertJsonPath('data.content.title', 'Après')
            ->assertJsonPath('data.position', 3);

        // Contenu invalide → 422 (schéma du type conservé).
        $this->putJson("/api/v1/showcase/sections/{$id}", [
            'content' => ['title' => 'OK', 'nope' => 1],
        ])->assertStatus(422)->assertJsonValidationErrors('content');
    }

    public function test_destroy_deletes_and_recompacts_positions(): void
    {
        $this->storeSection($this->principalA, $this->heroPayload(['title' => 'A']));
        $middle = $this->storeSection($this->principalA, $this->heroPayload(['title' => 'B']));
        $this->storeSection($this->principalA, $this->heroPayload(['title' => 'C']));

        $this->actAs($this->principalA);
        $this->deleteJson("/api/v1/showcase/sections/{$middle}")->assertStatus(204);

        $this->getJson('/api/v1/showcase/sections')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.position', 0)
            ->assertJsonPath('data.1.position', 1);
    }

    public function test_reorder_bulk_updates_positions(): void
    {
        $a = $this->storeSection($this->principalA, $this->heroPayload(['title' => 'A']));
        $b = $this->storeSection($this->principalA, $this->heroPayload(['title' => 'B']));
        $c = $this->storeSection($this->principalA, $this->heroPayload(['title' => 'C']));

        $this->actAs($this->principalA);
        $this->putJson('/api/v1/showcase/sections/order', [
            'sections' => [
                ['id' => $c, 'position' => 0],
                ['id' => $a, 'position' => 1],
                ['id' => $b, 'position' => 2],
            ],
        ])->assertOk()
            ->assertJsonPath('data.0.id', $c)
            ->assertJsonPath('data.1.id', $a)
            ->assertJsonPath('data.2.id', $b);

        // Payload invalide (doublon de position hors 0..n-1) → 422.
        $this->putJson('/api/v1/showcase/sections/order', [
            'sections' => [
                ['id' => $a, 'position' => 0],
                ['id' => $b, 'position' => 0],
            ],
        ])->assertStatus(422);
    }

    public function test_cross_tenant_isolation_returns_404(): void
    {
        $id = $this->storeSection($this->principalA, $this->heroPayload());

        // Le principal du tenant B ne voit ni ne modifie les sections du tenant A.
        $this->actAs($this->principalB);
        $this->getJson('/api/v1/showcase/sections')->assertOk()->assertJson(['data' => []]);
        $this->getJson("/api/v1/showcase/sections/{$id}")->assertNotFound();
        $this->putJson("/api/v1/showcase/sections/{$id}", ['content' => ['title' => 'Piratage']])->assertNotFound();
        $this->deleteJson("/api/v1/showcase/sections/{$id}")->assertNotFound();

        // Réordonnancement cross-tenant → 404 (liste partielle introuvable).
        $this->putJson('/api/v1/showcase/sections/order', [
            'sections' => [['id' => $id, 'position' => 0]],
        ])->assertNotFound();

        $this->actAs($this->principalA);
        $this->getJson("/api/v1/showcase/sections/{$id}")->assertOk();
    }
}
