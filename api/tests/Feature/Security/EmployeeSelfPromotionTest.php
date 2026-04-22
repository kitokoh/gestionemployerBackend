<?php

namespace Tests\Feature\Security;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class EmployeeSelfPromotionTest extends TestCase
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

    public function test_employee_cannot_promote_self_to_manager(): void
    {
        $company = Company::query()->create([
            'name' => 'Security Corp',
            'slug' => 'security-corp',
            'sector' => 'security',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'sec@corp.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Low',
            'last_name' => 'Privilege',
            'email' => 'low@corp.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $employee->createToken('tests')->plainTextToken;

        // Attempt to promote self to manager
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$employee->id}", [
                'role' => 'manager',
                'manager_role' => 'rh',
            ]);

        // Current behavior might return 200 but ignore the role in EmployeeService,
        // however we want the Request to catch this and reject it.
        // If it returns 200, we check if the role actually changed.

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['role']);
    }

    public function test_manager_cannot_promote_anyone_to_principal(): void
    {
        $company = Company::query()->create([
            'name' => 'Security Corp',
            'slug' => 'security-corp',
            'sector' => 'security',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'sec@corp.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Medium',
            'last_name' => 'Privilege',
            'email' => 'med@corp.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Low',
            'last_name' => 'Privilege',
            'email' => 'low@corp.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $token = $manager->createToken('tests')->plainTextToken;

        // Attempt to promote another employee to principal manager
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$employee->id}", [
                'manager_role' => 'principal',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['manager_role']);
    }

    public function test_manager_cannot_promote_self_to_principal(): void
    {
        $company = Company::query()->create([
            'name' => 'Security Corp',
            'slug' => 'security-corp',
            'sector' => 'security',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'sec@corp.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $manager = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Medium',
            'last_name' => 'Privilege',
            'email' => 'med@corp.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'rh',
            'status' => 'active',
        ]);

        $token = $manager->createToken('tests')->plainTextToken;

        // Attempt to promote self to principal manager
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$manager->id}", [
                'manager_role' => 'principal',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['manager_role']);
    }

    public function test_principal_manager_can_update_self_without_error_on_manager_role(): void
    {
        $company = Company::query()->create([
            'name' => 'Security Corp',
            'slug' => 'security-corp',
            'sector' => 'security',
            'country' => 'DZ',
            'city' => 'Alger',
            'email' => 'sec@corp.test',
            'schema_name' => 'shared_tenants',
            'tenancy_type' => 'shared',
            'status' => 'active',
        ]);

        $principal = Employee::query()->create([
            'company_id' => $company->id,
            'first_name' => 'Top',
            'last_name' => 'Privilege',
            'email' => 'top@corp.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $token = $principal->createToken('tests')->plainTextToken;

        // Principal manager updates self and includes their own manager_role in payload (common frontend behavior)
        $response = $this->withHeader('Authorization', "Bearer {$token}")
            ->patchJson("/api/v1/employees/{$principal->id}", [
                'first_name' => 'UpdatedTop',
                'manager_role' => 'principal',
            ]);

        $response->assertOk();
        $this->assertSame('UpdatedTop', $principal->fresh()->first_name);
    }
}
