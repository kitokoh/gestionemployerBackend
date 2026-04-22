<?php

namespace Tests\Feature;

use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\Hash;
use Tests\Support\CreatesMvpSchema;
use Tests\TestCase;

class WebAuthPagesTest extends TestCase
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

    public function test_guest_is_redirected_to_login_for_dashboard(): void
    {
        $response = $this->get('/dashboard');

        $response->assertRedirect('/login');
    }

    public function test_manager_can_login_to_web_dashboard(): void
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

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'manager_role' => 'principal',
            'status' => 'active',
        ]);

        $this->get('/login');
        $token = session()->token();

        $response = $this->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => 'manager@company.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/dashboard');
        $this->assertAuthenticated('web');
    }

    public function test_employee_is_redirected_to_mobile_cta_on_web_login(): void
    {
        // APV v2 / L.02 : un employe simple n'a pas d'interface web.
        // Il est authentifie quand meme (pour pouvoir se deconnecter proprement)
        // mais atterrit sur la page CTA "telecharge l'app mobile".
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

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->get('/login');
        $token = session()->token();

        $response = $this->from('/login')->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => 'employee@company.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/mobile');
        $this->assertAuthenticated('web');
    }

    public function test_legacy_me_urls_redirect_to_mobile_cta_for_employee(): void
    {
        // Les anciens liens /me et /me/... sont rediriges vers /mobile
        // pour ne pas casser les signets existants des employes qui avaient
        // deja utilise le web avant le virage APV v2.
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

        $employee = Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'employee@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'employee',
            'status' => 'active',
        ]);

        $this->actingAs($employee, 'web')->get('/me')->assertRedirect('/mobile');
        $this->actingAs($employee, 'web')->get('/me/anything')->assertRedirect('/mobile');
    }

    public function test_suspended_company_is_blocked_from_web_login(): void
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
            'status' => 'suspended',
        ]);

        Employee::query()->create([
            'company_id' => $company->id,
            'email' => 'manager@company.test',
            'password_hash' => Hash::make('password123'),
            'role' => 'manager',
            'status' => 'active',
        ]);

        $this->get('/login');
        $token = session()->token();

        $response = $this->from('/login')->withSession(['_token' => $token])->post('/login', [
            '_token' => $token,
            'email' => 'manager@company.test',
            'password' => 'password123',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors(['email']);
        $this->assertGuest('web');
    }
}
