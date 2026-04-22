<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Services\EstimationService;
use Illuminate\Contracts\View\View;

class DashboardController extends Controller
{
    public function __construct(private readonly EstimationService $estimationService) {}

    public function index(): View
    {
        $company = app('current_company');
        $today = now('UTC')->setTimezone($company->timezone)->toDateString();

        $employeesTotal = Employee::query()->count();

        $employees = Employee::query()
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->paginate(25)
            ->withQueryString();

        $pageEmployeeIds = $employees->getCollection()->pluck('id');

        $present = AttendanceLog::query()
            ->where('date', $today)
            ->where('session_number', 1)
            ->where('status', '!=', 'absent')
            ->count();

        $late = AttendanceLog::query()
            ->where('date', $today)
            ->where('session_number', 1)
            ->where('status', 'late')
            ->count();

        $logsByEmployee = AttendanceLog::query()
            ->where('date', $today)
            ->where('session_number', 1)
            ->whereIn('employee_id', $pageEmployeeIds)
            ->get()
            ->keyBy('employee_id');

        $rows = [];
        $totalEstimated = 0.0;

        foreach ($employees as $employee) {
            $log = $logsByEmployee->get($employee->id);
            $attendanceStatus = $log?->status ?? 'absent';

            $summary = $this->estimationService->dailySummaryFromLog($employee, $log, $today);
            $totalEstimated += (float) $summary['total_estimated'];

            $rows[] = [
                'employee' => $employee,
                'attendance_status' => $attendanceStatus,
                'check_in' => $log?->check_in?->setTimezone($company->timezone)->format('H:i'),
                'check_out' => $log?->check_out?->setTimezone($company->timezone)->format('H:i'),
                'hours' => $summary['hours_worked'] ?? 0.0,
                'due' => $summary['total_estimated'] ?? 0.0,
                'currency' => $summary['currency'] ?? $company->currency,
            ];
        }

        return view('dashboard', [
            'company' => $company,
            'today' => $today,
            'employeesTotal' => $employeesTotal,
            'presentCount' => $present,
            'lateCount' => $late,
            'totalEstimated' => round($totalEstimated, 2),
            'currency' => $company->currency,
            'rows' => $rows,
            'employees' => $employees,
        ]);
    }
}
