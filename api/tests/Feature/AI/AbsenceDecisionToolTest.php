<?php

declare(strict_types=1);

namespace Tests\Feature\AI;

use App\AI\DTOs\AIResponse;
use App\AI\DTOs\ToolCall;
use App\AI\DTOs\ToolResult;
use App\AI\IntentEngine;
use App\AI\ToolRegistry;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\AbsenceApproved;
use App\Events\AbsenceRejected;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use App\Modules\Planning\Domain\Models\LeaveBalance;
use App\Modules\Planning\Domain\Models\LeaveBalanceLog;
use Database\Seeders\AIToolRegistrySeeder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Schema;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B3a (#6856) — outil écriture `absence_decision` (contrat A3 #6850, flux A4) :
 * décision (approbation / refus motivé) sur une demande d'absence, exécutée
 * UNIQUEMENT après confirmation, via les Actions canoniques Planning
 * (ApproveAbsence/RejectAbsence → AbsenceService, PA2-ARCH-002) — parité REST
 * AbsenceController : RBAC manager (`absences.approve`), isolation tenant
 * fail-closed, motif de refus obligatoire, événements métier conservés.
 */
class AbsenceDecisionToolTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
        config(['ai.enabled' => true]);
        $this->seed(AIToolRegistrySeeder::class);
        $this->app->forgetInstance(ToolRegistry::class);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    /**
     * Étape 1 du flux A4 : le LLM propose l'outil → réponse de confirmation
     * (aucun effet de bord à ce stade).
     *
     * @param  array<string, mixed>  $arguments
     */
    private function proposeDecision(string $companyId, int $userId, array $arguments): ToolResult
    {
        $engine = app(IntentEngine::class);

        return $engine->executeToolCalls(
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', 'absence_decision', $arguments)]),
            $companyId,
            $userId,
        )[0];
    }

    /**
     * Étape 2 du flux A4 : l'utilisateur confirme → exécution effective.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function confirmDecision(string $companyId, int $userId, array $arguments): array
    {
        return app(IntentEngine::class)
            ->executeConfirmedWrite('absence_decision', $arguments, $companyId, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ToolResult $result): array
    {
        $decoded = json_decode($result->content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function statusOf(Absence $absence): string
    {
        return (string) $absence->refresh()->status;
    }

    /**
     * @return array{company: Company, manager: Employee, employee: Employee, type: AbsenceType}
     */
    private function tenant(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        /** @var AbsenceType $type */
        $type = AbsenceType::factory()->nonDeductible()->create(['company_id' => $company->id]);

        return compact('company', 'manager', 'employee', 'type');
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function pendingAbsence(Company $company, Employee $employee, AbsenceType $type, array $overrides = []): Absence
    {
        /** @var Absence $absence */
        $absence = Absence::factory()->withType($type)->create(array_merge([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'start_date' => '2026-04-10',
            'end_date' => '2026-04-12',
            'days_count' => 3,
            'status' => 'pending',
            'reason' => 'Congé familial',
        ], $overrides));

        return $absence;
    }

    public function test_tool_is_registered_as_write_tool_and_proposal_requires_confirmation_without_side_effect(): void
    {
        ['company' => $company, 'manager' => $manager, 'employee' => $employee, 'type' => $type] = $this->tenant();
        $absence = $this->pendingAbsence($company, $employee, $type);

        // Outil déclaré dans la config write (confirmation obligatoire) et
        // présent dans le registre exposé.
        $this->assertContains('absence_decision', config('ai.write_tools', []));
        $registryTool = app(ToolRegistry::class)->findTool('absence_decision');
        $this->assertNotNull($registryTool);
        $this->assertSame('manager', $registryTool['required_role']);

        // Proposition : confirmation requise, aucune exécution.
        $result = $this->proposeDecision((string) $company->id, $manager->id, [
            'absence_id' => $absence->id,
            'decision' => 'approve',
        ]);

        $this->assertTrue($result->success);
        $payload = $this->payload($result);
        $this->assertSame('confirmation_required', $payload['status']);
        $this->assertArrayHasKey('pending_action_id', $payload);
        $this->assertSame('absence_decision', $payload['tool']);

        // Aucun effet de bord avant confirmation : la demande est toujours
        // en attente, aucune ligne d'audit de décision écrite.
        $this->assertSame('pending', $this->statusOf($absence));
        $this->assertDatabaseMissing('audit_logs', [
            'action' => 'planning.absence.approve',
            'auditable_id' => $absence->id,
        ]);
    }

    public function test_manager_approves_pending_absence_after_confirmation_with_business_event(): void
    {
        Event::fake([AbsenceApproved::class]);

        ['company' => $company, 'manager' => $manager, 'employee' => $employee, 'type' => $type] = $this->tenant();
        $absence = $this->pendingAbsence($company, $employee, $type);

        $proposal = $this->proposeDecision((string) $company->id, $manager->id, [
            'absence_id' => $absence->id,
            'decision' => 'approve',
        ]);
        $this->assertTrue($proposal->success);
        $this->assertSame('pending', $this->statusOf($absence));

        // Confirmation → exécution via l'Action canonique ApproveAbsence.
        $executed = $this->confirmDecision((string) $company->id, $manager->id, [
            'absence_id' => $absence->id,
            'decision' => 'approve',
        ]);

        $this->assertSame($absence->id, $executed['absence_id']);
        $this->assertSame('approved', $executed['status']);
        $this->assertSame($manager->id, $executed['approved_by']);

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'approved',
            'approved_by' => $manager->id,
        ]);

        // Événement métier conservé (même chemin que le REST approve).
        Event::assertDispatched(AbsenceApproved::class);
    }

    public function test_reject_requires_a_reason(): void
    {
        ['company' => $company, 'manager' => $manager, 'employee' => $employee, 'type' => $type] = $this->tenant();
        $absence = $this->pendingAbsence($company, $employee, $type);

        $executed = $this->confirmDecision((string) $company->id, $manager->id, [
            'absence_id' => $absence->id,
            'decision' => 'reject',
        ]);

        $this->assertSame('ABSENCE_REJECT_REASON_REQUIRED', $executed['error']);
        $this->assertSame('pending', $this->statusOf($absence));
    }

    public function test_manager_rejects_pending_absence_with_reason_after_confirmation(): void
    {
        Event::fake([AbsenceRejected::class]);

        ['company' => $company, 'manager' => $manager, 'employee' => $employee, 'type' => $type] = $this->tenant();
        $absence = $this->pendingAbsence($company, $employee, $type);

        $executed = $this->confirmDecision((string) $company->id, $manager->id, [
            'absence_id' => $absence->id,
            'decision' => 'reject',
            'reason' => 'Période déjà couverte par un autre arrêt',
        ]);

        $this->assertSame($absence->id, $executed['absence_id']);
        $this->assertSame('rejected', $executed['status']);
        $this->assertSame('Période déjà couverte par un autre arrêt', $executed['rejected_reason']);

        $this->assertDatabaseHas('absences', [
            'id' => $absence->id,
            'status' => 'rejected',
            'rejected_reason' => 'Période déjà couverte par un autre arrêt',
        ]);

        Event::assertDispatched(AbsenceRejected::class);
    }

    public function test_employee_cannot_use_absence_decision(): void
    {
        ['company' => $company, 'employee' => $employee] = $this->tenant();

        // Un employé ne peut même pas proposer l'action (matrice
        // ai.tool_permissions : rôle manager requis) — aucune pending action.
        $result = $this->proposeDecision((string) $company->id, $employee->id, [
            'absence_id' => 1,
            'decision' => 'approve',
        ]);

        $this->assertFalse($result->success);
        $payload = $this->payload($result);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $payload['error']);

        // Défense en profondeur : la confirmation re-vérifie le rôle.
        $denied = $this->confirmDecision((string) $company->id, $employee->id, [
            'absence_id' => 1,
            'decision' => 'approve',
        ]);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $denied['error']);
    }

    public function test_cross_tenant_absence_is_not_found(): void
    {
        ['company' => $companyA, 'manager' => $managerA] = $this->tenant();
        ['company' => $companyB, 'employee' => $employeeB, 'type' => $typeB] = $this->tenant();
        $absenceB = $this->pendingAbsence($companyB, $employeeB, $typeB);

        // Le manager A ne voit pas une absence du tenant B (isolation
        // fail-closed, même comportement que le REST : 404).
        $executed = $this->confirmDecision((string) $companyA->id, $managerA->id, [
            'absence_id' => $absenceB->id,
            'decision' => 'approve',
        ]);

        $this->assertSame('Absence not found', $executed['error']);
        $this->assertSame('pending', $this->statusOf($absenceB));
    }

    public function test_invalid_decision_is_rejected(): void
    {
        ['company' => $company, 'manager' => $manager, 'employee' => $employee, 'type' => $type] = $this->tenant();
        $absence = $this->pendingAbsence($company, $employee, $type);

        $executed = $this->confirmDecision((string) $company->id, $manager->id, [
            'absence_id' => $absence->id,
            'decision' => 'maybe',
        ]);

        $this->assertSame('INVALID_DECISION', $executed['error']);
        $this->assertSame('pending', $this->statusOf($absence));
    }

    public function test_approving_an_already_decided_absence_returns_absense_not_pending(): void
    {
        ['company' => $company, 'manager' => $manager, 'employee' => $employee, 'type' => $type] = $this->tenant();
        $absence = $this->pendingAbsence($company, $employee, $type, ['status' => 'approved']);

        $executed = $this->confirmDecision((string) $company->id, $manager->id, [
            'absence_id' => $absence->id,
            'decision' => 'approve',
        ]);

        $this->assertSame('ABSENCE_NOT_PENDING', $executed['error']);
    }

    public function test_approval_deducts_leave_balance_when_type_deducts(): void
    {
        $this->ensureLeaveBalancesTable();

        ['company' => $company, 'manager' => $manager, 'employee' => $employee, 'type' => $type] = $this->tenant();

        // Type déductible + solde crédité (chaîne de logs legacy, même
        // fixture que AbsenceApproveTest) : l'approbation doit déduire 3 j.
        $type->update(['deducts_leave' => true]);
        LeaveBalanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'delta' => 20.0,
            'reason' => 'initial_credit',
            'reference_id' => 0,
            'balance_after' => 20.0,
        ]);

        $absence = $this->pendingAbsence($company, $employee, $type);

        $executed = $this->confirmDecision((string) $company->id, $manager->id, [
            'absence_id' => $absence->id,
            'decision' => 'approve',
        ]);

        $this->assertSame('approved', $executed['status']);

        $balance = LeaveBalance::query()
            ->where('company_id', $company->id)
            ->where('employee_id', $employee->id)
            ->where('year', 2026)
            ->first();

        $this->assertNotNull($balance, 'le snapshot leave_balances doit exister après approbation');
        $this->assertSame(3.0, (float) $balance->used);
    }

    /**
     * La fixture mvp (CreatesMvpSchema) ne crée pas `leave_balances` (table du
     * domaine congé ajoutée par les vraies migrations tenant) — elle est créée
     * ici à l'identique de la migration canonique pour le seul test qui exerce
     * la déduction de solde (sans FK, parité fixture).
     */
    private function ensureLeaveBalancesTable(): void
    {
        if (Schema::hasTable('leave_balances')) {
            return;
        }

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->uuid('company_id')->index();
            $table->unsignedInteger('employee_id');
            $table->unsignedInteger('absence_type_id');
            $table->decimal('balance', 6, 2)->default(0);
            $table->decimal('used', 6, 2)->default(0);
            $table->decimal('pending', 6, 2)->default(0);
            $table->unsignedSmallInteger('year');
            $table->timestampTz('updated_at')->useCurrent();

            $table->unique(['employee_id', 'absence_type_id', 'year']);
        });
    }
}
