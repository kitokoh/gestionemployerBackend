<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\SalaryAdvanceService;

/**
 * Cas d'usage : resolution d'un litige d'avance par un manager
 * (route `PUT /salary-advances/{id}/resolve-dispute`, PA2-PAY-015) :
 * - `confirmed` → l'avance passe en `employee_confirmed` (paiement exact) ;
 * - `reopened` → retour en `payment_declared` pour correction + re-confirmation.
 *
 * Orchestration pure (ADR-0020, lot 2 avances - #6968). Gardes (manager 403,
 * statut `disputed` 422) au niveau interface (controleur).
 */
class ResolveSalaryAdvanceDispute
{
    public function __construct(
        private readonly SalaryAdvanceService $advances,
    ) {}

    public function execute(SalaryAdvance $advance, Employee $actor, string $resolution, ?string $note = null): SalaryAdvance
    {
        $advance->update([
            'dispute_resolved_at' => now(),
            'dispute_resolved_by' => $actor->id,
            'dispute_resolution_note' => $note,
            'validation_status' => $resolution === 'confirmed' ? 'employee_confirmed' : 'payment_declared',
            'employee_confirmed_at' => $resolution === 'confirmed' ? now() : null,
        ]);
        $advance->refresh();

        $this->advances->notify($advance, 'salary_advance_dispute_resolved');

        return $advance;
    }
}
