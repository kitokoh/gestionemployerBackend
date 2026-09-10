<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\SalaryAdvanceService;

/**
 * Cas d'usage : confirmation de reception d'une avance par l'employe
 * (route `PUT /salary-advances/{id}/confirm-received`, Plan 60).
 *
 * Orchestration pure (ADR-0020, lot 2 avances - #6968) : passage en
 * `employee_confirmed` + notification du manager declarant. La garde de
 * propriete (403) et de statut (`payment_declared` requis, 422) reste au
 * niveau interface (controleur).
 */
class ConfirmSalaryAdvanceReceived
{
    public function __construct(
        private readonly SalaryAdvanceService $advances,
    ) {}

    public function execute(SalaryAdvance $advance): SalaryAdvance
    {
        $advance->update([
            'employee_confirmed_at' => now(),
            'validation_status' => 'employee_confirmed',
        ]);
        $advance->refresh();

        if ($advance->payment_declared_by !== null) {
            $manager = Employee::query()->withoutGlobalScopes()->find($advance->payment_declared_by);
            if ($manager instanceof Employee) {
                $this->advances->notifyRecipient($advance, $manager, 'salary_advance_received');
            }
        }

        return $advance;
    }
}
