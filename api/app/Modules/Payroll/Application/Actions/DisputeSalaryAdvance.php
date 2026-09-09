<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\SalaryAdvanceService;

/**
 * Cas d'usage : ouverture d'un litige par l'employe sur une avance declaree
 * payee (PA2-PAY-015, route `PUT /salary-advances/{id}/dispute`) - le
 * paiement declare ne correspond pas a la realite (montant, remise, destinataire).
 *
 * Orchestration pure (ADR-0020, lot 2 avances - #6968) : passage en
 * `disputed` + notification du manager declarant. Les gardes (propriete 403,
 * statut `payment_declared` 422) restent au niveau interface (controleur).
 */
class DisputeSalaryAdvance
{
    public function __construct(
        private readonly SalaryAdvanceService $advances,
    ) {}

    public function execute(SalaryAdvance $advance, string $reason): SalaryAdvance
    {
        $advance->update([
            'dispute_reason' => $reason,
            'disputed_at' => now(),
            'validation_status' => 'disputed',
        ]);
        $advance->refresh();

        if ($advance->payment_declared_by !== null) {
            $manager = Employee::query()->withoutGlobalScopes()->find($advance->payment_declared_by);
            if ($manager instanceof Employee) {
                $this->advances->notifyRecipient($advance, $manager, 'salary_advance_disputed');
            }
        }

        return $advance;
    }
}
