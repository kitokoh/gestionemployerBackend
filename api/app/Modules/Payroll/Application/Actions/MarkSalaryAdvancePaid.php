<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\GeneratePaymentDocumentJob;
use App\Modules\Payroll\Domain\Exceptions\SalaryAdvancePaymentStateException;
use App\Modules\Payroll\Domain\Models\LedgerEntry;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\LedgerService;
use App\Modules\Payroll\Infrastructure\Services\SalaryAdvanceService;

/**
 * Cas d'usage : déclaration de paiement d'une avance par un manager
 * (principal | comptable | rh) — étape du workflow de double validation
 * Plan 60 (route `PUT /salary-advances/{id}/mark-paid`).
 *
 * Orchestration pure et nommable (ADR-0020, lot 2 avances — #6968) :
 * - update conditionnel ATOMIQUE anti-TOCTOU (#3429/#2997) : seule la
 *   première requête sur une avance `manager_approved` écrit ;
 * - audit explicite de la transition (le query builder bypasse les events
 *   modèle — PA2-PAY-001/#4677) ;
 * - création du document de paiement + écriture comptable (ledger) +
 *   notification de l'employé.
 *
 * Les autorisations RBAC et le mapping HTTP (404 vs 422 sur 0 ligne) restent
 * au niveau interface (contrôleur) — l'Action lève
 * `SalaryAdvancePaymentStateException` quand l'update conditionnel échoue.
 *
 * @throws SalaryAdvancePaymentStateException si l'avance n'est plus dans
 *                                            l'état `manager_approved`
 */
class MarkSalaryAdvancePaid
{
    public function __construct(
        private readonly SalaryAdvanceService $advances,
        private readonly LedgerService $ledger,
    ) {}

    /**
     * @param  array{payment_reference?: string|null, payment_note?: string|null}  $data
     */
    public function execute(
        SalaryAdvance $advance,
        Employee $actor,
        array $data,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): SalaryAdvance {
        $oldValues = $advance->only([
            'payment_declared_at', 'payment_declared_by', 'payment_reference',
            'payment_note', 'validation_status', 'status',
        ]);

        $updated = SalaryAdvance::query()
            ->where('id', $advance->id)
            ->where('company_id', $actor->company_id)
            ->where('validation_status', 'manager_approved')
            ->update([
                'payment_declared_at' => now(),
                'payment_declared_by' => $actor->id,
                'payment_reference' => $data['payment_reference'] ?? null,
                'payment_note' => $data['payment_note'] ?? null,
                'validation_status' => 'payment_declared',
                'status' => 'active',
            ]);

        if ($updated === 0) {
            // Soit le statut a changé (déjà déclaré → conflit), soit l'avance
            // n'est plus dans la société de l'acteur (le contrôleur distingue
            // 404 vs 422 en re-vérifiant l'existence).
            throw new SalaryAdvancePaymentStateException(
                'Avance non déclarable : update conditionnel 0 ligne (statut ≠ manager_approved ou ligne absente).'
            );
        }

        $advance->refresh();

        // PA2-PAY-001 — audit explicite, même forme que le trait Auditable.
        AuditLog::create([
            'company_id' => $actor->company_id,
            'user_id' => $actor->id,
            'action' => 'updated',
            'auditable_type' => $advance->getMorphClass(),
            'auditable_id' => $advance->id,
            'old_values' => $oldValues,
            'new_values' => [
                'payment_declared_at' => $advance->payment_declared_at,
                'payment_declared_by' => $advance->payment_declared_by,
                'payment_reference' => $advance->payment_reference,
                'payment_note' => $advance->payment_note,
                'validation_status' => $advance->validation_status,
                'status' => $advance->status,
            ],
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent !== null && $userAgent !== '' ? mb_substr($userAgent, 0, 500) : null,
        ]);

        $document = GeneratePaymentDocumentJob::dispatchForSalaryAdvance($advance, $actor->id);

        /** @var Employee|null $itemEmployee */
        $itemEmployee = $advance->employee ?? Employee::query()->find($advance->employee_id);
        if ($itemEmployee !== null) {
            $this->ledger->record(
                employee: $itemEmployee,
                entryType: LedgerEntry::TYPE_ADVANCE,
                amount: -abs((float) $advance->amount),
                description: 'Salary advance paid: '.($advance->payment_reference ?? 'no reference'),
                source: $advance,
                paymentDocumentId: $document->id,
                createdBy: $actor->id,
            );
        }

        $this->advances->notify($advance, 'salary_advance_payment_declared');

        return $advance;
    }
}
