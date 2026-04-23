<?php

namespace Tests\Feature\Contracts;

use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class MobilePayloadContractTest extends TestCase
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

    public function test_auth_me_payload_matches_mobile_contract(): void
    {
        $company = Company::query()->create([
            'name' => 'TechCorp SPA',
            'slug' => 'techcorp-spa',
            'sector' => 'technology',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'contact@techcorp.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
            'language' => 'fr',
            'timezone' => 'Africa/Algiers',
            'currency' => 'DZD',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'matricule' => 'EMP-0042',
            'first_name' => 'Ahmed',
            'last_name' => 'Benali',
            'email' => 'ahmed.benali@techcorp.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/auth/me');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id',
                'company_id',
                'matricule',
                'first_name',
                'middle_name',
                'last_name',
                'preferred_name',
                'email',
                'personal_email',
                'phone',
                'role',
                'manager_role',
                'status',
                'photo_path',
                'biometric_face_enabled',
                'biometric_fingerprint_enabled',
                'address_line',
                'postal_code',
                'emergency_contact_name',
                'emergency_contact_phone',
                'extra_data',
                'capabilities' => [
                    'can_view_dashboard',
                    'can_create_employees',
                    'can_manage_invitations',
                    'can_manage_biometrics',
                    'can_view_payroll',
                    'is_principal',
                ],
                'features',
                'suggested_home_route',
                'company' => [
                    'id',
                    'name',
                    'language',
                    'timezone',
                    'currency',
                ],
            ],
        ]);

        $response->assertJsonPath('data.matricule', 'EMP-0042');
        $response->assertJsonPath('data.email', 'ahmed.benali@techcorp.test');
        $response->assertJsonPath('data.company.name', 'TechCorp SPA');
        $response->assertJsonPath('data.company.timezone', 'Africa/Algiers');
    }

    public function test_attendance_today_single_payload_matches_mobile_contract(): void
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
            'first_name' => 'Sami',
            'last_name' => 'Employee',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => Carbon::now('UTC')->toDateString(),
            'session_number' => 1,
            'check_in' => Carbon::parse(Carbon::now('UTC')->toDateString() . ' 08:00:00', 'UTC'),
            'check_out' => null,
            'hours_worked' => 0,
            'overtime_hours' => 0,
            'status' => 'incomplete',
        ]);

        Sanctum::actingAs($employee);

        $response = $this->getJson('/api/v1/attendance/today');

        $response->assertOk();
        $response->assertJsonPath('data.mode', 'single');
        $response->assertJsonStructure([
            'data' => [
                'mode',
                'item' => [
                    'employee_id',
                    'name',
                    'checked_in',
                    'check_in_time',
                    'check_out_time',
                    'hours_worked',
                    'status',
                ],
            ],
        ]);
    }

    public function test_attendance_today_collection_payload_matches_mobile_contract(): void
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
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Sami',
            'last_name' => 'Employee',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        AttendanceLog::query()->create([
            'company_id' => $company->id,
            'employee_id' => $employee->id,
            'date' => Carbon::now('UTC')->toDateString(),
            'session_number' => 1,
            'check_in' => Carbon::parse(Carbon::now('UTC')->toDateString() . ' 08:00:00', 'UTC'),
            'check_out' => null,
            'hours_worked' => 0,
            'overtime_hours' => 0,
            'status' => 'incomplete',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/attendance/today');

        $response->assertOk();
        $response->assertJsonPath('data.mode', 'collection');
        $response->assertJsonStructure([
            'data' => [
                'mode',
                'items' => [
                    '*' => [
                        'employee_id',
                        'name',
                        'checked_in',
                        'check_in_time',
                        'check_out_time',
                        'hours_worked',
                        'status',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'per_page',
                    'total',
                ],
            ],
        ]);
    }

    public function test_employees_index_payload_matches_mobile_contract(): void
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

        Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Sami',
            'last_name' => 'Employee',
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        Sanctum::actingAs($manager);

        $response = $this->getJson('/api/v1/employees?per_page=10');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'first_name',
                    'last_name',
                    'email',
                    'role',
                    'status',
                ],
            ],
            'meta' => [
                'current_page',
                'per_page',
                'total',
            ],
        ]);
        $response->assertJsonPath('meta.per_page', 10);
    }
}
