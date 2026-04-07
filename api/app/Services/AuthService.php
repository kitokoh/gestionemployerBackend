<?php

namespace App\Services;

use App\Models\Employee;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

class AuthService
{
    /**
     * @return array{employee: Employee, token: string}
     */
    public function login(string $email, string $password, ?string $deviceName = null): array
    {
        $lookup = null;
        if (Schema::hasTable('user_lookups')) {
            $lookup = DB::table($this->lookupTable())
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
            $employee = Employee::withoutGlobalScopes()->where('email', $email)->first();
            $employee?->syncUserLookup();
        }

        if (! $employee || ! Hash::check($password, $employee->password_hash)) {
            abort(401, 'INVALID_CREDENTIALS');
        }

        $company = $employee->company;
        if (! $company) {
            abort(403, 'COMPANY_NOT_FOUND');
        }

        if (in_array($company->status, ['suspended', 'expired'], true)) {
            abort(403, 'ACCOUNT_SUSPENDED');
        }

        if ($employee->status !== 'active') {
            abort(403, 'EMPLOYEE_NOT_ACTIVE');
        }

        $tokenName = $deviceName ?: 'api';
        $token = $employee->createToken($tokenName)->plainTextToken;

        return [
            'employee' => $employee,
            'token' => $token,
        ];
    }

    private function lookupTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.user_lookups' : 'user_lookups';
    }
}
