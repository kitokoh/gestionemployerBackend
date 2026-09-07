<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\SalaryAdvanceService;

/**
 * Cas d'usage : seconde approbation « manager » d'une avance (Plan 60,
 * route `PUT /salary-advances/{id}/manager-approve`) — passage en
 * `manager_approved` / `approved` après le flux d'approbation existant.
 *
 * Orchestration pure (ADR-0020, lot 2 avances — #6968). `status` n'est pas
 * mass-assignable (#4677/#3597) : assignation explicite via `forceFill`,
 * pattern `SalaryAdvanceService::create`. Gardes (manager 403, statut
 * pending/approved 422) au niveau interface (contrôleur).
 */
class ManagerApproveSalaryAdvance
{
    public function __construct(
        private readonly SalaryAdvanceService $advances,
    ) {}

    public function execute(SalaryAdvance $advance, Employee $actor): SalaryAdvance
    {
        $advance->forceFill([
            'manager_approved_at' => now(),
            'manager_approved_by' => $actor->id,
            'validation_status' => 'manager_approved',
            'status' => 'approved',
        ])->save();
        $advance->refresh();

        $this->advances->notify($advance, 'salary_advance_manager_approved');

        return $advance;
    }
}
