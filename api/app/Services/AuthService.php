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
            $lookup = DB::table($this->lookupTable())
                ->where('email', $email)
                ->first();
        }

        /** @var Employee|null $employee */
        $employee = null;

        if ($lookup) {
            $originalPath = DB::selectOne('SHOW search_path')->search_path;
            try {
                DB::statement("SET search_path TO {$lookup->schema_name}, public");

                $employee = Employee::withoutGlobalScopes()
                    ->where('company_id', $lookup->company_id)
                    ->where('id', $lookup->employee_id)
                    ->first();
            } finally {
                DB::statement("SET search_path TO {$originalPath}");
            }
        }

        if (!$employee) {
            // Fallback: try all relevant schemas if lookup failed but we are in public
            // For MVP shared mode, we just try shared_tenants
            $originalPath = DB::selectOne('SHOW search_path')->search_path;
            try {
                DB::statement('SET search_path TO shared_tenants, public');
                $employee = Employee::withoutGlobalScopes()->where('email', $email)->first();
                $employee?->syncUserLookup();
            } finally {
                DB::statement("SET search_path TO {$originalPath}");
            }
        }

        if (!$employee || !Hash::check($password, $employee->password_hash)) {
            throw new InvalidCredentialsException;
        }

        $company = $this->resolveCompany($employee);
        if (!$company) {
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

    private function lookupTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.user_lookups' : 'user_lookups';
    }

    private function lookupTableExists(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable('user_lookups');
        }

        $table = DB::selectOne("select to_regclass('public.user_lookups') as table_name");

        return $table?->table_name !== null;
    }

    private function resolveCompany(Employee $employee): ?Company
    {
        if ($employee->relationLoaded('company') && $employee->company) {
            return $employee->company;
        }

        if (!$employee->company_id) {
            return null;
        }

        if (DB::getDriverName() === 'pgsql') {
            return Company::query()
                ->from('public.companies')
                ->whereKey($employee->company_id)
                ->first();
        }

        return Company::query()->find($employee->company_id);
    }
}
