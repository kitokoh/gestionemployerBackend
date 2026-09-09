<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\SalaryAdvanceResource;
use App\Modules\Payroll\Application\Actions\ConfirmSalaryAdvanceReceived;
use App\Modules\Payroll\Application\Actions\DisputeSalaryAdvance;
use App\Modules\Payroll\Application\Actions\ManagerApproveSalaryAdvance;
use App\Modules\Payroll\Application\Actions\MarkSalaryAdvancePaid;
use App\Modules\Payroll\Application\Actions\ResolveSalaryAdvanceDispute;
use App\Modules\Payroll\Domain\Exceptions\SalaryAdvancePaymentStateException;
use App\Modules\Payroll\Domain\Models\SalaryAdvance;
use App\Modules\Payroll\Infrastructure\Services\SalaryAdvanceService;
use App\Modules\Payroll\Interfaces\Api\V1\Requests\DecideSalaryAdvanceRequest;
use App\Modules\Payroll\Interfaces\Api\V1\Requests\DisputeSalaryAdvanceRequest;
use App\Modules\Payroll\Interfaces\Api\V1\Requests\ResolveSalaryAdvanceDisputeRequest;
use App\Modules\Payroll\Interfaces\Api\V1\Requests\SalaryAdvanceIndexRequest;
use App\Modules\Payroll\Interfaces\Api\V1\Requests\StoreSalaryAdvanceRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SalaryAdvanceController extends Controller
{
    public function __construct(
        private readonly SalaryAdvanceService $salaryAdvanceService,
        private readonly MarkSalaryAdvancePaid $markPaidAction,
        private readonly ConfirmSalaryAdvanceReceived $confirmReceivedAction,
        private readonly DisputeSalaryAdvance $disputeAction,
        private readonly ResolveSalaryAdvanceDispute $resolveDisputeAction,
        private readonly ManagerApproveSalaryAdvance $managerApproveAction,
    ) {}

    public function index(SalaryAdvanceIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        $query = SalaryAdvance::query()
            ->where('company_id', $actor->company_id)
            ->with([
                'employee:id,first_name,last_name,email,company_id',
                'employee.company:id,currency',
            ]);

        if (! $actor->isManager()) {
            $query->where('employee_id', $actor->id);
        } elseif ($actor->isTeamScoped()) {
            // Issue #6534 (audit) : un manager dept/superviseur ne voit que
            // les avances de SON equipe (montants, echeanciers) - pattern
            // visibleToManager (PA2-SEC-002/003).
            $query->whereIn('employee_id', Employee::query()->select('id')->visibleToManager($actor));
        } elseif ($request->filled('employee_id')) {
            $query->where('employee_id', $request->integer('employee_id'));
        }

        if ($request->filled('status')) {
            $query->where('status', $request->input('status'));
        }

        $perPage = max(1, min(100, $request->integer('per_page', 15)));

        return SalaryAdvanceResource::collection($query->orderByDesc('created_at')->paginate($perPage))
            ->response();
    }

    public function store(StoreSalaryAdvanceRequest $request): JsonResponse
    {
        $advance = $this->salaryAdvanceService->create($request->user(), $request->validated(), $request->file('proof'));

        return (new SalaryAdvanceResource($advance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))
            ->response()
            ->setStatusCode(201);
    }

    /**
     * PA2-MOB-006: stream the supporting document attached to a salary
     * advance request. Only the requesting employee or a manager of the
     * same company may download it.
     */
    public function downloadProof(Request $request, SalaryAdvance $salaryAdvance): StreamedResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager() && $salaryAdvance->employee_id !== $actor->id) {
            abort(403);
        }

        if (! $salaryAdvance->proof_path) {
            abort(404, 'NO_PROOF_ATTACHED');
        }

        $disk = Storage::disk('local');

        if (! $disk->exists($salaryAdvance->proof_path)) {
            abort(404, 'PROOF_FILE_MISSING');
        }

        return $disk->response($salaryAdvance->proof_path);
    }

    public function show(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($actor->id === $salaryAdvance->employee_id) {
            // self-service conserve.
        } elseif (! $actor->isManager()) {
            abort(403);
        } elseif ($actor->isTeamScoped()) {
            // Issue #6534 : manager team-scoped → uniquement son equipe.
            $target = $salaryAdvance->employee;
            if ($target === null || ! $actor->managesTeamMemberOf($target)) {
                abort(403);
            }
        }

        return (new SalaryAdvanceResource($salaryAdvance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }

    public function approve(DecideSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->approve($salaryAdvance, $actor, $request->validated());

        return (new SalaryAdvanceResource($advance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }

    public function reject(DecideSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->reject($salaryAdvance, $actor, $request->validated('decision_comment'));

        return (new SalaryAdvanceResource($advance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }

    public function destroy(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();
        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($salaryAdvance->employee_id !== $actor->id) {
            abort(403);
        }

        $advance = $this->salaryAdvanceService->cancel($salaryAdvance);

        return (new SalaryAdvanceResource($advance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Plan 60 - Double validation workflow
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * PUT /salary-advances/{id}/mark-paid
     * Manager (principal | comptable | rh) marks an advance as paid.
     * Orchestration dans `MarkSalaryAdvancePaid` (ADR-0020, lot 2 - #6968) :
     * update conditionnel atomique anti-TOCTOU (#3429/#2997), audit explicite
     * (PA2-PAY-001/#4677), document de paiement, ledger, notification.
     */
    public function markPaid(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403, 'MANAGER_REQUIRED');
        }
        if ($salaryAdvance->validation_status !== 'manager_approved') {
            return response()->json(['message' => __('payroll.advance_manager_approve_first')], 422);
        }

        $validated = $request->validate([
            'payment_reference' => 'nullable|string|max:255',
            'payment_note' => 'nullable|string|max:1000',
        ]);

        try {
            $salaryAdvance = $this->markPaidAction->execute(
                $salaryAdvance,
                $actor,
                $validated,
                $request->ip(),
                $request->userAgent(),
            );
        } catch (SalaryAdvancePaymentStateException) {
            // Course perdue (deja declaree) ou ligne supprimee entre-temps :
            // 404 sans fuite d'existence si absente, 422 sinon (même contrat).
            $stillExists = SalaryAdvance::query()
                ->where('id', $salaryAdvance->id)
                ->where('company_id', $actor->company_id)
                ->exists();

            if (! $stillExists) {
                abort(404);
            }

            return response()->json(['message' => __('payroll.advance_manager_approve_first')], 422);
        }

        return (new SalaryAdvanceResource($salaryAdvance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }

    /**
     * PUT /salary-advances/{id}/confirm-received
     * Employee confirms they received the advance.
     * Orchestration dans `ConfirmSalaryAdvanceReceived` (ADR-0020, lot 2 - #6968).
     */
    public function confirmReceived(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($salaryAdvance->employee_id !== $actor->id) {
            abort(403, 'ADVANCE_CONFIRM_FORBIDDEN');
        }
        if ($salaryAdvance->validation_status !== 'payment_declared') {
            return response()->json(['message' => __('payroll.payment_declared_before_confirm')], 422);
        }

        $salaryAdvance = $this->confirmReceivedAction->execute($salaryAdvance);

        return (new SalaryAdvanceResource($salaryAdvance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }

    /**
     * PUT /salary-advances/{id}/dispute
     * Employee opens a dispute instead of confirming reception (PA2-PAY-015).
     * Orchestration dans `DisputeSalaryAdvance` (ADR-0020, lot 2 - #6968).
     */
    public function dispute(DisputeSalaryAdvanceRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if ($salaryAdvance->employee_id !== $actor->id) {
            abort(403, 'ADVANCE_DISPUTE_FORBIDDEN');
        }
        if ($salaryAdvance->validation_status !== 'payment_declared') {
            return response()->json(['message' => __('payroll.payment_declared_before_dispute')], 422);
        }

        $salaryAdvance = $this->disputeAction->execute($salaryAdvance, (string) $request->validated('dispute_reason'));

        return (new SalaryAdvanceResource($salaryAdvance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }

    /**
     * PUT /salary-advances/{id}/resolve-dispute
     * Manager (principal | comptable | rh) resolves a dispute: `confirmed`
     * (→ employee_confirmed) or `reopened` (→ payment_declared).
     * Orchestration dans `ResolveSalaryAdvanceDispute` (ADR-0020, lot 2 - #6968).
     */
    public function resolveDispute(ResolveSalaryAdvanceDisputeRequest $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403, 'MANAGER_REQUIRED');
        }
        if ($salaryAdvance->validation_status !== 'disputed') {
            return response()->json(['message' => __('payroll.advance_not_disputed')], 422);
        }

        $salaryAdvance = $this->resolveDisputeAction->execute(
            $salaryAdvance,
            $actor,
            (string) $request->validated('resolution'),
            $request->validated('dispute_resolution_note'),
        );

        return (new SalaryAdvanceResource($salaryAdvance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }

    /**
     * Alias kept for backward compatibility with Plan 60 naming.
     * PUT /salary-advances/{id}/manager-approve
     * Sets validation_status to manager_approved after existing approve flow.
     * Orchestration dans `ManagerApproveSalaryAdvance` (ADR-0020, lot 2 - #6968).
     */
    public function managerApprove(Request $request, SalaryAdvance $salaryAdvance): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if ($salaryAdvance->company_id !== $actor->company_id) {
            abort(404);
        }
        if (! $actor->isManager()) {
            abort(403, 'MANAGER_REQUIRED');
        }
        if (! in_array($salaryAdvance->status, ['pending', 'approved'], true)) {
            return response()->json(['message' => __('payroll.only_pending_advances_approvable')], 422);
        }

        $salaryAdvance = $this->managerApproveAction->execute($salaryAdvance, $actor);

        return (new SalaryAdvanceResource($salaryAdvance->load(['employee:id,first_name,last_name,email,company_id', 'employee.company:id,currency'])))->response();
    }
}
