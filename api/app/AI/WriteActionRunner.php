<?php

declare(strict_types=1);

namespace App\AI;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Planning\Application\Actions\ApproveAbsence;
use App\Modules\Planning\Application\Actions\RejectAbsence;
use App\Modules\Planning\Domain\Exceptions\AbsenceNotPendingException;
use App\Modules\Planning\Domain\Exceptions\InsufficientLeaveBalanceException;
use App\Modules\Planning\Domain\Models\Absence;
use App\Modules\Planning\Domain\Models\AbsenceType;
use Illuminate\Support\Carbon;

class WriteActionRunner
{
    public function __construct(
        private readonly ApproveAbsence $approveAbsence,
        private readonly RejectAbsence $rejectAbsence,
    ) {}

    /**
     * Issue #5625 : liste statique des write tools qui ont un handler PHP.
     *
     * @return list<string>
     */
    public static function supportedWriteToolNames(): array
    {
        return [
            'create_absence',
            'absence_decision',
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function run(string $toolName, array $arguments, string $companyId, int $userId): array
    {
        $handler = $this->writeToolHandlers($companyId, $userId)[$toolName] ?? null;
        if ($handler === null) {
            return ['error' => "Write tool '{$toolName}' is not implemented."];
        }

        return $handler($arguments);
    }

    /**
     * Issue #5625 : source de vérité des write-tools — couplée au test de
     * couverture ToolRegistryCoverageTest (config ai.write_tools ⊆ ici, et
     * chaque outil ici doit être exposé dans ai_tool_registry).
     *
     * B3a (#6856) : `absence_decision` remplace l'ancien `approve_absence`
     * (approbation seule, mise à jour inline) — décision approve OU reject
     * avec motif, exécutée via les cas d'usage canoniques Planning
     * (ApproveAbsence/RejectAbsence, PA2-ARCH-002) : statuts, événements
     * métier, verrou ligne et revalidation du solde (#2666) préservés.
     *
     * @return array<string, callable(array<string, mixed>): array<string, mixed>>
     */
    private function writeToolHandlers(string $companyId, int $userId): array
    {
        return [
            'create_absence' => fn (array $arguments): array => $this->createAbsence($companyId, $userId, $arguments),
            'absence_decision' => fn (array $arguments): array => $this->decideAbsence($companyId, $userId, $arguments),
        ];
    }

    /**
     * Noms des write-tools effectivement exécutables (issue #5625).
     *
     * @return list<string>
     */
    public function supportedWriteTools(): array
    {
        return array_keys($this->writeToolHandlers('', 0));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function createAbsence(string $companyId, int $userId, array $arguments): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $userId)
            ->first();

        if ($actor === null) {
            return ['error' => 'Actor not found'];
        }

        // audit(securite) #6533 : un non-manager ne peut créer une absence que
        // pour LUI-MÊME — l'employee_id passé par le LLM est ignoré. Un
        // manager peut créer pour un employé du même tenant (périmètre
        // AbsencePolicy::create + company_id).
        $employeeId = $actor->isManager()
            ? $this->intArgument($arguments, 'employee_id', $userId)
            : $userId;

        $employee = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $employeeId)
            ->first();

        if ($employee === null) {
            return ['error' => 'Employee not found'];
        }

        $startDate = Carbon::parse($this->stringArgument($arguments, 'start_date', now()->toDateString()));
        $endDate = Carbon::parse($this->stringArgument($arguments, 'end_date', $startDate->toDateString()));
        if ($endDate->lessThan($startDate)) {
            return ['error' => 'end_date must be on or after start_date'];
        }

        $absenceType = $this->resolveAbsenceType($companyId, $arguments);

        $absence = Absence::create([
            'company_id' => $companyId,
            'employee_id' => $employeeId,
            'absence_type_id' => $absenceType?->id,
            'start_date' => $startDate->toDateString(),
            'end_date' => $endDate->toDateString(),
            'days_count' => $startDate->diffInDays($endDate) + 1,
            'status' => 'pending',
            'reason' => array_key_exists('reason', $arguments)
                ? ($this->stringArgument($arguments, 'reason', '') ?: null)
                : null,
        ]);

        return [
            'absence_id' => $absence->id,
            'status' => $absence->status,
            'employee_id' => $employeeId,
        ];
    }

