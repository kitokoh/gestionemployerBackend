<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AttendanceKiosk;
use App\Services\KioskAttendanceService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class KioskController extends Controller
{
    public function __construct(
        private readonly KioskAttendanceService $kioskAttendanceService,
    ) {
    }

    public function show(string $deviceCode): View
    {
        $kiosk = AttendanceKiosk::query()
            ->where('device_code', strtoupper($deviceCode))
            ->where('status', 'active')
            ->firstOrFail();

        return view('kiosk.show', [
            'kiosk' => $kiosk,
            'company' => $kiosk->company,
        ]);
    }

    public function punch(Request $request, string $deviceCode): RedirectResponse
    {
        $validated = $request->validate([
            'identifier' => ['required', 'string', 'max:150'],
            'action' => ['nullable', 'in:check_in,check_out'],
        ]);

        $kiosk = AttendanceKiosk::query()
            ->where('device_code', strtoupper($deviceCode))
            ->where('status', 'active')
            ->firstOrFail();

        app()->instance('current_company', $kiosk->company);

        $this->kioskAttendanceService->punch(
            kiosk: $kiosk,
            identifier: trim($validated['identifier']),
            action: $validated['action'] ?? 'check_in',
        );

        return redirect()
            ->route('kiosk.show', $kiosk->device_code)
            ->with('status', 'Pointage enregistre avec succes.');
    }
}
