<?php

namespace Tests\Feature;

use App\Models\AttendanceKiosk;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class KioskSecurityTest extends TestCase
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

    public function test_archived_employee_cannot_punch_via_kiosk(): void
    {
        $company = Company::query()->create([
            'name' => 'Company A',
            'slug' => 'company-a',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'a@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Karim',
            'last_name' => 'Archived',
            'email' => 'karim@company.test',
            'zkteco_id' => 'FP-001',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'archived',
            'biometric_fingerprint_enabled' => true,
        ]);

        $plainToken = Str::random(48);
        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Entree',
            'device_code' => 'KIOSK01',
            'sync_token_hash' => Hash::make($plainToken),
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-Kiosk-Token', $plainToken)
            ->postJson('/api/v1/kiosks/KIOSK01/punch', [
                'identifier' => 'FP-001',
                'action' => 'check_in',
            ]);

        // Expected behavior: blocked because employee is archived.
        $response->assertStatus(403);
    }

    public function test_suspended_company_kiosk_is_blocked(): void
    {
        $company = Company::query()->create([
            'name' => 'Company Suspended',
            'slug' => 'company-suspended',
            'sector' => 'services',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'suspended@company.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'suspended',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@suspended.test',
            'zkteco_id' => 'FP-002',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_fingerprint_enabled' => true,
        ]);

        $plainToken = Str::random(48);
        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Entree',
            'device_code' => 'KIOSK02',
            'sync_token_hash' => Hash::make($plainToken),
            'status' => 'active',
        ]);

        $response = $this->withHeader('X-Kiosk-Token', $plainToken)
            ->postJson('/api/v1/kiosks/KIOSK02/punch', [
                'identifier' => 'FP-002',
                'action' => 'check_in',
            ]);

        // Expected behavior: blocked because company is suspended.
        $response->assertStatus(403);
    }
}
