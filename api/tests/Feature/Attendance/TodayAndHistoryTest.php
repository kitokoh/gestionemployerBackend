<?php

namespace Tests\Feature\Attendance;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class TodayAndHistoryTest extends TestCase
{
    use CreatesMvpSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpMvpSchema();
    }

    protected function tearDown(): void
    {
        $this->tearDownMvpSchema();
        parent::tearDown();
    }

    public function test_today_endpoint_returns_self_status_for_employee(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in')
            ->assertStatus(201);

        $today = $this->getJson('/api/v1/attendance/today');

        $today->assertOk();
        $today->assertJsonPath('data.mode', 'single');
        $today->assertJsonPath('data.item.employee_id', $employee->id);
        $today->assertJsonPath('data.item.checked_in', true);
        $today->assertJsonPath('data.item.check_in_time', '08:00');
    }

    public function test_employee_history_returns_only_self(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $employeeA = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $employeeB = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@b.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        app()->instance('current_company', $company);
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeA->id,
            'date' => '2026-04-03',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-03 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-03 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-04-03',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-03 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-03 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($employeeA);

        $resp = $this->getJson('/api/v1/attendance');

        $resp->assertOk();
        $ids = collect($resp->json('data'))->pluck('employee_id')->unique()->values()->all();
        $this->assertSame([$employeeA->id], $ids);
    }

    public function test_manager_keeps_personal_today_status_without_blocking_on_team_data(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $this->travelTo(Carbon::parse('2026-04-04 08:00:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        Sanctum::actingAs($manager);
        $this->travelTo(Carbon::parse('2026-04-04 08:05:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        Sanctum::actingAs($manager);
        $all = $this->getJson('/api/v1/attendance/today');

        $all->assertOk();
        $all->assertJsonPath('data.mode', 'single');
        $all->assertJsonPath('data.item.employee_id', $manager->id);
        $all->assertJsonPath('data.item.checked_in', true);
        $all->assertJsonMissingPath('data.context');

        Sanctum::actingAs($employee);
        $forbidden = $this->getJson("/api/v1/attendance/today?employee_id={$manager->id}");
        $forbidden->assertStatus(403);
    }

    public function test_manager_can_load_team_overview_separately(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Sami',
            'last_name' => 'Employee',
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 10,
        ]);

        app()->instance('current_company', $company);
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-04',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-04 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-04 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/team-overview');

        $response->assertOk();
        $response->assertJsonPath('data.mode', 'collection');
        $response->assertJsonCount(2, 'data.items');
        $response->assertJsonStructure([
            'data' => [
                'mode',
                'items' => [
                    '*' => [
                        'employee_id',
                        'name',
                        'role',
                        'manager_role',
                        'checked_in',
                        'check_in_time',
                        'check_out_time',
                        'hours_worked',
                        'overtime_hours',
                        'estimated_gain',
                        'currency',
                        'status',
                    ],
                ],
                'meta',
            ],
        ]);
    }

    public function test_manager_history_defaults_to_self_when_no_employee_id_is_given(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        app()->instance('current_company', $company);
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'date' => '2026-04-03',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-03 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-03 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-03',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-03 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-03 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $resp = $this->getJson('/api/v1/attendance');

        $resp->assertOk();
        $ids = collect($resp->json('data'))->pluck('employee_id')->unique()->values()->all();
        $this->assertSame([$manager->id], $ids);
    }

    public function test_tenant_isolation_on_attendance_history(): void
    {
        $companyA = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $companyB = Company::query()->create([
            'name' => 'Company B',
            'slug' => 'company-b',
            'sector' => 'restaurant',
            'country' => 'DZ',
            'city' => 'Oran',
            'email' => 'b@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        $managerA = Employee::query()->create([
            'company_id' => $companyA->id,
            'email' => 'manager@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employeeB = Employee::withoutGlobalScopes()->create([
            'company_id' => $companyB->id,
            'email' => 'employee@b.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        app()->instance('current_company', $companyB);
        AttendanceLog::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => $employeeB->id,
            'date' => '2026-04-03',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-03 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-03 17:00:00', 'UTC'),
            'hours_worked' => 9.0,
            'overtime_hours' => 1.0,
            'status' => 'ontime',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($managerA);
        $resp = $this->getJson('/api/v1/attendance');

        $resp->assertOk();
        $this->assertSame([], $resp->json('data'));
    }
}
