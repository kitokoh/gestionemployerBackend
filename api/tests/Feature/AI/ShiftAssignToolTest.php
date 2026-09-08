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
use App\Modules\Planning\Domain\Models\Schedule;
use Database\Seeders\AIToolRegistrySeeder;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B3b (#6857) — outil écriture `shift_assign` (contrat A3 #6850, flux A4) :
 * affectation d'un shift (schedule, BC-05 WORKFORCE) à un employé, exécutée
 * UNIQUEMENT après confirmation — parité REST
 * ScheduleController::assignEmployees : RBAC manager (`schedules.assign`),
 * schedule/employé du tenant, périmètre des managers d'équipe
 * (visibleToManager, PA2-SEC-002/003), invalidation du cache employés.
 */
class ShiftAssignToolTest extends TestCase
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
     * Étape 1 du flux A4 : le LLM propose l'outil → réponse de confirmation.
     *
     * @param  array<string, mixed>  $arguments
     */
    private function proposeAssign(string $companyId, int $userId, array $arguments): ToolResult
    {
        $engine = app(IntentEngine::class);

        return $engine->executeToolCalls(
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', 'shift_assign', $arguments)]),
            $companyId,
            $userId,
        )[0];
    }

    /**
     * Étape 2 du flux A4 : confirmation → exécution effective.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function confirmAssign(string $companyId, int $userId, array $arguments): array
    {
        return app(IntentEngine::class)
            ->executeConfirmedWrite('shift_assign', $arguments, $companyId, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ToolResult $result): array
    {
        $decoded = json_decode($result->content, true);

        return is_array($decoded) ? $decoded : [];
    }

    private function schedule(Company $company, string $name = 'Journée'): Schedule
    {
        /** @var Schedule $schedule */
        $schedule = Schedule::factory()->create([
            'company_id' => $company->id,
            'name' => $name,
        ]);

        return $schedule;
    }

    private function assignedScheduleId(Employee $employee): ?int
    {
        /** @var Employee|null $fresh */
        $fresh = Employee::query()->find($employee->id);

        return $fresh?->schedule_id;
    }

    public function test_tool_is_registered_as_write_tool_and_proposal_requires_confirmation_without_side_effect(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $schedule = $this->schedule($company);

        // Outil déclaré dans la config write (confirmation obligatoire) et
        // présent dans le registre exposé.
        $this->assertContains('shift_assign', config('ai.write_tools', []));
        $registryTool = app(ToolRegistry::class)->findTool('shift_assign');
        $this->assertNotNull($registryTool);
        $this->assertSame('manager', $registryTool['required_role']);

        // Proposition : confirmation requise, aucun effet de bord.
        $result = $this->proposeAssign((string) $company->id, $manager->id, [
            'schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertTrue($result->success);
        $payload = $this->payload($result);
        $this->assertSame('confirmation_required', $payload['status']);
        $this->assertArrayHasKey('pending_action_id', $payload);
        $this->assertSame('shift_assign', $payload['tool']);

        // Aucune affectation avant confirmation.
        $this->assertNull($this->assignedScheduleId($employee));
    }

    public function test_manager_assigns_schedule_after_confirmation(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $schedule = $this->schedule($company, 'Nuit');

        $proposal = $this->proposeAssign((string) $company->id, $manager->id, [
            'schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
        ]);
        $this->assertTrue($proposal->success);
        $this->assertNull($this->assignedScheduleId($employee));

        $executed = $this->confirmAssign((string) $company->id, $manager->id, [
            'schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertSame('assigned', $executed['status']);
        $this->assertSame($employee->id, $executed['employee_id']);
        $this->assertSame($schedule->id, $executed['schedule_id']);
        $this->assertSame('Nuit', $executed['schedule_name']);

        $this->assertSame($schedule->id, $this->assignedScheduleId($employee));
    }

    public function test_employee_cannot_assign_shifts(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $schedule = $this->schedule($company);

        // Un employé ne peut même pas proposer l'action (matrice
        // ai.tool_permissions : rôle manager requis).
        $result = $this->proposeAssign((string) $company->id, $employee->id, [
            'schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $this->payload($result)['error'] ?? null);

        // Défense en profondeur : la confirmation re-vérifie le rôle.
        $denied = $this->confirmAssign((string) $company->id, $employee->id, [
            'schedule_id' => $schedule->id,
            'employee_id' => $employee->id,
        ]);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $denied['error']);
    }

    public function test_cross_tenant_schedule_and_employee_are_not_found(): void
    {
        /** @var Company $companyA */
        $companyA = Company::factory()->create();
        /** @var Employee $managerA */
        $managerA = Employee::factory()->manager()->create(['company_id' => $companyA->id]);
        /** @var Company $companyB */
        $companyB = Company::factory()->create();
        /** @var Employee $employeeB */
        $employeeB = Employee::factory()->create(['company_id' => $companyB->id]);
        $scheduleB = $this->schedule($companyB);

        // Schedule d'un autre tenant → introuvable.
        $scheduleNotFound = $this->confirmAssign((string) $companyA->id, $managerA->id, [
            'schedule_id' => $scheduleB->id,
            'employee_id' => $employeeB->id,
        ]);
        $this->assertSame('Schedule not found', $scheduleNotFound['error']);

        // Employé d'un autre tenant → introuvable (isolation fail-closed) :
        // le schedule appartient bien au tenant A, seul l'employé est étranger.
        $scheduleA = $this->schedule($companyA);
        $employeeNotFound = $this->confirmAssign((string) $companyA->id, $managerA->id, [
            'schedule_id' => $scheduleA->id,
            'employee_id' => $employeeB->id,
        ]);
        $this->assertSame('Employee not found', $employeeNotFound['error']);
    }

    public function test_team_scoped_manager_cannot_assign_employee_outside_scope(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $supervisor */
        $supervisor = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => 700,
        ]);
        /** @var Employee $inScope */
        $inScope = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => 700,
        ]);
        /** @var Employee $outOfScope */
        $outOfScope = Employee::factory()->create([
            'company_id' => $company->id,
            'department_id' => 701,
        ]);
        $schedule = $this->schedule($company);

        // Employé hors périmètre (autre département) → introuvable.
        $denied = $this->confirmAssign((string) $company->id, $supervisor->id, [
            'schedule_id' => $schedule->id,
            'employee_id' => $outOfScope->id,
        ]);
        $this->assertSame('Employee not found', $denied['error']);

        // Employé du périmètre → affectation OK (parité REST visibleToManager).
        $executed = $this->confirmAssign((string) $company->id, $supervisor->id, [
            'schedule_id' => $schedule->id,
            'employee_id' => $inScope->id,
        ]);
        $this->assertSame('assigned', $executed['status']);
        $this->assertSame($schedule->id, $this->assignedScheduleId($inScope));
    }

    public function test_reassigning_another_schedule_overrides_previous_assignment(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);
        $day = $this->schedule($company, 'Journée');
        $night = $this->schedule($company, 'Nuit');

        // Première affectation (Journée), puis ré-affectation (Nuit) — parité
        // REST assignEmployees (le dernier schedule affecté fait foi).
        $this->confirmAssign((string) $company->id, $manager->id, [
            'schedule_id' => $day->id,
            'employee_id' => $employee->id,
        ]);
        $executed = $this->confirmAssign((string) $company->id, $manager->id, [
            'schedule_id' => $night->id,
            'employee_id' => $employee->id,
        ]);

        $this->assertSame('assigned', $executed['status']);
        $this->assertSame($night->id, $executed['schedule_id']);
        $this->assertSame($night->id, $this->assignedScheduleId($employee));
    }
}
