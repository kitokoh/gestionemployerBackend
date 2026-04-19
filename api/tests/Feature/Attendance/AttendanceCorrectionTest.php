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

class AttendanceCorrectionTest extends TestCase
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

    public function test_manager_can_correct_employee_attendance_log(): void
    {
        [$company, $manager, $employee, $log] = $this->seedCorrectionContext();

        app()->instance('current_company', $company);
        Sanctum::actingAs($manager);

        $response = $this->patchJson("/api/v1/attendance/{$log->id}", [
            'check_in' => '2026-04-08T08:30:00Z',
            'check_out' => '2026-04-08T17:30:00Z',
            'correction_note' => 'Retard regularise apres verification du manager.',
        ]);

        $response->assertOk();
        $response->assertJsonPath('data.corrected_by', $manager->id);
        $response->assertJsonPath('data.correction_note', 'Retard regularise apres verification du manager.');
        $response->assertJsonPath('data.hours_worked', '9.00');

        $fresh = $log->fresh();
        $this->assertSame($manager->id, $fresh->corrected_by);
        $this->assertSame('Retard regularise apres verification du manager.', $fresh->correction_note);
        $this->assertSame('9.00', $fresh->hours_worked);
        $this->assertSame('late', $fresh->status);
    }

    public function test_employee_cannot_correct_attendance_log(): void
    {
        [$company, $manager, $employee, $log] = $this->seedCorrectionContext();

        app()->instance('current_company', $company);
        Sanctum::actingAs($employee);

        $this->patchJson("/api/v1/attendance/{$log->id}", [
            'check_in' => '2026-04-08T08:05:00Z',
            'correction_note' => 'Tentative employee',
        ])->assertStatus(403);
    }

    private function seedCorrectionContext(): array
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
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@a.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $log = AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => '2026-04-08',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-08 09:15:00', 'UTC'),
            'check_out' => Carbon::parse('2026-04-08 17:00:00', 'UTC'),
            'hours_worked' => 7.75,
            'overtime_hours' => 0,
            'status' => 'late',
            'late_minutes' => 15,
        ]);

        return [$company, $manager, $employee, $log];
    }
}
