<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Application\Actions\ConfirmPaymentItemReception;
use App\Modules\Payroll\Application\Actions\CreatePaymentBatch;
use App\Modules\Payroll\Application\Actions\MarkPaymentBatchPaid;
use App\Modules\Payroll\Domain\Models\PaymentBatch;
use App\Modules\Payroll\Domain\Models\PaymentItem;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentBatchController extends Controller
{
    public function __construct(
        private readonly CreatePaymentBatch $createBatch,
        private readonly ConfirmPaymentItemReception $confirmReception,
        private readonly MarkPaymentBatchPaid $markBatchPaid,
    ) {}

    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $batches = PaymentBatch::query()
            ->where('company_id', $actor->company_id)
            ->withCount('items')
            ->orderByDesc('id')
            ->paginate(max(1, min(50, $request->integer('per_page', 20))));

        return response()->json([
            'data' => $batches->getCollection()->map(fn (PaymentBatch $batch): array => $this->batchPayload($batch))->values(),
            'meta' => [
                'current_page' => $batches->currentPage(),
                'per_page' => $batches->perPage(),
                'total' => $batches->total(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validate([
            'payroll_run_id' => ['required', 'integer', 'exists:payroll_runs,id'],
            'currency' => ['nullable', 'string', 'size:3'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Cas d'usage nommable (ADR-0020, lot 4 paiements #6968) : gardes
        // métier + transaction dans CreatePaymentBatch.
        $currency = isset($validated['currency']) ? (string) $validated['currency'] : null;
        $metadata = is_array($validated['metadata'] ?? null) ? $validated['metadata'] : null;
        $batch = $this->createBatch->execute(
            $actor,
            (int) $validated['payroll_run_id'],
            $currency,
            $metadata,
        );

        return response()->json(['data' => $this->batchPayload($batch, includeItems: true)], 201);
    }

    public function show(Request $request, PaymentBatch $paymentBatch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->ensureBatchCompany($paymentBatch, $actor);

        return response()->json([
            'data' => $this->batchPayload($paymentBatch->load(['items.employee', 'items.paySlip']), includeItems: true),
        ]);
    }

    public function markPaid(Request $request, PaymentBatch $paymentBatch): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->ensureBatchCompany($paymentBatch, $actor);

        // Cas d'usage nommable (ADR-0020, lot 4 paiements #6968) : garde de
        // statut + transaction (batch→paid, items→paid) + documents de
        // paiement + écritures de ledger dans MarkPaymentBatchPaid.
        $batch = $this->markBatchPaid->execute($actor, $paymentBatch);

        return response()->json([
            'data' => $this->batchPayload($batch, includeItems: true),
            'message' => __('errors.PAYMENT_BATCH_CREATED'),
        ], 202);
    }

    public function confirm(Request $request, PaymentItem $paymentItem): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($paymentItem->company_id !== $actor->company_id || $paymentItem->employee_id !== $actor->id) {
            abort(404);
        }

        $validated = $request->validate([
            'device_signature' => ['nullable', 'string', 'max:255'],
            'document_version' => ['nullable', 'string', 'max:40'],
            'metadata' => ['nullable', 'array'],
        ]);

        // Cas d'usage nommable (ADR-0020, lot 4 paiements #6968) : garde
        // d'état + transaction + consentement horodaté (PA2-PAY-016) dans
        // ConfirmPaymentItemReception. Les métadonnées HTTP (ip, user_agent)
        // sont passées en paramètres, l'enveloppe de réponse reste ici.
        $confirmation = $this->confirmReception->execute(
            $paymentItem,
            $actor,
            (string) $request->ip(),
            $request->userAgent() !== null ? substr((string) $request->userAgent(), 0, 500) : null,
            $validated,
        );

        return response()->json([
            'data' => [
                'id' => $confirmation->id,
                'payment_item_id' => $confirmation->payment_item_id,
                'status' => $confirmation->status,
                'confirmed_at' => $confirmation->confirmed_at?->toIso8601String(),
                'document_version' => $confirmation->document_version,
                'document_hash' => $confirmation->document_hash,
            ],
            'message' => __('payroll.payment_reception_confirmed'),
        ]);
    }

    private function ensureBatchCompany(PaymentBatch $batch, Employee $actor): void
    {
        if ($batch->company_id !== $actor->company_id) {
            abort(404);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function batchPayload(PaymentBatch $batch, bool $includeItems = false): array
    {
        $payload = [
            'id' => $batch->id,
            'company_id' => $batch->company_id,
            'payroll_run_id' => $batch->payroll_run_id,
            'period_start' => $batch->period_start?->format('Y-m-d'),
            'period_end' => $batch->period_end?->format('Y-m-d'),
            'status' => $batch->status,
            'total_amount' => $batch->total_amount,
            'currency' => $batch->currency,
            'items_count' => $batch->items_count,
            'marked_paid_at' => $batch->marked_paid_at?->toIso8601String(),
            'confirmed_at' => $batch->confirmed_at?->toIso8601String(),
        ];

        if ($includeItems) {
            $payload['items'] = $batch->items->map(fn (PaymentItem $item): array => [
                'id' => $item->id,
                'employee_id' => $item->employee_id,
                'employee_name' => $item->relationLoaded('employee') && $item->employee
                    ? trim(($item->employee->first_name ?? '').' '.($item->employee->last_name ?? ''))
                    : null,
                'pay_slip_id' => $item->pay_slip_id,
                'amount' => $item->amount,
                'currency' => $item->currency,
                'status' => $item->status,
                'paid_at' => $item->paid_at?->toIso8601String(),
                'confirmed_at' => $item->confirmed_at?->toIso8601String(),
            ])->values();
        }

        return $payload;
    }
}
