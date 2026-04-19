<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Estimation\DailySummaryRequest;
use App\Http\Requests\Api\V1\Estimation\QuickEstimateRequest;
use App\Http\Requests\Api\V1\Estimation\ReceiptRequest;
use App\Models\Employee;
use App\Services\EstimationService;
use App\Services\PlanFeatureGate;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EstimationController extends Controller
{
    public function __construct(private readonly EstimationService $estimationService) {}

    public function dailySummary(DailySummaryRequest $request, string $employeeId): JsonResponse
    {
        $employee = Employee::query()->findOrFail($employeeId);
        $this->authorize('view', $employee);

        $summary = $this->estimationService->dailySummary(
            employee: $employee,
            date: $request->validated('date')
        );

        return new JsonResponse(['data' => $summary]);
    }

    public function quickEstimate(QuickEstimateRequest $request, string $employeeId): JsonResponse
    {
        $this->authorize('viewAny', Employee::class);

        $employee = Employee::query()->findOrFail($employeeId);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $request->validated('from'),
            to: $request->validated('to')
        );

        return new JsonResponse(['data' => $estimate]);
    }

    public function receipt(ReceiptRequest $request, string $employeeId): Response
    {
        $this->authorize('viewAny', Employee::class);
        PlanFeatureGate::check(app('current_company'), 'excel_export');

        $employee = Employee::query()->findOrFail($employeeId);

        $estimate = $this->estimationService->quickEstimate(
            employee: $employee,
            from: $request->validated('from'),
            to: $request->validated('to')
        );

        $company = app('current_company');

        $pdf = Pdf::loadView('pdf.receipt', [
            'company' => $company,
            'employee' => $employee,
            'estimate' => $estimate,
        ]);

        $fileName = sprintf(
            'receipt_estimate_employee_%s_%s_%s.pdf',
            $employee->id,
            $request->validated('from'),
            $request->validated('to')
        );

        return $pdf->download($fileName);
    }

    public function attendanceExport(QuickEstimateRequest $request, string $employeeId): StreamedResponse
    {
        $this->authorize('viewAny', Employee::class);
        PlanFeatureGate::check(app('current_company'), 'excel_export');

        $employee = Employee::query()->findOrFail($employeeId);
        $rows = $this->estimationService->exportRows(
            employee: $employee,
            from: $request->validated('from'),
            to: $request->validated('to')
        );

        $fileName = sprintf(
            'attendance_export_employee_%s_%s_%s.csv',
            $employee->id,
            $request->validated('from'),
            $request->validated('to')
        );

        return response()->streamDownload(function () use ($rows): void {
            $handle = fopen('php://output', 'wb');
            fwrite($handle, "\xEF\xBB\xBF");
            fputcsv($handle, [
                'date',
                'employee_name',
                'check_in',
                'check_out',
                'hours_worked',
                'overtime_hours',
                'base_gain',
                'overtime_gain',
                'total_estimated',
                'currency',
                'status',
            ]);

            foreach ($rows as $row) {
                fputcsv($handle, $row);
            }

            fclose($handle);
        }, $fileName, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
