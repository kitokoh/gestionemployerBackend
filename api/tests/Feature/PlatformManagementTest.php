<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\SuperAdmin;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;
use Tests\Support\CreatesMvpSchema;

class PlatformManagementTest extends TestCase
{
    use CreatesMvpSchema;

    private SuperAdmin $superAdmin;
    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->withoutMiddleware([\Illuminate\Foundation\Http\Middleware\ValidateCsrfToken::class]);
        
        $this->setUpMvpSchema();
        
        // Ensure plans exist
        if (DB::table('plans')->count() === 0) {
            DB::table('plans')->insert([
                ['id' => 1, 'name' => 'Starter', 'max_employees' => 20, 'price_monthly' => 29, 'is_active' => true],
                ['id' => 2, 'name' => 'Business', 'max_employees' => null, 'price_monthly' => 79, 'is_active' => true],
            ]);
        }

        $this->superAdmin = SuperAdmin::create([
            'name' => 'Test Admin',
            'email' => 'admin@leopardorh.com',
            'password_hash' => Hash::make('password'),
        ]);

        $this->company = Company::create([
            'name' => 'Test Company',
            'slug' => 'test-company',
            'email' => 'contact@testco.com',
            'country' => 'DZ',
            'city' => 'Alger',
            'sector' => 'IT',
            'language' => 'fr',
            'plan_id' => 1,
            'status' => 'active',
            'tenancy_type' => 'shared',
            'schema_name' => 'company_test',
            'subscription_start' => now()->toDateString(),
            'subscription_end' => now()->addYear()->toDateString(),
        ]);
        
        // Add a fake employee in user_lookups so we can test the blocked access
        DB::table('public.user_lookups')->insert([
            'email' => 'employee@testco.com',
            'company_id' => $this->company->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 99,
            'role' => 'employee',
        ]);
        
        // Fake inserting employee inside shared_tenants
        DB::statement('SET search_path TO shared_tenants, public');
        DB::table('shared_tenants.employees')->insert([
            'id' => 99,
            'company_id' => $this->company->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'employee@testco.com',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_base' => 50000,
        ]);
        DB::statement('SET search_path TO public');
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_super_admin_can_view_dashboard()
    {
        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->get(route('platform.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('Dashboard');
        $response->assertSee('Entreprises Actives');
    }

    public function test_super_admin_can_filter_companies_by_status(): void
    {
        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->get(route('platform.companies.index', ['status' => 'active']));

        $response->assertStatus(200);
        $response->assertSee('Test Company');
    }

    public function test_super_admin_can_update_company_plan_and_status()
    {
        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->put(route('platform.companies.update', $this->company), [
                'name' => 'Updated Test Company',
                'plan_id' => 2, // Upgrade to Business
                'status' => 'trial',
            ]);

        $response->assertRedirect(route('platform.companies.show', $this->company));
        
        $this->company->refresh();
        $this->assertEquals('Updated Test Company', $this->company->name);
        $this->assertEquals(2, $this->company->plan_id);
        $this->assertEquals('trial', $this->company->status);
    }

    public function test_super_admin_can_suspend_company()
    {
        $this->assertEquals('active', $this->company->status);

        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->post(route('platform.companies.suspend', $this->company));

        $response->assertRedirect();
        
        $this->company->refresh();
        $this->assertEquals('suspended', $this->company->status);
    }

    public function test_suspended_company_blocks_auth_login()
    {
        // Suspend the company
        $this->company->update(['status' => 'suspended']);

        // Attempt as Employee
        $response = $this->postJson('/api/v1/auth/login', [
            'email' => 'employee@testco.com',
            'password' => 'password123',
            'device_name' => 'TestPhone',
            'fcm_token' => 'fcm_fake',
        ]);

        $response->assertStatus(403);
        $response->assertJson(['error' => 'ACCOUNT_SUSPENDED']);
    }

    public function test_super_admin_can_view_audit_logs()
    {
        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->get(route('platform.audit.index'));

        $response->assertStatus(200);
        $response->assertSee('Sécurité');
    }

    public function test_super_admin_can_view_invitations()
    {
        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->get(route('platform.invitations.index'));

        $response->assertStatus(200);
        $response->assertSee('Relances Managers');
    }

    public function test_super_admin_can_export_companies_csv()
    {
        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->get(route('platform.companies.export'));

        $response->assertStatus(200);
        $response->assertHeader('Content-Disposition', 'attachment; filename=leopardo_companies_export_' . date('Y-m-d') . '.csv');
        $this->assertStringContainsString('text/csv', $response->headers->get('Content-type'));
    }
}
