<?php

namespace Tests\Feature;

use App\Exceptions\DomainException;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Services\EmployeeService;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class PlatformPlanTest extends TestCase
{
    use CreatesMvpSchema;

    private SuperAdmin $superAdmin;

    private Plan $limitedPlan;

    private Company $company;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutMiddleware([ValidateCsrfToken::class]);
        $this->setUpMvpSchema();

        $this->superAdmin = SuperAdmin::create([
            'name' => 'Admin',
            'email' => 'admin@leopardo.com',
            'password_hash' => Hash::make('password'),
        ]);

        $this->limitedPlan = Plan::create([
            'name' => 'Limited',
            'price_monthly' => 10,
            'price_yearly' => 100,
            'max_employees' => 2,
            'features' => ['biometric' => true],
            'trial_days' => 7,
            'is_active' => true,
        ]);

        $this->company = Company::create([
            'id' => Str::uuid(),
            'name' => 'Limited Co',
            'slug' => 'limited-co',
            'email' => 'contact@limited.com',
            'plan_id' => $this->limitedPlan->id,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'sector' => 'Tech',
            'country' => 'DZ',
            'city' => 'Algiers',
        ]);
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_super_admin_can_create_plan(): void
    {
        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->post(route('platform.plans.store'), [
                'name' => 'Business Plus',
                'price_monthly' => 49,
                'price_yearly' => 490,
                'max_employees' => 50,
                'trial_days' => 30,
                'is_active' => 1,
                'features' => ['advanced_reports' => 1],
            ]);

        $response->assertRedirect(route('platform.plans.index'));
        $this->assertDatabaseHas('plans', ['name' => 'Business Plus'], 'platform');
    }

    public function test_quota_is_enforced_on_employee_creation(): void
    {
        Employee::create([
            'company_id' => $this->company->id,
            'first_name' => 'E1',
            'last_name' => 'L1',
            'email' => 'e1@test.com',
            'password_hash' => 'hash',
        ]);
        Employee::create([
            'company_id' => $this->company->id,
            'first_name' => 'E2',
            'last_name' => 'L2',
            'email' => 'e2@test.com',
            'password_hash' => 'hash',
        ]);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage("Quota d'employes atteint");

        $service = app(EmployeeService::class);
        $service->create([
            'first_name' => 'E3',
            'last_name' => 'L3',
            'email' => 'e3@test.com',
            'company_id' => $this->company->id,
        ]);
    }

    public function test_dashboard_shows_correct_mrr(): void
    {
        $response = $this->actingAs($this->superAdmin, 'super_admin_web')
            ->get(route('platform.dashboard'));

        $response->assertStatus(200);
        $response->assertSee('10');
    }
}
