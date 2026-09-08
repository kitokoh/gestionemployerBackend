<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Jobs\GeneratePaymentDocumentJob;
use App\Modules\Payroll\Domain\Models\LedgerEntry;
use App\Modules\Payroll\Domain\Models\PaymentBatch;
use App\Modules\Payroll\Domain\Models\PaymentItem;
use App\Modules\Payroll\Infrastructure\Services\LedgerService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : marquage d'un lot de paiement (batch) comme payé par le
 * manager (lot 4 paiements — #6968, ADR-0020).
 *
 * Orchestration pure et nommable de la transition batch → paid :
 *  - garde de statut : seuls les lots `draft`/`processing` peuvent être
 *    marqués payés (422 sinon — un lot déjà payé/confirmé est figé) ;
 *  - transaction : passage du batch en `paid` (`marked_paid_by`/`marked_paid_at`)
 *    + transition de tous ses PaymentItem vers `paid` (`paid_at`) ;
 *  - après commit, par item payé : génération asynchrone du document de
 *    paiement (`GeneratePaymentDocumentJob` via le bulletin lié, si présent)
 *    et écriture de l'entrée de ledger (`LedgerService`, `TYPE_PAYMENT`).
 *
 * L'autorisation (404 — le manager ne marque que les lots de SA société),
 * la validation de la requête et l'enveloppe de réponse (202) restent au
 * niveau interface (contrôleur).
 *
 * @throws ValidationException si le lot n'est pas `draft`/`processing`
 */
class MarkPaymentBatchPaid
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly LedgerService $ledgerService,
    ) {}

    public function execute(Employee $manager, PaymentBatch $paymentBatch): PaymentBatch
    {
        if (! in_array($paymentBatch->status, [PaymentBatch::STATUS_DRAFT, PaymentBatch::STATUS_PROCESSING], true)) {
            throw ValidationException::withMessages([
                'status' => ['Ce lot de paiement ne peut plus etre marque comme paye.'],
            ]);
        }

        $batch = $this->db->transaction(function () use ($paymentBatch, $manager): ?PaymentBatch {
            $paymentBatch->forceFill([
                'status' => PaymentBatch::STATUS_PAID,
                'marked_paid_by' => $manager->id,
                'marked_paid_at' => now(),
            ])->save();

            PaymentItem::query()
                ->where('payment_batch_id', $paymentBatch->id)
                ->where('company_id', $manager->company_id)
                ->update([
                    'status' => PaymentItem::STATUS_PAID,
                    'paid_at' => now(),
                ]);

            return $paymentBatch->fresh(['items.paySlip', 'items.employee']);
        });

        if (! $batch instanceof PaymentBatch) {
            throw new \RuntimeException('Échec du marquage du lot comme payé.');
        }

        foreach ($batch->items as $item) {
            $document = null;
            if ($item->pay_slip_id !== null && $item->paySlip !== null) {
                $document = GeneratePaymentDocumentJob::dispatchForPaySlip($item->paySlip, $manager->id);
            }

            /** @var Employee|null $itemEmployee */
            $itemEmployee = $item->employee ?? Employee::query()->find($item->employee_id);
            if ($itemEmployee !== null) {
                $this->ledgerService->record(
                    employee: $itemEmployee,
                    entryType: LedgerEntry::TYPE_PAYMENT,
                    amount: abs((float) $item->amount),
                    description: 'Bulk payment batch #'.$batch->id,
                    source: $item,
                    paymentDocumentId: $document?->id,
                    createdBy: $manager->id,
                    currency: $item->currency,
                );
            }
        }

        return $batch;
    }
}
