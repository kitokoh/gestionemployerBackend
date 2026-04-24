<?php

namespace App\Services;

use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\MissingCheckInException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AttendanceService
{
    public function checkIn(Employee $employee, ?float $gpsLat = null, ?float $gpsLng = null, string $method = 'mobile'): AttendanceLog
    {
        $company = app('current_company');

        $nowUtc = now('UTC');
        $today = $nowUtc->copy()->setTimezone($company->timezone)->toDateString();
        $schedule = $this->resolveSchedule($employee);

        $status = 'incomplete';
        $lateMinutes = 0;

        if ($schedule) {
            $checkInLocal = $nowUtc->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = (int) $startLocal->diffInMinutes($checkInLocal, false);
            $lateMinutes = max(0, $diffMinutes);
            $status = $diffMinutes > (int) $schedule->late_tolerance_minutes ? 'late' : 'ontime';
        }

        // Le SELECT-puis-INSERT n'est pas atomique : sous double-tap mobile,
        // deux requetes peuvent simultanement voir aucune session ouverte et
        // tenter d'inserer. La contrainte unique (employee_id, date, session_number)
        // garantit l'integrite, on attrape la collision pour rendre un 409 propre.
        return DB::transaction(function () use ($employee, $today, $schedule, $nowUtc, $method, $status, $lateMinutes, $gpsLat, $gpsLng) {
            $open = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today)
                ->where('session_number', 1)
                ->whereNull('check_out')
                ->lockForUpdate()
                ->first();

            if ($open) {
                throw new AlreadyCheckedInException;
            }

            try {
                return AttendanceLog::query()->create([
                    'employee_id' => $employee->id,
                    'schedule_id' => $schedule?->id,
                    'date' => $today,
                    'session_number' => 1,
                    'check_in' => $nowUtc,
                    'method' => $method,
                    'status' => $status,
                    'late_minutes' => $lateMinutes,
                    'gps_lat' => $gpsLat,
                    'gps_lng' => $gpsLng,
                ]);
            } catch (UniqueConstraintViolationException $exception) {
                throw new AlreadyCheckedInException;
            }
        });
    }

    public function checkOut(Employee $employee, ?float $gpsLat = null, ?float $gpsLng = null, string $method = 'mobile'): AttendanceLog
    {
        $company = app('current_company');

        $nowUtc = now('UTC');
        $today = $nowUtc->copy()->setTimezone($company->timezone)->toDateString();

        $log = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('session_number', 1)
            ->whereNull('check_out')
            ->first();

        if (! $log) {
            throw new MissingCheckInException;
        }

        $schedule = $log->schedule_id
            ? $log->schedule
            : $this->resolveSchedule($employee);

        $seconds = $log->check_in?->diffInSeconds($nowUtc) ?? 0;
        $hours = $this->workedHours($seconds, $schedule);

        $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
        $overtime = max(0.0, round($hours - $threshold, 2));

        $log->check_out = $nowUtc;
        $log->hours_worked = $hours;
        $log->overtime_hours = $overtime;
        $log->gps_lat = $gpsLat ?? $log->gps_lat;
        $log->gps_lng = $gpsLng ?? $log->gps_lng;
        $log->method = $method;

        if ($log->status === 'incomplete' && $schedule) {
            $checkInLocal = $log->check_in->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = (int) $startLocal->diffInMinutes($checkInLocal, false);
            $log->late_minutes = max(0, $diffMinutes);
            $log->status = $diffMinutes > (int) $schedule->late_tolerance_minutes ? 'late' : 'ontime';
        }

        $log->save();

        return $log;
    }

    public function importExternalPunch(
        Employee $employee,
        array $payload,
    ): AttendanceLog {
        $company = app('current_company');
        $occurredAt = Carbon::parse($payload['occurred_at'] ?? now('UTC'))->utc();
        $today = $occurredAt->copy()->setTimezone($company->timezone)->toDateString();
        $action = $payload['action'] ?? 'check_in';
        $externalEventId = $payload['external_event_id'] ?? null;

        if ($externalEventId) {
            $existing = AttendanceLog::query()->where('external_event_id', $externalEventId)->first();
            if ($existing) {
                return $existing;
            }
        }

        if ($action === 'check_out') {
            $log = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today)
                ->where('session_number', 1)
                ->whereNull('check_out')
                ->first();

            if (! $log) {
                throw new MissingCheckInException;
            }

            $schedule = $log->schedule_id
                ? $log->schedule
                : $this->resolveSchedule($employee);

            $seconds = $log->check_in?->diffInSeconds($occurredAt) ?? 0;
            $hours = $this->workedHours($seconds, $schedule);
            $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
            $overtime = max(0.0, round($hours - $threshold, 2));

            $log->forceFill([
                'check_out' => $occurredAt,
                'hours_worked' => $hours,
                'overtime_hours' => $overtime,
                'method' => $payload['method'] ?? 'kiosk_offline',
                'source_device_code' => $payload['source_device_code'] ?? null,
                'external_event_id' => $externalEventId,
                'biometric_type' => $payload['biometric_type'] ?? null,
                'synced_from_offline' => (bool) ($payload['synced_from_offline'] ?? true),
            ])->save();

            return $log;
        }

        $open = AttendanceLog::query()
            ->where('employee_id', $employee->id)
            ->where('date', $today)
            ->where('session_number', 1)
            ->whereNull('check_out')
            ->first();

        if ($open) {
            return $open;
        }

        $schedule = $this->resolveSchedule($employee);
        $status = 'incomplete';
        $lateMinutes = 0;

        if ($schedule) {
            $checkInLocal = $occurredAt->copy()->setTimezone($company->timezone);
            $startLocal = Carbon::parse($today.' '.$schedule->start_time, $company->timezone);
            $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
            $lateMinutes = max(0, $diffMinutes);
            $status = $diffMinutes > (int) $schedule->late_tolerance_minutes ? 'late' : 'ontime';
        }

        return AttendanceLog::query()->create([
            'company_id' => $employee->company_id,
            'employee_id' => $employee->id,
            'schedule_id' => $schedule?->id,
            'date' => $today,
            'session_number' => 1,
            'check_in' => $occurredAt,
            'method' => $payload['method'] ?? 'kiosk_offline',
            'source_device_code' => $payload['source_device_code'] ?? null,
            'external_event_id' => $externalEventId,
            'biometric_type' => $payload['biometric_type'] ?? null,
            'synced_from_offline' => (bool) ($payload['synced_from_offline'] ?? true),
            'status' => $status,
            'late_minutes' => $lateMinutes,
        ]);
    }

    private function resolveSchedule(Employee $employee): ?Schedule
    {
        return $employee->schedule;
    }

    /**
     * Convertit une duree de presence brute (en secondes) en heures effectives
     * en deduisant la pause configuree sur le planning.
     *
     * Regles :
     *  - si la presence est inferieure ou egale a la pause, on retourne 0
     *    (l'employe a quitte avant la fin de sa pause -> aucune heure facturable)
     *  - sinon : (presence - pause) arrondi a 2 decimales
     *  - si aucun planning n'est associe, la pause est consideree nulle pour
     *    rester compatible avec les modes degraded (kiosque externe sans
     *    schedule_id, importation historique, etc.)
     */
    private function workedHours(int $seconds, ?Schedule $schedule): float
    {
        $breakSeconds = $schedule ? max(0, (int) $schedule->break_minutes) * 60 : 0;
        $netSeconds = max(0, $seconds - $breakSeconds);

        return round($netSeconds / 3600, 2);
    }
}
