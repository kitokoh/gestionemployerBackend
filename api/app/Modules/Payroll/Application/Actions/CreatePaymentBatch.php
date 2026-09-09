<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PaymentBatch;
use App\Modules\Payroll\Domain\Models\PaymentItem;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Domain\Models\PaySlip;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : creation d'un lot de paiement (batch) à partir d'un cycle de
 * paie calcule/valide/paye (lot 4 paiements - #6968, ADR-0020).
 *
 * Orchestration pure et nommable de la creation :
 *  - resolution du PayrollRun, bornee au tenant du manager (404 si absent
 *    ou hors societe - ModelNotFoundException) ;
 *  - garde de statut : seuls les runs `calculated`/`validated`/`paid` sont
 *    batchables (422 PAYMENT_BATCH_RUN_INVALID) ;
 *  - collecte des bulletins payables du run (calculated/validated/sent) -
 *    lot vide refuse (422) ;
 *  - transaction : creation du batch (draft) + un PaymentItem `pending` par
 *    bulletin payable.
 *
 * L'autorisation (manager), la validation de la requête et l'enveloppe de
 * reponse (201) restent au niveau interface (controleur).
 *
 * @param  array<mixed>|null  $metadata
 *
 * @throws ModelNotFoundException si le run n'existe pas / n'appartient pas au tenant
 * @throws ValidationException si le run n'est pas batchable ou sans bulletin payable
 */
class CreatePaymentBatch
{
    public function __construct(
        private readonly ConnectionInterface $db,
    ) {}

    /**
     * @param  array<string, mixed>|null  $metadata
     */
    public function execute(
        Employee $manager,
        int $payrollRunId,
        ?string $currency = null,
        ?array $metadata = null,
    ): PaymentBatch {
        $run = PayrollRun::query()
            ->where('company_id', $manager->company_id)
            ->findOrFail($payrollRunId);

        if (! in_array($run->status, ['calculated', 'validated', 'paid'], true)) {
            throw ValidationException::withMessages([
                'payroll_run_id' => [__('errors.PAYMENT_BATCH_RUN_INVALID')],
            ]);
        }

        $slips = PaySlip::query()
            ->where('company_id', $manager->company_id)
            ->where('payroll_run_id', $run->id)
            ->whereIn('status', ['calculated', 'validated', 'sent'])
            ->get();

        if ($slips->isEmpty()) {
            throw ValidationException::withMessages([
                'payroll_run_id' => ['Aucun bulletin payable trouve pour ce cycle.'],
            ]);
        }

        $resolvedCurrency = strtoupper((string) ($currency ?? currentCompany()->currency ?? 'DZD'));

        $batch = $this->db->transaction(function () use ($manager, $run, $slips, $resolvedCurrency, $metadata): ?PaymentBatch {
            $batch = PaymentBatch::query()->create([
                'company_id' => $manager->company_id,
                'payroll_run_id' => $run->id,
                'period_start' => $run->period_start,
                'period_end' => $run->period_end,
                'status' => PaymentBatch::STATUS_DRAFT,
                'total_amount' => $slips->sum('net_salary'),
                'currency' => $resolvedCurrency,
                'items_count' => $slips->count(),
                'created_by' => $manager->id,
                'metadata' => $metadata,
            ]);

            foreach ($slips as $slip) {
                PaymentItem::query()->create([
                    'company_id' => $manager->company_id,
                    'payment_batch_id' => $batch->id,
                    'employee_id' => $slip->employee_id,
                    'pay_slip_id' => $slip->id,
                    'amount' => $slip->net_salary,
                    'currency' => $resolvedCurrency,
                    'status' => PaymentItem::STATUS_PENDING,
                ]);
            }

            return $batch->fresh(['items.employee']);
        });

        if (! $batch instanceof PaymentBatch) {
            throw new \RuntimeException('Echec de la creation du lot de paiement.');
        }

        return $batch;
    }
}
