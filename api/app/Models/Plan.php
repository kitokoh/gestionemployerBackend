<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'public.plans';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'price_monthly',
        'price_yearly',
        'max_employees',
        'features',
        'trial_days',
        'is_active',
    ];

    protected $casts = [
        'features' => 'array',
        'is_active' => 'boolean',
        'price_monthly' => 'decimal:2',
        'price_yearly' => 'decimal:2',
    ];

    public function hasFeature(string $feature): bool
    {
        return isset($this->features[$feature]) && (bool) $this->features[$feature];
    }

    public function hasUnlimitedEmployees(): bool
    {
        return is_null($this->max_employees);
    }

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'plan_id');
    }
}
