<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\CatalogQuoteStatus;
use App\Modules\Catalog\Domain\Models\CatalogQuote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Back-office des demandes de devis B2B (BC-28 CATALOG, #6885 — C-BACKOFFICE).
 *
 * Accès réservé au responsable du tenant (`principal`/`rh`,
 * CatalogQuotePolicy — données acheteur RGPD). Isolation : toute demande
 * d'un autre tenant répond 404. Workflow de statuts borné par
 * `CatalogQuoteStatus::allowedTransitions()` (422 hors transitions).
 * Export CSV des demandes (pattern d'export du repo).
 */
class CatalogQuoteBackofficeController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', CatalogQuote::class);

        $query = CatalogQuote::query()->where('company_id', $actor->company_id);

        if ($request->filled('status')) {
            $status = $request->input('status');
            $known = array_map(
                static fn (CatalogQuoteStatus $s): string => $s->value,
                CatalogQuoteStatus::cases()
            );
            abort_if(! in_array($status, $known, true), 422);
            $query->where('status', $status);
        }

        if ($request->filled('q')) {
            $q = (string) $request->input('q');
            $query->where(function ($builder) use ($q): void {
                $builder->where('buyer_company', 'ilike', '%'.$q.'%')
                    ->orWhere('contact_name', 'ilike', '%'.$q.'%')
                    ->orWhere('email', 'ilike', '%'.$q.'%')
                    ->orWhere('reference', 'ilike', '%'.$q.'%');
            });
        }

        $quotes = $query
            ->orderByDesc('created_at')
            ->paginate(max(1, min(100, $request->integer('per_page', 15))));

        return response()->json([
            'data' => collect($quotes->items())
                ->map(fn (CatalogQuote $quote): array => $this->payload($quote)),
            'meta' => [
                'current_page' => $quotes->currentPage(),
                'last_page' => $quotes->lastPage(),
                'total' => $quotes->total(),
            ],
        ]);
    }

    /**
     * Mise à jour back-office : transition de statut (workflow borné) et/ou
     * notes internes. Corps : `{ status?: string, internal_notes?: string }`.
     */
    public function update(Request $request, CatalogQuote $quote): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($quote->company_id !== (string) $actor->company_id) {
            abort(404);
        }

        $this->authorize('update', $quote);

        $data = $request->validate([
            'status' => ['nullable', 'string'],
            'internal_notes' => ['nullable', 'string', 'max:10000'],
        ]);

        if (isset($data['status'])) {
            $target = CatalogQuoteStatus::tryFrom((string) $data['status']);
            if ($target === null) {
                throw ValidationException::withMessages(['status' => 'Statut inconnu.']);
            }
            if (! $quote->status->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'status' => sprintf('Transition %s → %s non autorisée.', $quote->status->value, $target->value),
                ]);
            }
            $quote->status = $target;
        }

        if (array_key_exists('internal_notes', $data)) {
            $quote->internal_notes = $data['internal_notes'] !== null ? (string) $data['internal_notes'] : null;
        }

        $quote->save();

        return response()->json(['data' => $this->payload($quote->refresh())]);
    }

    /**
     * Export CSV des demandes du tenant (responsable uniquement).
     */
    public function export(Request $request): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $this->authorize('viewAny', CatalogQuote::class);

        $quotes = CatalogQuote::query()
            ->where('company_id', $actor->company_id)
            ->orderByDesc('created_at')
            ->limit(10000)
            ->get();

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="catalog-quotes-'.now()->format('Ymd-His').'.csv"',
        ];

        $callback = function () use ($quotes): void {
            $out = fopen('php://output', 'wb');
            if ($out === false) {
                return;
            }
            fputcsv($out, [
                'reference', 'created_at', 'status', 'product_name', 'product_slug',
                'quantity', 'buyer_company', 'contact_name', 'email', 'phone',
                'message', 'internal_notes', 'consented_at',
            ]);
            foreach ($quotes as $quote) {
                /** @var CatalogQuote $quote */
                fputcsv($out, [
                    $quote->reference,
                    $quote->created_at?->toIso8601String(),
                    $quote->status->value,
                    $quote->product_name,
                    $quote->product_slug,
                    $quote->quantity,
                    $quote->buyer_company,
                    $quote->contact_name,
                    $quote->email,
                    $quote->phone,
                    $quote->message,
                    $quote->internal_notes,
                    $quote->consented_at?->toIso8601String(),
                ]);
            }
            fclose($out);
        };

        return response()->stream($callback, 200, $headers);
    }

    /**
     * @return array<string, mixed>
     */
    private function payload(CatalogQuote $quote): array
    {
        return [
            'id' => $quote->id,
            'reference' => $quote->reference,
            'status' => $quote->status->value,
            'product' => [
                'slug' => $quote->product_slug,
                'name' => $quote->product_name,
            ],
            'quantity' => $quote->quantity,
            'buyer' => [
                'company' => $quote->buyer_company,
                'contact_name' => $quote->contact_name,
                'email' => $quote->email,
                'phone' => $quote->phone,
            ],
            'message' => $quote->message,
            'internal_notes' => $quote->internal_notes,
            'consented_at' => $quote->consented_at?->toIso8601String(),
            'created_at' => $quote->created_at?->toIso8601String(),
        ];
    }
}
