<?php

declare(strict_types=1);

namespace Tests\Feature\CRM;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Laravel\Sanctum\Sanctum;
use Tests\RefreshTenantDatabase;
use Tests\TestCase;

/**
 * Issues #5712/#6977 — répertoire CRM tenant : listes paginées
 * `GET /crm/{leads,accounts,contacts,opportunities}` consommées par le
 * dashboard client web. Vérifie : isolation tenant (jamais de donnée d'un
 * autre tenant), pagination (meta), exclusions (leads convertis, comptes/
 * contacts archivés), embarquement du compte parent (contacts) et RBAC
 * (lecture = managers, 403 pour un employé).
 */
class CrmDirectoryListTest extends TestCase
{
    use RefreshTenantDatabase;

    private Company $company;

    private Company $other;

    private Employee $manager;

    protected function setUp(): void
    {
        parent::setUp();

        /** @var Company $company */
        $company = Company::factory()->create(['country' => 'DZ', 'currency' => 'DZD']);
        $this->company = $company;

        /** @var Company $other */
        $other = Company::factory()->create(['country' => 'MA', 'currency' => 'MAD']);
        $this->other = $other;

        /** @var Employee $manager */
        $manager = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);
        $this->manager = $manager;
    }

    public function test_leads_list_is_tenant_scoped_and_excludes_converted(): void
    {
        $own = CrmLead::query()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Karim',
            'last_name' => 'Benali',
            'email' => 'karim@usine.dz',
            'status' => 'new',
        ]);
        CrmLead::query()->create([
            'company_id' => $this->company->id,
            'first_name' => 'Convertis',
            'last_name' => 'Done',
            'status' => 'qualified',
            'converted_at' => now(),
        ]);
        $foreign = CrmLead::query()->create([
            'company_id' => $this->other->id,
            'first_name' => 'Autre',
            'last_name' => 'Tenant',
            'status' => 'new',
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/crm/leads?per_page=25')
            ->assertOk()
            ->assertJsonPath('meta.current_page', 1)
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.id', $own->id);
        $this->assertNotEquals($foreign->id, $response->json('data.0.id'));
    }

    public function test_accounts_list_excludes_archived_and_foreign_tenant(): void
    {
        $own = CrmAccount::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Usine Alger',
            'status' => 'active',
            'email' => 'contact@usine.dz',
        ]);
        CrmAccount::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Archivé',
            'status' => 'inactive',
            'archived_at' => now(),
        ]);
        CrmAccount::query()->create([
            'company_id' => $this->other->id,
            'name' => 'Autre tenant',
            'status' => 'active',
        ]);

        Sanctum::actingAs($this->manager);

        $response = $this->getJson('/api/v1/crm/accounts')
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.id', $own->id);
        $response->assertJsonPath('data.0.name', 'Usine Alger');
    }

    public function test_contacts_list_embeds_own_account(): void
    {
        $account = CrmAccount::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Usine Alger',
            'status' => 'active',
        ]);
        $contact = CrmContact::query()->create([
            'company_id' => $this->company->id,
            'account_id' => $account->id,
            'first_name' => 'Fatima',
            'last_name' => 'Meziane',
            'email' => 'fatima@usine.dz',
        ]);
        CrmContact::query()->create([
            'company_id' => $this->other->id,
            'first_name' => 'Autre',
            'last_name' => 'Tenant',
        ]);

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/crm/contacts')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $contact->id)
            ->assertJsonPath('data.0.account.id', $account->id)
            ->assertJsonPath('data.0.account.name', 'Usine Alger');
    }

    public function test_opportunities_list_is_tenant_scoped(): void
    {
        $own = CrmOpportunity::query()->create([
            'company_id' => $this->company->id,
            'name' => 'Contrat maintenance',
            'stage' => 'proposal',
            'status' => 'open',
        ]);
        CrmOpportunity::query()->create([
            'company_id' => $this->other->id,
            'name' => 'Autre tenant',
            'stage' => 'prospecting',
            'status' => 'open',
        ]);

        Sanctum::actingAs($this->manager);

        $this->getJson('/api/v1/crm/opportunities?per_page=100')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $own->id)
            ->assertJsonPath('data.0.stage', 'proposal');
    }

    public function test_employee_without_manager_role_is_denied(): void
    {
        /** @var Employee $employee */
        $employee = Employee::factory()->create([
            'company_id' => $this->company->id,
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->getJson('/api/v1/crm/leads')->assertForbidden();
        $this->getJson('/api/v1/crm/accounts')->assertForbidden();
    }
}
