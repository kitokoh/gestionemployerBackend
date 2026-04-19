<?php

namespace Tests\Unit;

use App\Models\Company;
use App\Models\Employee;
use App\Models\Schedule;
use App\Services\EstimationService;
use Illuminate\Support\Carbon;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EstimationWorkingDaysTest extends TestCase
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

    public function test_count_working_days_inclusive_same_day(): void
    {
        $company = Company::query()->create([
            'name' => 'Demo',
            'slug' => 'demo',
            'sector' => 'tech',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'admin@demo.test',
            'schema_name' => 'shared_tenants',
        ]);

        $schedule = Schedule::query()->create([
            'company_id' => $company->id,
            'name' => 'Standard',
            'start_time' => '09:00:00',
            'end_time' => '17:00:00',
            // Monday to Thursday
            'work_days' => [1, 2, 3, 4],
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'schedule_id' => $schedule->id,
            'email' => 'test@demo.test',
            'password_hash' => 'hash',
        ]);

        // Access private method via reflection
        $service = app(EstimationService::class);
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('countWorkingDaysInclusive');
        $method->setAccessible(true);

        // Same day: A Monday (work day)
        $monday = Carbon::parse('2026-04-13', 'UTC'); // April 13, 2026 is Monday
        $countMonday = $method->invoke($service, $employee, $monday, $monday->copy());
        $this->assertSame(1, $countMonday, 'Should count as 1 working day if from and to are the same working day.');

        // Same day: A Friday (non-work day for this schedule)
        $friday = Carbon::parse('2026-04-17', 'UTC'); // April 17, 2026 is Friday
        $countFriday = $method->invoke($service, $employee, $friday, $friday->copy());
        $this->assertSame(0, $countFriday, 'Should count as 0 working days if from and to are the same non-working day.');
    }
}
