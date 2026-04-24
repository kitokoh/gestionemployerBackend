<?php

namespace Tests\Feature;

use App\Models\AttendanceKiosk;
use App\Models\BiometricEnrollmentRequest;
use App\Models\Company;
use App\Models\UserInvitation;
use Illuminate\Support\Str;
use Tests\TestCase;
use Tests\Support\CreatesMvpSchema;

class SentinelTenantIsolationHardeningTest extends TestCase
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

    public function test_user_invitations_are_isolated_by_company(): void
    {
        $companyA = $this->createTestCompany('Company A', 'company-a');
        $companyB = $this->createTestCompany('Company B', 'company-b');

        // Create invitation for A
        UserInvitation::query()->create([
            'id' => Str::uuid(),
            'company_id' => $companyA->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 1,
            'email' => 'invitation@company-a.test',
            'role' => 'employee',
            'invited_by_type' => 'manager',
            'invited_by_email' => 'manager@company-a.test',
            'token_hash' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        // Create invitation for B
        UserInvitation::query()->create([
            'id' => Str::uuid(),
            'company_id' => $companyB->id,
            'schema_name' => 'shared_tenants',
            'employee_id' => 2,
            'email' => 'invitation@company-b.test',
            'role' => 'employee',
            'invited_by_type' => 'manager',
            'invited_by_email' => 'manager@company-b.test',
            'token_hash' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        app()->instance('current_company', $companyA);

        $visible = UserInvitation::all();
        $this->assertCount(1, $visible, 'Company A should only see its own invitations');
        $this->assertEquals('invitation@company-a.test', $visible->first()->email);
    }

    public function test_biometric_requests_are_isolated_by_company(): void
    {
        $companyA = $this->createTestCompany('Company A', 'company-a');
        $companyB = $this->createTestCompany('Company B', 'company-b');

        BiometricEnrollmentRequest::query()->create([
            'company_id' => $companyA->id,
            'employee_id' => 1,
            'status' => 'pending',
        ]);

        BiometricEnrollmentRequest::query()->create([
            'company_id' => $companyB->id,
            'employee_id' => 2,
            'status' => 'pending',
        ]);

        app()->instance('current_company', $companyA);

        $visible = BiometricEnrollmentRequest::all();
        $this->assertCount(1, $visible, 'Company A should only see its own biometric requests');
    }

    public function test_attendance_kiosks_are_isolated_by_company(): void
    {
        $companyA = $this->createTestCompany('Company A', 'company-a');
        $companyB = $this->createTestCompany('Company B', 'company-b');

        AttendanceKiosk::query()->create([
            'company_id' => $companyA->id,
            'name' => 'Kiosk A',
            'device_code' => 'CODE-A',
        ]);

        AttendanceKiosk::query()->create([
            'company_id' => $companyB->id,
            'name' => 'Kiosk B',
            'device_code' => 'CODE-B',
        ]);

        app()->instance('current_company', $companyA);

        $visible = AttendanceKiosk::all();
        $this->assertCount(1, $visible, 'Company A should only see its own kiosks');
    }

    private function createTestCompany(string $name, string $slug): Company
    {
        return Company::query()->create([
            'id' => Str::uuid(),
            'name' => $name,
            'slug' => $slug,
            'sector' => 'test',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => "contact@{$slug}.test",
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);
    }
}
