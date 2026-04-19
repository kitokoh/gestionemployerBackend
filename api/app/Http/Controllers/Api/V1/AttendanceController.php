<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Attendance\AttendanceIndexRequest;
use App\Http\Requests\Api\V1\Attendance\AttendanceTodayRequest;
use App\Http\Requests\Api\V1\Attendance\CheckInRequest;
use App\Http\Requests\Api\V1\Attendance\CheckOutRequest;
use App\Http\Requests\Api\V1\Attendance\UpdateAttendanceRequest;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\AttendanceService;
use App\Services\AuditLogger;
use App\Services\EstimationService;
use Illuminate\Http\JsonResponse;

class AttendanceController extends Controller
{
    public function __construct(
        private readonly AttendanceService $attendanceService,
        private readonly EstimationService $estimationService,
    ) {}

    public function checkIn(CheckInRequest $request): JsonResponse
    {
        $this->authorize('checkIn', AttendanceLog::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $log = $this->attendanceService->checkIn(
            employee: $employee,
            gpsLat: $request->validated('gps_lat'),
            gpsLng: $request->validated('gps_lng'),
        );

        return new JsonResponse([
            'data' => $this->serializeLog($log),
        ], 201);
    }

    public function checkOut(CheckOutRequest $request): JsonResponse
    {
        $this->authorize('checkOut', AttendanceLog::class);

        /** @var Employee $employee */
        $employee = $request->user();

        $log = $this->attendanceService->checkOut(
            employee: $employee,
            gpsLat: $request->validated('gps_lat'),
            gpsLng: $request->validated('gps_lng'),
        );

        return new JsonResponse([
            'data' => $this->serializeLog($log),
        ]);
    }

    public function today(AttendanceTodayRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $company = app('current_company');
        $today = now('UTC')->setTimezone($company->timezone)->toDateString();

        $employeeId = $request->validated('employee_id');

        if ($employeeId) {
            $target = Employee::query()->findOrFail($employeeId);
            $this->authorize('viewForEmployee', [AttendanceLog::class, $target]);

            $log = AttendanceLog::query()
                ->where('employee_id', $target->id)
                ->where('date', $today)
                ->where('session_number', 1)
                ->orderByDesc('id')
                ->first();

            return new JsonResponse([
                'data' => [
                    'mode' => 'single',
                    'item' => $this->serializeToday($target, $log),
                ],
            ]);
        }

        $this->authorize('viewForEmployee', [AttendanceLog::class, $actor]);

        $log = AttendanceLog::query()
            ->where('employee_id', $actor->id)
            ->where('date', $today)
            ->where('session_number', 1)
            ->orderByDesc('id')
            ->first();

        return new JsonResponse([
            'data' => [
                'mode' => 'single',
                'item' => $this->serializeToday($actor, $log),
            ],
        ]);
    }

    public function teamOverview(AttendanceTodayRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $this->authorize('viewAny', AttendanceLog::class);

        $company = app('current_company');
        $today = now('UTC')->setTimezone($company->timezone)->toDateString();

        return new JsonResponse([
            'data' => $this->buildTeamOverviewContext($request, $today),
        ]);
    }

    public function index(AttendanceIndexRequest $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        $validated = $request->validated();

        $employeeId = $validated['employee_id'] ?? null;
        if ($employeeId) {
            $target = Employee::query()->findOrFail($employeeId);
            $this->authorize('viewForEmployee', [AttendanceLog::class, $target]);
        } else {
            $target = $actor;
            $this->authorize('viewForEmployee', [AttendanceLog::class, $actor]);
        }

        $query = AttendanceLog::query()
            ->orderByDesc('date')
            ->orderByDesc('id');

        if ($target) {
            $query->where('employee_id', $target->id);
        }

        if (! empty($validated['date_from'])) {
            $query->where('date', '>=', $validated['date_from']);
        }
        if (! empty($validated['date_to'])) {
            $query->where('date', '<=', $validated['date_to']);
        }

        $perPage = $validated['per_page'] ?? 20;

        $paginator = $query->paginate($perPage);
        $data = collect($paginator->items())->map(fn (AttendanceLog $log) => $this->serializeLog($log))->values();

        return new JsonResponse([
            'data' => $data,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ]);
    }

    public function update(UpdateAttendanceRequest $request, AttendanceLog $attendance): JsonResponse
    {
        $this->authorize('update', $attendance);

        /** @var Employee $actor */
        $actor = $request->user();

        $log = $this->attendanceService->correct($attendance, $request->validated(), $actor);

        AuditLogger::log(
            actorType: 'employee',
            actorId: $actor->id,
            companyId: (string) $actor->company_id,
            action: 'attendance.corrected',
            request: $request,
            metadata: [
                'attendance_id' => $log->id,
                'target_employee_id' => $log->employee_id,
                'status' => $log->status,
            ],
        );

        return new JsonResponse([
            'data' => $this->serializeLog($log),
        ]);
    }

    private function serializeLog(AttendanceLog $log): array
    {
        return [
            'id' => $log->id,
            'employee_id' => $log->employee_id,
            'date' => $log->date?->format('Y-m-d'),
            'check_in' => optional($log->check_in)->toIso8601String(),
            'check_out' => optional($log->check_out)->toIso8601String(),
            'method' => $log->method,
            'source_device_code' => $log->source_device_code,
            'hours_worked' => $log->hours_worked,
            'overtime_hours' => $log->overtime_hours,
            'status' => $log->status,
            'late_minutes' => $log->late_minutes,
            'corrected_by' => $log->corrected_by,
            'correction_note' => $log->correction_note,
        ];
    }

    private function serializeToday(Employee $employee, ?AttendanceLog $log): array
    {
        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'checked_in' => (bool) $log?->check_in,
            'check_in_time' => $log?->check_in?->setTimezone(app('current_company')->timezone)->format('H:i'),
            'check_out_time' => $log?->check_out?->setTimezone(app('current_company')->timezone)->format('H:i'),
            'hours_worked' => $log?->hours_worked ?? '0.00',
            'status' => $log?->status ?? 'absent',
        ];
    }

