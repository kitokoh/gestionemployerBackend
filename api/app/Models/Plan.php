<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    protected $table = 'plans';

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

    public function companies(): HasMany
    {
        return $this->hasMany(Company::class, 'plan_id');
    }
}
