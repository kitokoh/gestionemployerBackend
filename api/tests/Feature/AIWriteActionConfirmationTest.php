<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\IntentEngine;
use App\AI\LLMClient;
use App\AI\Models\AIToolRegistryEntry;
use App\AI\PendingActionStore;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\AbsenceApproved;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use stdClass;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AIWriteActionConfirmationTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_write_tool_returns_confirmation_required_without_creating_absence(): void
    {
        [$company, $employee] = $this->aiFixture();
        $this->seedAbsenceType($company->id);
        $this->registerWriteTool('create_absence');
        $this->app->forgetInstance(ToolRegistry::class);

        $engine = app(IntentEngine::class);
        $response = new AIResponse(
            content: '',
            toolCalls: [
                new ToolCall('call_1', 'create_absence', [
                    'start_date' => '2026-06-10',
                    'end_date' => '2026-06-12',
                    'reason' => 'Conges',
                ]),
            ],
        );

        $results = $engine->executeToolCalls($response, $company->id, $employee->id);
        $payload = json_decode($results[0]->content, true);

        $this->assertTrue($results[0]->success);
        $this->assertSame('confirmation_required', $payload['status'] ?? null);
        $this->assertNotEmpty($payload['pending_action_id'] ?? null);
        $this->assertDatabaseCount('absences', 0);
    }

    public function test_confirm_action_executes_create_absence(): void
    {
        [$company, $employee] = $this->aiFixture();
        $this->seedAbsenceType($company->id);
        Sanctum::actingAs($employee);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $employee->id,
            'create_absence',
            [
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-11',
                'reason' => 'Conges',
            ],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.tool', 'create_absence')
            ->assertJsonPath('data.result.status', 'pending');

        $this->assertDatabaseHas('absences', [
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'status' => 'pending',
        ]);
    }

    public function test_reject_action_does_not_create_absence(): void
    {
        [$company, $employee] = $this->aiFixture();
        Sanctum::actingAs($employee);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $employee->id,
            'create_absence',
            [
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-11',
            ],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.tool', 'create_absence');

        $this->assertDatabaseCount('absences', 0);
    }

    public function test_confirm_action_is_scoped_to_authenticated_user(): void
    {
        [$company, $employee] = $this->aiFixture();
        $other = Employee::factory()->create(['company_id' => $company->id]);
        $this->assertInstanceOf(Employee::class, $other);
        Sanctum::actingAs($other);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $employee->id,
            'create_absence',
            ['start_date' => '2026-06-10', 'end_date' => '2026-06-11'],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")->assertNotFound();
        $this->assertDatabaseCount('absences', 0);
    }

    public function test_confirm_absence_decision_approve_updates_status(): void
    {
        // B3a (#6856) : la confirmation exécute la décision via le service
        // canonique Planning (AbsenceService::approve) — statut, approved_by,
        // événement métier et revalidation du solde (#2666) préservés.
        [$company, $manager] = $this->aiFixture();
        $type = $this->seedAbsenceType($company->id);
        $employee = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->assertInstanceOf(Employee::class, $employee);

        $absence = Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
            'days_count' => 2,
            'status' => 'pending',
        ]);

        // Snapshot de solde suffisant (approbation d'un type déductible).
        LeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'absence_type_id' => $type->id,
            'year' => 2026,
            'balance' => 20,
            'used' => 0,
            'pending' => 0,
        ]);

        Sanctum::actingAs($manager);
        Event::fake([AbsenceApproved::class]);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $manager->id,
            'absence_decision',
            ['absence_id' => $absence->id, 'decision' => 'approve'],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.tool', 'absence_decision')
            ->assertJsonPath('data.result.status', 'approved');

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'approved',
            'approved_by' => $manager->id,
        ]);
        Event::assertDispatched(AbsenceApproved::class);
    }

    public function test_orchestrator_returns_pending_confirmations_for_write_tools(): void
    {
        [$company, $employee] = $this->aiFixture();
        $this->registerWriteTool('create_absence');
        $this->app->forgetInstance(ToolRegistry::class);
        Sanctum::actingAs($employee);
        $this->fakeLlmClientWithWriteToolCall();

        $this->postJson('/api/v1/ai/chat', ['message' => 'Demande un conge'])
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.0.status', 'confirmation_required')
            ->assertJsonPath('data.pending_confirmations.0.tool', 'create_absence');

        $this->assertDatabaseCount('absences', 0);
    }

    private function fakeLlmClientWithWriteToolCall(): void
    {
        $this->app->instance(LLMClient::class, new class implements LLMClient
        {
            public function chat(array $messages, array $tools = []): AIResponse
            {
                return new AIResponse(
                    content: 'Je prepare la demande.',
                    toolCalls: [
                        new ToolCall('call_1', 'create_absence', [
                            'start_date' => '2026-06-10',
                            'end_date' => '2026-06-12',
                        ]),
                    ],
                    inputTokens: 5,
                    outputTokens: 8,
                    model: 'test-model',
                );
            }

            public function provider(): string
            {
                return 'test';
            }
        });
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    /** @return array{Company, Employee} */
    private function aiFixture(): array
    {
        $company = Company::factory()->create();
        $this->assertInstanceOf(Company::class, $company);
        $employee = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->assertInstanceOf(Employee::class, $employee);

        return [$company, $employee];
    }

    private function seedAbsenceType(string $companyId): AbsenceType
    {
        return AbsenceType::create([
            'company_id' => $companyId,
            'name' => 'Conges payes',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);
    }

    private function registerWriteTool(string $name): void
    {
        AIToolRegistryEntry::create([
            'name' => $name,
            'description' => "Write tool {$name}",
            'parameters' => ['type' => 'object', 'properties' => new stdClass],
            'required_permissions' => [],
            'required_role' => 'manager',
            'module' => 'rh',
            'active' => true,
        ]);
    }

    public function test_employee_cannot_decide_absence_via_ai(): void
    {
        // audit(securite) #6533 : décision d'absence via IA réservée aux
        // managers (AbsencePolicy::approve) — un employé qui tente de décider
        // reçoit un refus explicite, l'absence reste pending (matrice de
        // permissions re-vérifiée à la confirmation, flux A4).
        [$company] = $this->aiFixture();
        $type = $this->seedAbsenceType($company->id);
        $employeeActor = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->assertInstanceOf(Employee::class, $employeeActor);

        $absence = Absence::create([
            'company_id' => $company->id,
            'employee_id' => $employeeActor->id,
            'absence_type_id' => $type->id,
            'start_date' => '2026-06-01',
            'end_date' => '2026-06-02',
            'days_count' => 2,
            'status' => 'pending',
        ]);

        Sanctum::actingAs($employeeActor);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $employeeActor->id,
            'absence_decision',
            ['absence_id' => $absence->id, 'decision' => 'approve'],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error', 'AI_TOOL_PERMISSION_DENIED');

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'pending',
        ]);
    }

    public function test_employee_create_absence_for_other_is_forced_to_self(): void
    {
        // audit(securite) #6533 : un employé (non-manager) ne peut pas créer
        // une absence pour un collègue via l'IA — l'employee_id fourni est
        // ignoré, l'absence est créée pour le demandeur.
        [$company] = $this->aiFixture();
        $this->seedAbsenceType($company->id);
        $employeeActor = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->assertInstanceOf(Employee::class, $employeeActor);
        $colleague = Employee::factory()->create(['company_id' => $company->id, 'status' => 'active']);
        $this->assertInstanceOf(Employee::class, $colleague);

        Sanctum::actingAs($employeeActor);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $employeeActor->id,
            'create_absence',
            [
                'employee_id' => $colleague->id,
                'start_date' => '2026-06-10',
                'end_date' => '2026-06-12',
                'reason' => 'Conges',
            ],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.result.employee_id', $employeeActor->id);

        $this->assertDatabaseHas('absences', [
            'company_id' => $company->id,
            'employee_id' => $employeeActor->id,
        ]);
        $this->assertDatabaseMissing('absences', [
            'company_id' => $company->id,
            'employee_id' => $colleague->id,
        ]);
    }
}
