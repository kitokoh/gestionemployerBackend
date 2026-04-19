<?php

namespace Tests\Feature\Attendance;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class NightShiftAttendanceTest extends TestCase
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

    public function test_employee_keeps_overnight_shift_visible_after_midnight_and_can_check_out(): void
    {
        $company = Company::query()->create([
            'name' => 'Night Co',
            'slug' => 'night-co',
            'sector' => 'pharmacy',
            'country' => 'MA',
            'city' => 'Casablanca',
            'email' => 'night@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        app()->instance('current_company', $company);
        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Equipe Nuit',
            'start_time' => '22:00:00',
            'end_time' => '06:00:00',
            'late_tolerance_minutes' => 15,
            'overtime_threshold_daily' => 8,
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'first_name' => 'Nadia',
            'last_name' => 'Nuit',
            'email' => 'nadia@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        app()->forgetInstance('current_company');

        Sanctum::actingAs($employee);

        $this->travelTo(Carbon::parse('2026-04-19 22:05:00', 'UTC'));
        $this->postJson('/api/v1/attendance/check-in')->assertStatus(201);

        $this->travelTo(Carbon::parse('2026-04-20 01:00:00', 'UTC'));
        $today = $this->getJson('/api/v1/attendance/today');
        $today->assertOk();
        $today->assertJsonPath('data.item.checked_in', true);
        $today->assertJsonPath('data.item.check_in_time', '22:05');

        $this->travelTo(Carbon::parse('2026-04-20 06:05:00', 'UTC'));
        $checkout = $this->postJson('/api/v1/attendance/check-out');
        $checkout->assertOk();
        $checkout->assertJsonPath('data.date', '2026-04-19');
        $checkout->assertJsonPath('data.check_out', '2026-04-20T06:05:00+00:00');
    }
}
