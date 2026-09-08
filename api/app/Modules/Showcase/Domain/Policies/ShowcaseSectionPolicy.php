<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Domain\Policies;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Showcase\Domain\Models\ShowcaseSection;

/**
 * RBAC des sections de vitrine tenant (BC-27 SHOWCASE, #6866).
 *
 * Même portée que CompanyShowcasePolicy (#6865) : la gestion (création,
 * édition, suppression, réordonnancement) est réservée au responsable du
 * tenant (sous-rôles `principal`/`rh`) ; la lecture est ouverte aux membres
 * du tenant (scope `company_id`). deny-by-default. Les routes publiques
 * n'empruntent pas cette policy (DTO public dédié, V-PUBLIC-API #6867).
 */
class ShowcaseSectionPolicy
{
    public function viewAny(Employee $actor): bool
    {
        return true;
    }

    public function view(Employee $actor, ShowcaseSection $section): bool
    {
        return $section->company_id === (string) $actor->company_id;
    }

    public function create(Employee $actor): bool
    {
        return $actor->hasManagerRole('principal', 'rh');
    }

    public function update(Employee $actor, ShowcaseSection $section): bool
    {
        return $actor->hasManagerRole('principal', 'rh')
            && $section->company_id === (string) $actor->company_id;
    }

    public function delete(Employee $actor, ShowcaseSection $section): bool
    {
        return $this->update($actor, $section);
    }
}
