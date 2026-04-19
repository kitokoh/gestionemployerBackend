<?php

namespace Tests\Feature\Attendance;

use App\Mail\AttendanceAlertMail;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class MissingPunchAlertsCommandTest extends TestCase
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

    public function test_command_sends_missing_check_in_alerts(): void
    {
        Mail::fake();

        [$company, $manager] = $this->seedAlertContext();

        $this->travelTo(Carbon::parse('2026-04-21 09:35:00', 'UTC'));
        Artisan::call('attendance:notify-missing-punches', [
            '--window' => 10,
            '--missing-check-in-at' => '09:30',
        ]);

        Mail::assertSent(AttendanceAlertMail::class, function (AttendanceAlertMail $mail) use ($company, $manager): bool {
            return $mail->company->is($company)
                && $mail->recipient->is($manager)
                && $mail->alertType === 'missing_check_in';
        });

        $this->assertDatabaseHas('notifications', [
            'company_id' => $company->id,
            'employee_id' => $manager->id,
            'type' => 'missing_check_in',
        ]);
    }

    public function test_command_sends_missing_check_out_alerts(): void
    {
        Mail::fake();

        [$company, $manager, $employee, $schedule] = $this->seedAlertContext();

        app()->instance('current_company', $company);
        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'schedule_id' => $schedule->id,
            'date' => '2026-04-21',
            'session_number' => 1,
            'check_in' => Carbon::parse('2026-04-21 08:01:00', 'UTC'),
            'status' => 'incomplete',
        ]);
        app()->forgetInstance('current_company');

        $this->travelTo(Carbon::parse('2026-04-21 18:05:00', 'UTC'));
        Artisan::call('attendance:notify-missing-punches', [
            '--window' => 10,
            '--missing-check-out-at' => '18:00',
        ]);

        Mail::assertSent(AttendanceAlertMail::class, function (AttendanceAlertMail $mail) use ($manager): bool {
            return $mail->recipient->is($manager)
                && $mail->alertType === 'missing_check_out';
        });

        $this->assertDatabaseHas('notifications', [
            'employee_id' => $manager->id,
            'type' => 'missing_check_out',
        ]);
    }

    private function seedAlertContext(): array
    {
        $company = Company::query()->create([
            'name' => 'Alert Co',
            'slug' => 'alert-co',
            'sector' => 'btp',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'alert@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'timezone' => 'UTC',
        ]);

        app()->instance('current_company', $company);
        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Jour',
            'start_time' => '08:00:00',
            'end_time' => '17:00:00',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'first_name' => 'Karim',
            'last_name' => 'RH',
            'email' => 'karim@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'first_name' => 'Sofiane',
            'last_name' => 'BTP',
            'email' => 'sofiane@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);
        app()->forgetInstance('current_company');

        return [$company, $manager, $employee, $schedule];
    }
}
