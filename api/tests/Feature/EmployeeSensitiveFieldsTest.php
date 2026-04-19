<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EmployeeSensitiveFieldsTest extends TestCase
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

    public function test_manager_can_store_and_read_encrypted_employee_financial_fields(): void
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

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Leila',
            'last_name' => 'Manager',
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $response = $this->actingAs($manager, 'sanctum')->postJson('/api/v1/employees', [
            'first_name' => 'Sami',
            'last_name' => 'Employee',
            'email' => 'employee@company.test',
            'password' => 'password123',
            'role' => 'employee',
            'iban' => 'DZ12345678901234567890',
            'bank_account' => '00123456789',
            'national_id' => 'NID-778899',
        ])->assertCreated();

        $employeeId = $response->json('data.id');

        DB::statement('SET search_path TO shared_tenants,public');
        $raw = DB::table('employees')->where('id', $employeeId)->first(['iban', 'bank_account', 'national_id']);

        $this->assertNotSame('DZ12345678901234567890', $raw->iban);
        $this->assertNotSame('00123456789', $raw->bank_account);
        $this->assertNotSame('NID-778899', $raw->national_id);

        $show = $this->actingAs($manager, 'sanctum')->getJson('/api/v1/employees/'.$employeeId);
        $show->assertOk();
        $show->assertJsonPath('data.iban', 'DZ12345678901234567890');
        $show->assertJsonPath('data.bank_account', '00123456789');
        $show->assertJsonPath('data.national_id', 'NID-778899');
    }
}