    /**
     * B3a (#6856) — décision sur une demande d'absence (approbation ou rejet
     * avec motif) après confirmation humaine (flux A4). L'exécution passe par
     * les cas d'usage canoniques Planning — jamais de mise à jour inline :
     * transitions d'état, événements métier (AbsenceApproved/AbsenceRejected)
     * et revalidation du solde (#2666) sont garantis par AbsenceService.
     *
     * Défense en profondeur (#6533, parité AbsenceController::approve) : le
     * rôle manager est re-vérifié ici (en plus de la matrice de permissions
     * ToolPermissionPolicy à l'exécution et à la confirmation), et l'absence
     * doit appartenir au tenant de l'acteur (isolation, 404 côté REST).
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function decideAbsence(string $companyId, int $userId, array $arguments): array
    {
        /** @var Employee|null $actor */
        $actor = Employee::query()
            ->where('company_id', $companyId)
            ->where('id', $userId)
            ->first();

        if ($actor === null || ! $actor->isManager()) {
            return ['error' => 'AI_TOOL_PERMISSION_DENIED', 'message' => 'Manager role required to decide absences'];
        }

        $absenceId = $this->intArgument($arguments, 'absence_id', 0);
        if ($absenceId <= 0) {
            return ['error' => 'ABSENCE_ID_REQUIRED'];
        }

        $absence = Absence::query()
            ->where('company_id', $companyId)
            ->where('id', $absenceId)
            ->first();

        if ($absence === null) {
            return ['error' => 'ABSENCE_NOT_FOUND'];
        }

        // Contrat B3a : l'outil décide d'une demande EN ATTENTE uniquement —
        // approbation comme refus. Un statut terminal (approved/rejected/
        // cancelled) est refusé AVANT toute exécution (parité avec la
        // description exposée au LLM ; un refus d'absence déjà approuvée
        // renverserait silencieusement une approbation tierce).
        if ($absence->status !== 'pending') {
            return ['error' => 'ABSENCE_NOT_PENDING'];
        }

        $decision = $this->stringArgument($arguments, 'decision', 'approve');

        try {
            if ($decision === 'approve') {
                $decided = $this->approveAbsence->execute($absence, $actor);
            } elseif ($decision === 'reject') {
                $reason = trim($this->stringArgument($arguments, 'reason', ''));
                if ($reason === '') {
                    return ['error' => 'REJECT_REASON_REQUIRED'];
                }
                if (mb_strlen($reason) > 1000) {
                    return ['error' => 'REJECT_REASON_TOO_LONG'];
                }
                $decided = $this->rejectAbsence->execute($absence, $reason);
            } else {
                return ['error' => 'INVALID_DECISION', 'message' => "decision must be 'approve' or 'reject'"];
            }
        } catch (AbsenceNotPendingException) {
            return ['error' => 'ABSENCE_NOT_PENDING'];
        } catch (InsufficientLeaveBalanceException) {
            return ['error' => 'INSUFFICIENT_LEAVE_BALANCE'];
        }

        $payload = [
            'absence_id' => $decided->id,
            'status' => $decided->status,
            'decision' => $decision,
        ];

        if ($decision === 'reject' && $decided->rejected_reason !== null) {
            $payload['rejected_reason'] = $decided->rejected_reason;
        }

        return $payload;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function resolveAbsenceType(string $companyId, array $arguments): ?AbsenceType
    {
        if (isset($arguments['absence_type_id'])) {
            return AbsenceType::query()
                ->where('company_id', $companyId)
                ->where('id', $this->intArgument($arguments, 'absence_type_id', 0))
                ->first();
        }

        $code = array_key_exists('type', $arguments)
            ? $this->stringArgument($arguments, 'type', '')
            : '';
        if ($code !== '') {
            $byCode = AbsenceType::query()
                ->where('company_id', $companyId)
                ->where('code', $code)
                ->first();
            if ($byCode !== null) {
                return $byCode;
            }
        }

        return AbsenceType::query()->where('company_id', $companyId)->first();
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function intArgument(array $arguments, string $key, int $default): int
    {
        if (! array_key_exists($key, $arguments)) {
            return $default;
        }

        $value = $arguments[$key];

        return is_numeric($value) ? (int) $value : $default;
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function stringArgument(array $arguments, string $key, string $default): string
    {
        if (! array_key_exists($key, $arguments)) {
            return $default;
        }

        $value = $arguments[$key];

        return is_scalar($value) ? (string) $value : $default;
    }
}
