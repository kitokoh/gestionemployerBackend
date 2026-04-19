<?php

namespace App\Services;

use App\Models\Company;
use Symfony\Component\HttpKernel\Exception\HttpException;

class PlanFeatureGate
{
    public static function check(Company $company, string $feature): void
    {
        $plan = $company->relationLoaded('plan') ? $company->plan : $company->plan()->first();
        $features = (array) ($plan?->features ?? []);

        if (! array_key_exists($feature, $features) || ! $features[$feature]) {
            throw new HttpException(403, 'FEATURE_NOT_AVAILABLE');
        }
    }
}
