<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Models\PaymentBatch;
use App\Modules\Payroll\Domain\Models\PaymentConfirmation;
use App\Modules\Payroll\Domain\Models\PaymentItem;
use App\Modules\Payroll\Infrastructure\Services\PaymentConsentSignatureService;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Validation\ValidationException;

/**
 * Cas d'usage : confirmation de réception d'un paiement par l'employé
 * destinataire (lot 4 paiements — #6968, ADR-0020).
 *
 * Orchestration pure et nommable de la transaction métier :
 *  - garde d'état : un PaymentItem encore `pending` n'est pas confirmable
 *    (le manager doit d'abord déclarer/marquer le paiement) → 422 ;
 *  - création IDEMPOTENTE de la PaymentConfirmation (un item ne peut être
 *    confirmé qu'une fois — un second appel renvoie la confirmation existante) ;
 *  - signature de consentement horodatée PA2-PAY-016 : le hash est calculé
 *    sur le `confirmed_at` RÉELLEMENT persisté (refresh) via
 *    PaymentConsentSignatureService, jamais sur la valeur non arrondie ;
 *  - transition du PaymentItem vers `confirmed` + rafraîchissement du statut
 *    agrégé du batch (confirmed / partially_confirmed).
 *
 * L'autorisation d'accès (404 company/employee — un employé ne confirme que
 * SES paiements), la validation de la requête et l'enveloppe de réponse
 * restent au niveau interface (contrôleur).
 *
 * @param  array{device_signature?: string|null, document_version?: string|null, metadata?: array<mixed>|null}  $validated
 *
 * @throws ValidationException si le paiement est encore `pending`
 */
class ConfirmPaymentItemReception
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly PaymentConsentSignatureService $consentSignatureService,
    ) {}

    public function execute(
        PaymentItem $paymentItem,
        Employee $employee,
        string $ipAddress,
        ?string $userAgent,
        array $validated,
    ): PaymentConfirmation {
        if ($paymentItem->status === PaymentItem::STATUS_PENDING) {
            throw ValidationException::withMessages([
                'status' => ['Le paiement doit etre declare par le manager avant confirmation.'],
            ]);
        }

        return $this->db->transaction(function () use ($paymentItem, $employee, $ipAddress, $userAgent, $validated): PaymentConfirmation {
            $existing = PaymentConfirmation::query()
                ->where('payment_item_id', $paymentItem->id)
                ->first();

            if ($existing !== null) {
                return $existing;
            }

            $confirmedAt = now();
            $documentVersion = (string) ($validated['document_version'] ?? 'v1');

            $confirmation = PaymentConfirmation::query()->create([
                'company_id' => $employee->company_id,
                'payment_batch_id' => $paymentItem->payment_batch_id,
                'payment_item_id' => $paymentItem->id,
                'employee_id' => $employee->id,
                'status' => 'confirmed',
                'confirmed_at' => $confirmedAt,
                'device_signature' => $validated['device_signature'] ?? null,
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent,
                'document_version' => $documentVersion,
                'metadata' => $validated['metadata'] ?? null,
            ]);

            // PA2-PAY-016 - Timestamped consent hash binding this confirmation
            // to the payment item, amount, currency and instant, without a
            // premature PKI/certificate stack.
            //
            // Le hash est calculé sur la valeur `confirmed_at` RÉELLEMENT
            // persistée (la colonne est timestamp(0) : PostgreSQL arrondit à la
            // seconde — hasher `now()` avec les millisecondes rendrait la
            // signature invérifiable après lecture, car le payload différerait).
            // `refresh()` recharge la valeur arrondie par le serveur.
            $confirmation->refresh();
            $documentHash = $this->consentSignatureService->hash(
                $this->consentSignatureService->buildPayload(
                    $paymentItem,
                    $confirmation->confirmed_at,
                    $documentVersion,
                ),
            );
            $confirmation->forceFill(['document_hash' => $documentHash])->save();

            $paymentItem->forceFill([
                'status' => PaymentItem::STATUS_CONFIRMED,
                'confirmed_at' => $confirmation->confirmed_at,
            ])->save();

            $this->refreshBatchConfirmationStatus($paymentItem->batch);

            return $confirmation;
        });
    }

    private function refreshBatchConfirmationStatus(PaymentBatch $batch): void
    {
        $total = $batch->items()->count();
        $confirmed = $batch->items()->where('status', PaymentItem::STATUS_CONFIRMED)->count();

        if ($total > 0 && $confirmed === $total) {
            $batch->forceFill([
                'status' => PaymentBatch::STATUS_CONFIRMED,
                'confirmed_at' => now(),
            ])->save();

            return;
        }

        if ($confirmed > 0) {
            $batch->forceFill(['status' => PaymentBatch::STATUS_PARTIALLY_CONFIRMED])->save();
        }
    }
}