    private function buildTeamOverviewContext(AttendanceTodayRequest $request, string $today): array
    {
        $perPage = $request->integer('per_page', 50);

        $paginator = Employee::query()
            ->select([
                'id',
                'first_name',
                'last_name',
                'email',
                'role',
                'manager_role',
                'status',
                'salary_type',
                'salary_base',
                'hourly_rate',
            ])
            ->orderBy('id')
            ->paginate(max(1, min(100, $perPage)));

        $employees = collect($paginator->items());
        $employeeIds = $employees->pluck('id')->all();

        $logsByEmployee = AttendanceLog::query()
            ->where('date', $today)
            ->where('session_number', 1)
            ->whereIn('employee_id', $employeeIds)
            ->get()
            ->keyBy('employee_id');

        $items = $employees->map(function (Employee $employee) use ($logsByEmployee, $today) {
            return $this->serializeTeamOverviewItem($employee, $logsByEmployee->get($employee->id), $today);
        })->values();

        return [
            'mode' => 'collection',
            'items' => $items,
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
            ],
        ];
    }

    private function serializeTeamOverviewItem(Employee $employee, ?AttendanceLog $log, string $today): array
    {
        $summary = $this->estimationService->dailySummaryFromLog($employee, $log, $today);

        return [
            'employee_id' => $employee->id,
            'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
            'role' => $employee->role,
            'manager_role' => $employee->manager_role,
            'checked_in' => (bool) $log?->check_in,
            'check_in_time' => $log?->check_in?->setTimezone(app('current_company')->timezone)->format('H:i'),
            'check_out_time' => $log?->check_out?->setTimezone(app('current_company')->timezone)->format('H:i'),
            'hours_worked' => $summary['hours_worked'],
            'overtime_hours' => $summary['overtime_hours'],
            'estimated_gain' => $summary['total_estimated'],
            'currency' => $summary['currency'],
            'status' => $log?->status ?? 'absent',
        ];
    }
}
