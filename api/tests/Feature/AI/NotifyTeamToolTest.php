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
use App\Modules\Notification\Domain\Models\CompanyAnnouncement;
use App\Modules\Notification\Domain\Models\Notification;
use Database\Seeders\AIToolRegistrySeeder;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

/**
 * B3c (#6858) — outil envoi `notify_team` (contrat A3 #6850, flux A4) :
 * message à une équipe via le système canonique d'annonces (BC-13 COMMS),
 * exécuté UNIQUEMENT après confirmation — parité REST AnnouncementController
 * (store + authorizeAudience) : RBAC manager (`announcements.create`),
 * cible `company` réservée principal/RH, cible `department` bornée au
 * département du manager, plafond anti-spam par acteur/heure.
 */
class NotifyTeamToolTest extends TestCase
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
    private function proposeNotify(string $companyId, int $userId, array $arguments): ToolResult
    {
        $engine = app(IntentEngine::class);

        return $engine->executeToolCalls(
            new AIResponse(content: '', toolCalls: [new ToolCall('call_1', 'notify_team', $arguments)]),
            $companyId,
            $userId,
        )[0];
    }

    /**
     * Étape 2 du flux A4 : confirmation → envoi effectif.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function confirmNotify(string $companyId, int $userId, array $arguments): array
    {
        return app(IntentEngine::class)
            ->executeConfirmedWrite('notify_team', $arguments, $companyId, $userId);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(ToolResult $result): array
    {
        $decoded = json_decode($result->content, true);

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * @return array{company: Company, manager: Employee, employee: Employee}
     */
    private function tenant(): array
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $manager */
        $manager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        /** @var Employee $employee */
        $employee = Employee::factory()->create(['company_id' => $company->id]);

        return compact('company', 'manager', 'employee');
    }

    private function notificationCount(Company $company, Employee $employee): int
    {
        return Notification::query()
            ->where('employee_id', $employee->id)
            ->where('company_id', $company->id)
            ->count();
    }

    public function test_tool_is_registered_as_send_tool_and_proposal_requires_confirmation_without_side_effect(): void
    {
        ['company' => $company, 'manager' => $manager, 'employee' => $employee] = $this->tenant();

        $this->assertContains('notify_team', config('ai.write_tools', []));
        $registryTool = app(ToolRegistry::class)->findTool('notify_team');
        $this->assertNotNull($registryTool);
        $this->assertSame('manager', $registryTool['required_role']);

        $result = $this->proposeNotify((string) $company->id, $manager->id, [
            'title' => 'Réunion demain',
            'message' => 'Présence obligatoire à 9h.',
            'audience_type' => 'company',
        ]);

        $this->assertTrue($result->success);
        $payload = $this->payload($result);
        $this->assertSame('confirmation_required', $payload['status']);
        $this->assertArrayHasKey('pending_action_id', $payload);

        // Aucun effet de bord avant confirmation : ni annonce, ni notification.
        $this->assertSame(0, CompanyAnnouncement::query()->where('company_id', $company->id)->count());
        $this->assertSame(0, $this->notificationCount($company, $employee));
    }

    public function test_principal_broadcasts_to_company_after_confirmation_and_notifications_fan_out(): void
    {
        ['company' => $company, 'manager' => $manager, 'employee' => $employee] = $this->tenant();
        /** @var Employee $other */
        $other = Employee::factory()->create(['company_id' => $company->id]);

        $executed = $this->confirmNotify((string) $company->id, $manager->id, [
            'title' => 'Pique-nique vendredi',
            'message' => 'Rejoignez-nous à midi.',
            'audience_type' => 'company',
        ]);

        $this->assertArrayNotHasKey('error', $executed);
        $this->assertSame('published', $executed['status']);
        $this->assertSame('company', $executed['audience_type']);
        $this->assertSame(2, $executed['recipients_count']);

        $this->assertDatabaseHas('company_announcements', [
            'company_id' => $company->id,
            'created_by' => $manager->id,
            'audience_type' => 'company',
            'status' => 'published',
        ]);

        // Fan-out : les employés reçoivent la notification, pas l'auteur.
        $this->assertSame(1, $this->notificationCount($company, $employee));
        $this->assertSame(1, $this->notificationCount($company, $other));
        $this->assertSame(0, $this->notificationCount($company, $manager));
    }

    public function test_department_manager_broadcasts_to_own_department_only(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $deptManager */
        $deptManager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => 700,
        ]);
        /** @var Employee $inDept */
        $inDept = Employee::factory()->create(['company_id' => $company->id, 'department_id' => 700]);
        /** @var Employee $otherDept */
        $otherDept = Employee::factory()->create(['company_id' => $company->id, 'department_id' => 701]);

        $executed = $this->confirmNotify((string) $company->id, $deptManager->id, [
            'title' => 'Point équipe',
            'message' => 'Bilan hebdo à 17h.',
            'audience_type' => 'department',
            'department_id' => 700,
        ]);

        $this->assertArrayNotHasKey('error', $executed);
        $this->assertSame('published', $executed['status']);
        $this->assertSame(1, $executed['recipients_count']);
        $this->assertSame(1, $this->notificationCount($company, $inDept));
        $this->assertSame(0, $this->notificationCount($company, $otherDept));
    }

    public function test_non_principal_cannot_broadcast_to_company(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $deptManager */
        $deptManager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => 700,
        ]);

        $executed = $this->confirmNotify((string) $company->id, $deptManager->id, [
            'title' => 'Réunion générale',
            'message' => 'Toute l\'entreprise est conviée.',
            'audience_type' => 'company',
        ]);

        $this->assertSame('AUDIENCE_NOT_ALLOWED', $executed['error']);
        $this->assertSame(0, CompanyAnnouncement::query()->where('company_id', $company->id)->count());
    }

    public function test_department_manager_cannot_broadcast_to_another_department(): void
    {
        /** @var Company $company */
        $company = Company::factory()->create();
        /** @var Employee $deptManager */
        $deptManager = Employee::factory()->managerDept()->create([
            'company_id' => $company->id,
            'department_id' => 700,
        ]);

        $executed = $this->confirmNotify((string) $company->id, $deptManager->id, [
            'title' => 'Point technique',
            'message' => 'Réunion du département 701.',
            'audience_type' => 'department',
            'department_id' => 701,
        ]);

        $this->assertSame('AUDIENCE_NOT_ALLOWED', $executed['error']);
        $this->assertSame(0, CompanyAnnouncement::query()->where('company_id', $company->id)->count());
    }

    public function test_employee_cannot_notify_a_team(): void
    {
        ['company' => $company, 'employee' => $employee] = $this->tenant();

        $result = $this->proposeNotify((string) $company->id, $employee->id, [
            'title' => 'Spam',
            'message' => 'Sera refusé.',
            'audience_type' => 'company',
        ]);

        $this->assertFalse($result->success);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $this->payload($result)['error'] ?? null);

        $denied = $this->confirmNotify((string) $company->id, $employee->id, [
            'title' => 'Spam',
            'message' => 'Sera refusé.',
            'audience_type' => 'company',
        ]);
        $this->assertSame('AI_TOOL_PERMISSION_DENIED', $denied['error']);
    }

    public function test_invalid_content_and_missing_department_are_rejected(): void
    {
        ['company' => $company, 'manager' => $manager] = $this->tenant();

        $noTitle = $this->confirmNotify((string) $company->id, $manager->id, [
            'title' => '',
            'message' => 'Contenu sans titre.',
            'audience_type' => 'department',
            'department_id' => 700,
        ]);
        $this->assertSame('NOTIFY_TITLE_INVALID', $noTitle['error']);

        $noMessage = $this->confirmNotify((string) $company->id, $manager->id, [
            'title' => 'Sans message',
            'message' => '   ',
            'audience_type' => 'department',
            'department_id' => 700,
        ]);
        $this->assertSame('NOTIFY_MESSAGE_INVALID', $noMessage['error']);

        $noDepartment = $this->confirmNotify((string) $company->id, $manager->id, [
            'title' => 'Vers quelle équipe ?',
            'message' => 'Département manquant.',
            'audience_type' => 'department',
        ]);
        $this->assertSame('NOTIFY_DEPARTMENT_REQUIRED', $noDepartment['error']);
    }

    public function test_rate_limit_blocks_spam_after_hourly_cap(): void
    {
        config(['ai.notify_team_rate.max_per_hour' => 2]);

        ['company' => $company, 'manager' => $manager] = $this->tenant();
        /** @var Employee $employeeA */
        $employeeA = Employee::factory()->create(['company_id' => $company->id]);

        // Deux envois autorisés, le troisième est refusé (même acteur/heure).
        $first = $this->confirmNotify((string) $company->id, $manager->id, [
            'title' => 'Message 1',
            'message' => 'Premier envoi.',
            'audience_type' => 'company',
        ]);
        $this->assertSame('published', $first['status']);

        $second = $this->confirmNotify((string) $company->id, $manager->id, [
            'title' => 'Message 2',
            'message' => 'Deuxième envoi.',
            'audience_type' => 'company',
        ]);
        $this->assertSame('published', $second['status']);

        $third = $this->confirmNotify((string) $company->id, $manager->id, [
            'title' => 'Message 3',
            'message' => 'Trop de messages.',
            'audience_type' => 'company',
        ]);
        $this->assertSame('NOTIFY_RATE_LIMITED', $third['error']);

        // Un autre manager du même tenant n'est pas affecté par le plafond.
        /** @var Employee $otherManager */
        $otherManager = Employee::factory()->manager()->create(['company_id' => $company->id]);
        $otherSend = $this->confirmNotify((string) $company->id, $otherManager->id, [
            'title' => 'Message bis',
            'message' => 'Plafond par acteur.',
            'audience_type' => 'company',
        ]);
        $this->assertSame('published', $otherSend['status']);
    }
}
