<?php

namespace App\Services;

use App\Exceptions\AccountSuspendedException;
use App\Exceptions\CompanyNotFoundException;
use App\Exceptions\EmployeeNotActiveException;
use App\Exceptions\InvalidCredentialsException;
use App\Models\Company;
use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthService
{
    /**
     * @return array{employee: Employee, token: string, token_type: string, token_expires_at: ?string}
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $lookup = null;
        if ($this->lookupTableExists()) {
            $lookup = DB::connection('platform')
                ->table('user_lookups')
                ->where('email', $email)
                ->first();
        }

        /** @var Employee|null $employee */
        $employee = null;

        if ($lookup) {
            $employee = Employee::withoutGlobalScopes()
                ->where('company_id', $lookup->company_id)
                ->where('id', $lookup->employee_id)
                ->first();
        }

        if (! $employee) {
            // Fallback: try all relevant schemas if lookup failed.
            // Since Employee doesn't have a fixed connection, it uses the default one
            // which has search_path=shared_tenants,public.
            $employee = Employee::withoutGlobalScopes()
                ->where('email', $email)
                ->first();
            $employee?->syncUserLookup();
        }

        if (! $employee || ! Hash::check($password, $employee->password_hash)) {
            throw new InvalidCredentialsException;
        }

        $company = $this->resolveCompany($employee);
        if (! $company) {
            throw new CompanyNotFoundException;
        }

        if (in_array($company->status, ['suspended', 'expired'], true)) {
            throw new AccountSuspendedException;
        }

        if ($employee->status !== 'active') {
            throw new EmployeeNotActiveException;
        }

        $employee->forceFill(['last_login_at' => now()])->saveQuietly();

        $tokenName = $deviceName ?: 'api';
        $expirationMinutes = (int) config('sanctum.expiration', 0);
        $expiresAt = $expirationMinutes > 0 ? now()->addMinutes($expirationMinutes) : null;
        $tokenResult = $employee->createToken($tokenName, ['*'], $expiresAt);

        return [
            'employee' => $employee,
            'token' => $tokenResult->plainTextToken,
            'token_type' => 'Bearer',
            'token_expires_at' => $expiresAt?->toIso8601String(),
        ];
    }

    private function lookupTableExists(): bool
    {
        return Schema::connection('platform')->hasTable('user_lookups');
    }

    private function resolveCompany(Employee $employee): ?Company
    {
        if ($employee->relationLoaded('company') && $employee->company) {
            return $employee->company;
        }

        if (! $employee->company_id) {
            return null;
        }

        // Company model is ALREADY on the 'platform' connection.
        // So we just use standard Eloquent.
        return Company::query()->find($employee->company_id);
    }
}
