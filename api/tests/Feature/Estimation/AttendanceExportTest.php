<?php

namespace Tests\Feature\Estimation;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Plan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class AttendanceExportTest extends TestCase
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

    public function test_manager_can_download_excel_compatible_csv_export(): void
    {
        $plan = Plan::query()->create([
            'name' => 'Business',
            'features' => ['excel_export' => true],
        ]);

        $company = Company::query()->create([
            'name' => 'Export Co',
            'slug' => 'export-co',
            'sector' => 'it',
            'country' => 'TN',
            'city' => 'Tunis',
            'email' => 'export@company.test',
            'plan_id' => $plan->id,
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
            'currency' => 'TND',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Sami',
            'last_name' => 'Export',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'salary_type' => 'hourly',
            'hourly_rate' => 20,
        ]);

        app()->instance('current_company', $company);
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-10',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-10 08:00:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-10 17:30:00', 'UTC'),
            'hours_worked' => 9.5,
            'overtime_hours' => 1.5,
            'status' => 'ontime',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($manager);

        $response = $this->get("/api/v1/employees/{$employee->id}/attendance-export?from=2026-04-01&to=2026-04-19");

        $response->assertOk();
        $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString(
            'attendance_export_employee_'.$employee->id.'_2026-04-01_2026-04-19.csv',
            (string) $response->headers->get('content-disposition')
        );
        $response->assertSee('employee_name', false);
        $response->assertSee('Sami Export', false);
        $response->assertSee('2026-04-10', false);
    }
}
