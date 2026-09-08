<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\LLMClient;
use App\AI\Models\AIToolRegistryEntry;
use App\AI\PendingActionStore;
use App\AI\Support\AIToolDefinitionRegistry;
use App\AI\Support\AIToolSensitivity;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use stdClass;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B3a (#6856) — outil d'écriture sensible `absence_decision` (BC-06 LEAVE,
 * EPIC #6846, lot B3a) : approuver/refuser une demande d'absence via
 * l'assistant, exécution UNIQUEMENT après confirmation (flux A4) et via les
 * cas d'usage canoniques Planning (ApproveAbsence/RejectAbsence →
 * AbsenceService) — événements métier, RBAC manager, isolation tenant.
 */
class AbsenceDecisionToolTest extends TestCase
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

    public function test_absence_decision_is_declared_as_write_tool_by_bc_06(): void
    {
        // Contrat A3 (#6850) : le BC propriétaire déclare l'outil au boot
        // (PlanningServiceProvider → AbsenceDecisionToolCatalog) avec la
        // sensibilité `write` — jamais exécuté sans confirmation humaine.
        $definition = AIToolDefinitionRegistry::find('absence_decision');

        $this->assertNotNull($definition, "l'outil absence_decision doit être déclaré au boot");
        $this->assertSame(AIToolSensitivity::Write, $definition->sensitivity);
        $this->assertSame('BC-06', $definition->bc);
        $this->assertSame('absences.approve', $definition->permission);

        $schema = $definition->inputSchema;
        $this->assertSame(['absence_id', 'decision'], $schema['required'] ?? []);
        $this->assertSame(['approve', 'reject'], $schema['properties']['decision']['enum'] ?? []);
    }

    public function test_chat_proposes_confirmation_then_confirm_executes_approval(): void
    {
        // Workflow complet (proposition → confirmation → exécution → événement) :
        // le tool_call seul ne produit AUCUN effet de bord.
        [$company, $manager] = $this->aiFixture();
        $absence = $this->pendingAbsence($company);
        $this->registerTool('absence_decision');
        $this->app->forgetInstance(ToolRegistry::class);
        Sanctum::actingAs($manager);
        $this->fakeLlmProposingDecision('approve', $absence->id);
        Event::fake([AbsenceApproved::class]);

        $chat = $this->postJson('/api/v1/ai/chat', ['message' => 'Approuve la demande d absence 1'])
            ->assertOk()
            ->assertJsonPath('data.pending_confirmations.0.status', 'confirmation_required')
            ->assertJsonPath('data.pending_confirmations.0.tool', 'absence_decision');

        $this->assertSame('pending', $absence->fresh()->status, 'aucun effet de bord avant confirmation');

        $pendingId = $chat->json('data.pending_confirmations.0.pending_action_id');
        $this->assertNotEmpty($pendingId);

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.status', 'approved');

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'approved',
            'approved_by' => $manager->id,
        ]);
        Event::assertDispatched(AbsenceApproved::class);
    }

    public function test_confirm_reject_requires_reason_and_dispatches_event(): void
    {
        [$company, $manager] = $this->aiFixture();
        $absence = $this->pendingAbsence($company);
        Sanctum::actingAs($manager);
        Event::fake([AbsenceRejected::class]);

        // Refus sans motif → refus explicite, absence toujours pending.
        $missingReason = app(PendingActionStore::class)->store(
            $company->id,
            $manager->id,
            'absence_decision',
            ['absence_id' => $absence->id, 'decision' => 'reject'],
        );
        $this->postJson("/api/v1/ai/actions/{$missingReason}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error', 'REJECT_REASON_REQUIRED');
        $this->assertSame('pending', $absence->fresh()->status);

        // Refus motivé → exécuté via RejectAbsence, événement conservé.
        $withReason = app(PendingActionStore::class)->store(
            $company->id,
            $manager->id,
            'absence_decision',
            ['absence_id' => $absence->id, 'decision' => 'reject', 'reason' => 'Conflit de planning'],
        );
        $this->postJson("/api/v1/ai/actions/{$withReason}/confirm")
            ->assertOk()
            ->assertJsonPath('data.status', 'executed')
            ->assertJsonPath('data.result.status', 'rejected')
            ->assertJsonPath('data.result.rejected_reason', 'Conflit de planning');

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'rejected',
            'rejected_reason' => 'Conflit de planning',
        ]);
        Event::assertDispatched(AbsenceRejected::class);
    }

    public function test_cross_tenant_absence_is_not_found(): void
    {
        // Isolation tenant : un manager du tenant A ne peut pas décider d'une
        // absence du tenant B (404 côté REST → ABSENCE_NOT_FOUND côté IA).
        [$companyA, $managerA] = $this->aiFixture();

        $companyB = Company::factory()->create();
        $this->assertInstanceOf(Company::class, $companyB);
        $absenceB = $this->pendingAbsence($companyB);

        Sanctum::actingAs($managerA);
        $pendingId = app(PendingActionStore::class)->store(
            $companyA->id,
            $managerA->id,
            'absence_decision',
            ['absence_id' => $absenceB->id, 'decision' => 'approve'],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error', 'ABSENCE_NOT_FOUND');

        $this->assertSame('pending', $absenceB->fresh()->status);
    }

    public function test_non_pending_absence_is_refused(): void
    {
        // Contrat B3a : l'outil ne décide que d'une demande EN ATTENTE — un
        // statut terminal est refusé AVANT exécution, pour l'approbation comme
        // pour le refus (pas de renversement silencieux d'une approbation
        // tierce par un refus d'absence déjà approuvée).
        [$company, $manager] = $this->aiFixture();
        $absence = $this->pendingAbsence($company);
        $absence->update(['status' => 'approved', 'approved_by' => $manager->id]);
        Sanctum::actingAs($manager);

        foreach (['approve', 'reject'] as $decision) {
            $pendingId = app(PendingActionStore::class)->store(
                $company->id,
                $manager->id,
                'absence_decision',
                ['absence_id' => $absence->id, 'decision' => $decision, 'reason' => 'Motif test'],
            );

            $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
                ->assertStatus(422)
                ->assertJsonPath('error', 'ABSENCE_NOT_PENDING');
        }

        $this->assertSame('approved', $absence->fresh()->status, 'aucune mutation sur un statut terminal');
    }

    public function test_reject_reason_too_long_is_refused(): void
    {
        // Parité RejectAbsenceRequest (max:1000) : un motif hors limite est
        // refusé côté serveur (le schéma JSON n'est qu'une recommandation LLM).
        [$company, $manager] = $this->aiFixture();
        $absence = $this->pendingAbsence($company);
        Sanctum::actingAs($manager);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $manager->id,
            'absence_decision',
            ['absence_id' => $absence->id, 'decision' => 'reject', 'reason' => str_repeat('a', 1001)],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error', 'REJECT_REASON_TOO_LONG');

        $this->assertSame('pending', $absence->fresh()->status);
    }

    public function test_approve_without_sufficient_balance_is_refused(): void
    {
        // Revalidation du solde (#2666) via le service canonique : sans
        // snapshot de solde suffisant, l'approbation est refusée proprement.
        [$company, $manager] = $this->aiFixture();
        $absence = $this->pendingAbsenceWithoutBalance($company);
        Sanctum::actingAs($manager);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $manager->id,
            'absence_decision',
            ['absence_id' => $absence->id, 'decision' => 'approve'],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error', 'INSUFFICIENT_LEAVE_BALANCE');

        $this->assertSame('pending', $absence->fresh()->status);
    }

    public function test_human_reject_endpoint_cancels_pending_action_without_mutation(): void
    {
        // Flux A4 : l'utilisateur REFUSE l'exécution → la pending action est
        // consommée, l'absence reste pending (aucun effet de bord).
        [$company, $manager] = $this->aiFixture();
        $absence = $this->pendingAbsence($company);
        Sanctum::actingAs($manager);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $manager->id,
            'absence_decision',
            ['absence_id' => $absence->id, 'decision' => 'approve'],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/reject")
            ->assertOk()
            ->assertJsonPath('data.status', 'rejected')
            ->assertJsonPath('data.tool', 'absence_decision');

        $this->assertSame('pending', $absence->fresh()->status);

        // Action consommée : une seconde confirmation échoue (404).
        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")->assertNotFound();
    }

    public function test_invalid_decision_is_refused(): void
    {
        [$company, $manager] = $this->aiFixture();
        $absence = $this->pendingAbsence($company);
        Sanctum::actingAs($manager);

        $pendingId = app(PendingActionStore::class)->store(
            $company->id,
            $manager->id,
            'absence_decision',
            ['absence_id' => $absence->id, 'decision' => 'maybe'],
        );

        $this->postJson("/api/v1/ai/actions/{$pendingId}/confirm")
            ->assertStatus(422)
            ->assertJsonPath('error', 'INVALID_DECISION');

        $this->assertSame('pending', $absence->fresh()->status);
    }

    /**
     * @return array{0: Company, 1: Employee}
     */
    private function aiFixture(): array
    {
        $company = Company::factory()->create();
        $this->assertInstanceOf(Company::class, $company);
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $this->assertInstanceOf(Employee::class, $manager);

        return [$company, $manager];
    }

    private function pendingAbsence(Company $company): Absence
    {
        $absence = $this->pendingAbsenceWithoutBalance($company);

        // Snapshot de solde suffisant pour la revalidation d'approbation (#2666).
        LeaveBalance::create([
            'company_id' => $company->id,
            'employee_id' => $absence->employee_id,
            'absence_type_id' => $absence->absence_type_id,
            'year' => 2026,
            'balance' => 20,
            'used' => 0,
            'pending' => 0,
        ]);

        return $absence;
    }

    private function pendingAbsenceWithoutBalance(Company $company): Absence
    {
        $type = AbsenceType::create([
            'company_id' => $company->id,
            'name' => 'Conges payes',
            'code' => 'CP',
            'is_paid' => true,
            'deducts_leave' => true,
            'requires_proof' => false,
        ]);
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
        $this->assertInstanceOf(Absence::class, $absence);

        return $absence;
    }

    private function registerTool(string $name): void
    {
        AIToolRegistryEntry::create([
            'name' => $name,
            'description' => "Write tool {$name}",
            'parameters' => ['type' => 'object', 'properties' => new stdClass],
            'required_permissions' => ['absences.approve'],
            'required_role' => 'manager',
            'module' => 'planning',
            'active' => true,
        ]);
    }

    private function fakeLlmProposingDecision(string $decision, int $absenceId): void
    {
        $this->app->instance(LLMClient::class, new class($decision, $absenceId) implements LLMClient
        {
            public function __construct(
                private readonly string $decision,
                private readonly int $absenceId,
            ) {}

            public function chat(array $messages, array $tools = []): AIResponse
            {
                return new AIResponse(
                    content: 'Je prepare la decision.',
                    toolCalls: [
                        new ToolCall('call_1', 'absence_decision', [
                            'absence_id' => $this->absenceId,
                            'decision' => $this->decision,
                            'reason' => 'Validation RH',
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

}
