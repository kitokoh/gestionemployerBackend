<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Services\KioskAttendanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    public function __construct(
        private readonly KioskAttendanceService $kioskAttendanceService,
    ) {
    }

    public function register(Request $request): JsonResponse
    {
        $company = app('current_company');
        $actor = $request->user();

        abort_unless($actor?->isManager(), 403, 'FORBIDDEN');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'location_label' => ['nullable', 'string', 'max:120'],
            'biometric_mode' => ['nullable', 'in:fingerprint,face,mixed'],
            'trusted_device_label' => ['nullable', 'string', 'max:120'],
        ]);

        $kiosk = AttendanceKiosk::query()->create([
            'company_id' => $company->id,
            'name' => $validated['name'],
            'location_label' => $validated['location_label'] ?? null,
            'biometric_mode' => $validated['biometric_mode'] ?? 'fingerprint',
            'trusted_device_label' => $validated['trusted_device_label'] ?? null,
            'device_code' => strtoupper(Str::random(10)),
            'status' => 'active',
        ]);

        return new JsonResponse([
            'data' => $this->serializeKiosk($kiosk),
        ], 201);
    }

    public function punch(Request $request, string $deviceCode): JsonResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'action' => ['nullable', 'in:check_in,check_out'],
        ]);

        $kiosk = AttendanceKiosk::query()
            ->where('device_code', strtoupper($deviceCode))
            ->where('status', 'active')
            ->firstOrFail();

        $company = $kiosk->company;
        app()->instance('current_company', $company);

        $log = $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: trim($validated['identifier']),
            action: $validated['action'] ?? 'check_in',
        );

        return new JsonResponse([
            'data' => [
                'employee_id' => $log->employee_id,
                'date' => $log->date?->format('Y-m-d'),
                'check_in' => optional($log->check_in)->toIso8601String(),
                'check_out' => optional($log->check_out)->toIso8601String(),
                'method' => $log->method,
                'status' => $log->status,
            ],
        ], 201);
    }

    private function serializeKiosk(AttendanceKiosk $kiosk): array
    {
        return [
            'id' => $kiosk->id,
            'name' => $kiosk->name,
            'location_label' => $kiosk->location_label,
            'device_code' => $kiosk->device_code,
            'status' => $kiosk->status,
            'biometric_mode' => $kiosk->biometric_mode,
            'trusted_device_label' => $kiosk->trusted_device_label,
        ];
    }
}
