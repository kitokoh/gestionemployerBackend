<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class Company extends Model
{
    use HasFactory;
    use HasUuids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'name',
        'slug',
        'sector',
        'country',
        'city',
        'address',
        'email',
        'phone',
        'plan_id',
        'schema_name',
        'tenancy_type',
        'status',
        'subscription_start',
        'subscription_end',
        'language',
        'timezone',
        'currency',
        'notes',
    ];

    protected static function booted(): void
    {
        static::saved(function (self $company): void {
            if (! $company->wasChanged('status')) {
                return;
            }

            $originalPath = DB::selectOne('SHOW search_path')->search_path;

            try {
                if ($company->tenancy_type === 'shared') {
                    DB::statement('SET search_path TO shared_tenants, public');
                } else {
                    DB::statement("SET search_path TO {$company->schema_name}, public");
                }

                Employee::withoutGlobalScopes()
                    ->where('company_id', $company->id)
                    ->get()
                    ->each(fn (Employee $employee) => $employee->tokens()->delete());
            } finally {
                DB::statement("SET search_path TO {$originalPath}");
            }
        });
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class, 'company_id');
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class, 'plan_id');
    }

    public function biometricRequests(): HasMany
    {
        return $this->hasMany(BiometricEnrollmentRequest::class, 'company_id');
    }

    public function attendanceKiosks(): HasMany
    {
        return $this->hasMany(AttendanceKiosk::class, 'company_id');
    }

    /**
     * Plan Enforcement Helpers
     */
    public function canAddMoreEmployees(): bool
    {
        if (! $this->plan || $this->plan->hasUnlimitedEmployees()) {
            return true;
        }

        // Count non-archived employees
        $count = $this->employees()->where('status', '!=', 'archived')->count();

        return $count < $this->plan->max_employees;
    }

    public function isFeatureEnabled(string $feature): bool
    {
        return $this->plan?->hasFeature($feature) ?? false;
    }
}
