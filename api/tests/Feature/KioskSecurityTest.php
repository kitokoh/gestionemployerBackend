<?php

namespace Tests\Feature;

use App\Models\AttendanceKiosk;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
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

    private function setPath(string $path): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("SET search_path TO $path");
        }
    }

    public function test_kiosk_punch_rejects_archived_employee(): void
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

        $this->setPath('shared_tenants,public');

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'archived@company.test',
            'matricule' => 'EMP-ARCHIVED',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'archived',
            'biometric_fingerprint_enabled' => true,
        ]);

        $this->setPath('public');

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Kiosk 1',
            'device_code' => 'KIOSK1',
            'sync_token_hash' => Hash::make('token123'),
            'status' => 'active',
        ]);

        $this->withHeader('X-Kiosk-Token', 'token123')
            ->postJson('/api/v1/kiosks/KIOSK1/punch', [
                'identifier' => 'EMP-ARCHIVED',
                'action' => 'check_in',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'EMPLOYEE_ARCHIVED');
    }

    public function test_kiosk_endpoints_reject_suspended_company(): void
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

        $this->setPath('public');

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Kiosk 1',
            'device_code' => 'KIOSK_SUSPENDED',
            'sync_token_hash' => Hash::make('token123'),
            'status' => 'active',
        ]);

        // Test punch
        $this->withHeader('X-Kiosk-Token', 'token123')
            ->postJson('/api/v1/kiosks/KIOSK_SUSPENDED/punch', [
                'identifier' => 'SOME-ID',
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'ACCOUNT_SUSPENDED');

        // Test roster
        $this->withHeader('X-Kiosk-Token', 'token123')
            ->getJson('/api/v1/kiosks/KIOSK_SUSPENDED/roster')
            ->assertStatus(403)
            ->assertJsonPath('error', 'ACCOUNT_SUSPENDED');

        // Test sync
        $this->withHeader('X-Kiosk-Token', 'token123')
            ->postJson('/api/v1/kiosks/KIOSK_SUSPENDED/sync', [
                'events' => [],
            ])
            ->assertStatus(403)
            ->assertJsonPath('error', 'ACCOUNT_SUSPENDED');
    }

    public function test_kiosk_sync_skips_archived_employee_punches(): void
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

        $this->setPath('shared_tenants,public');

        $archived = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'archived@company.test',
            'matricule' => 'ARCHIVED',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'archived',
            'biometric_fingerprint_enabled' => true,
        ]);

        $active = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'active@company.test',
            'matricule' => 'ACTIVE',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
            'biometric_fingerprint_enabled' => true,
        ]);

        $this->setPath('public');

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => 'Kiosk 1',
            'device_code' => 'KIOSK1',
            'sync_token_hash' => Hash::make('token123'),
            'status' => 'active',
        ]);

        $this->withHeader('X-Kiosk-Token', 'token123')
            ->postJson('/api/v1/kiosks/KIOSK1/sync', [
                'events' => [
                    [
                        'identifier' => 'ARCHIVED',
                        'action' => 'check_in',
                        'occurred_at' => now()->subHour()->toIso8601String(),
                        'external_event_id' => 'evt-archived',
                    ],
                    [
                        'identifier' => 'ACTIVE',
                        'action' => 'check_in',
                        'occurred_at' => now()->subHour()->toIso8601String(),
                        'external_event_id' => 'evt-active',
                    ],
                ],
            ])
            ->assertOk()
            ->assertJsonPath('data.processed_count', 1);

        $this->setPath('shared_tenants,public');
        $this->assertDatabaseMissing('attendance_logs', ['external_event_id' => 'evt-archived']);
        $this->assertDatabaseHas('attendance_logs', ['external_event_id' => 'evt-active']);
    }
}
