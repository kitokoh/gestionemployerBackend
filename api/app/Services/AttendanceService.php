<?php

namespace App\Services;

use App\Exceptions\AlreadyCheckedInException;
use App\Exceptions\MissingCheckInException;
use App\Models\AttendanceLog;
use App\Models\Employee;
use App\Models\Schedule;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class AttendanceService
{
    public function checkIn(Employee $employee, ?float $gpsLat = null, ?float $gpsLng = null, string $method = 'mobile'): AttendanceLog
    {
        return DB::transaction(function () use ($employee, $gpsLat, $gpsLng, $method): AttendanceLog {
            $company = app('current_company');

            $nowUtc = now('UTC');
            $today = $nowUtc->copy()->setTimezone($company->timezone)->toDateString();

            $open = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today)
                ->where('session_number', 1)
                ->whereNull('check_out')
                ->lockForUpdate()
                ->first();

            if ($open) {
                throw new AlreadyCheckedInException();
            }

            $schedule = $this->resolveSchedule($employee);
            $status = 'incomplete';
            $lateMinutes = 0;

            if ($schedule) {
                $checkInLocal = $nowUtc->copy()->setTimezone($company->timezone);
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
                'check_in' => $nowUtc,
                'method' => $method,
                'status' => $status,
                'late_minutes' => $lateMinutes,
                'gps_lat' => $gpsLat,
                'gps_lng' => $gpsLng,
            ]);
        });
    }

    public function checkOut(Employee $employee, ?float $gpsLat = null, ?float $gpsLng = null, string $method = 'mobile'): AttendanceLog
    {
        return DB::transaction(function () use ($employee, $gpsLat, $gpsLng, $method): AttendanceLog {
            $company = app('current_company');

            $nowUtc = now('UTC');
            $today = $nowUtc->copy()->setTimezone($company->timezone)->toDateString();

            $log = AttendanceLog::query()
                ->where('employee_id', $employee->id)
                ->where('date', $today)
                ->where('session_number', 1)
                ->whereNull('check_out')
                ->lockForUpdate()
                ->first();

            if (! $log) {
                throw new MissingCheckInException();
            }

            $schedule = $log->schedule_id
                ? Schedule::query()->find($log->schedule_id)
                : $this->resolveSchedule($employee);

            $seconds = $log->check_in?->diffInSeconds($nowUtc) ?? 0;
            $hours = round($seconds / 3600, 2);
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
                $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
                $log->late_minutes = max(0, $diffMinutes);
                $log->status = $diffMinutes > (int) $schedule->late_tolerance_minutes ? 'late' : 'ontime';
            }

            $log->save();

            return $log;
        });
    }

    public function importExternalPunch(Employee $employee, array $payload): AttendanceLog
    {
        return DB::transaction(function () use ($employee, $payload): AttendanceLog {
            $company = app('current_company');
            $occurredAt = Carbon::parse($payload['occurred_at'] ?? now('UTC'))->utc();
            $today = $occurredAt->copy()->setTimezone($company->timezone)->toDateString();
            $action = $payload['action'] ?? 'check_in';
            $externalEventId = $payload['external_event_id'] ?? null;

            if ($externalEventId) {
                $existing = AttendanceLog::query()->where('external_event_id', $externalEventId)->lockForUpdate()->first();
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
                    ->lockForUpdate()
                    ->first();

                if (! $log) {
                    throw new MissingCheckInException();
                }

                $schedule = $log->schedule_id
                    ? Schedule::query()->find($log->schedule_id)
                    : $this->resolveSchedule($employee);

                $seconds = $log->check_in?->diffInSeconds($occurredAt) ?? 0;
                $hours = round($seconds / 3600, 2);
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
                ->lockForUpdate()
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
        });
    }

    public function correct(AttendanceLog $attendanceLog, array $payload, Employee $actor): AttendanceLog
    {
        return DB::transaction(function () use ($attendanceLog, $payload, $actor): AttendanceLog {
            /** @var AttendanceLog $log */
            $log = AttendanceLog::query()->lockForUpdate()->findOrFail($attendanceLog->id);
            $company = app('current_company');

            $checkIn = array_key_exists('check_in', $payload)
                ? ($payload['check_in'] ? Carbon::parse($payload['check_in'])->utc() : null)
                : $log->check_in;

            $checkOut = array_key_exists('check_out', $payload)
                ? ($payload['check_out'] ? Carbon::parse($payload['check_out'])->utc() : null)
                : $log->check_out;

            if ($checkOut && ! $checkIn) {
                throw ValidationException::withMessages([
                    'check_in' => 'Un pointage d entree est requis avant de definir la sortie.',
                ]);
            }

            if ($checkIn && $checkOut && $checkOut->lessThanOrEqualTo($checkIn)) {
                throw ValidationException::withMessages([
                    'check_out' => 'L heure de sortie doit etre posterieure a l heure d entree.',
                ]);
            }

            $schedule = $log->schedule_id
                ? Schedule::query()->find($log->schedule_id)
                : $this->resolveSchedule($log->employee);

            $hoursWorked = null;
            $overtimeHours = null;
            $lateMinutes = 0;
            $status = $payload['status'] ?? null;

            if ($checkIn) {
                $date = $checkIn->copy()->setTimezone($company->timezone)->toDateString();

                if ($schedule) {
                    $startLocal = Carbon::parse($date.' '.$schedule->start_time, $company->timezone);
                    $checkInLocal = $checkIn->copy()->setTimezone($company->timezone);
                    $diffMinutes = $startLocal->diffInMinutes($checkInLocal, false);
                    $lateMinutes = max(0, $diffMinutes);

                    if ($status === null) {
                        $status = $diffMinutes > (int) $schedule->late_tolerance_minutes ? 'late' : 'ontime';
                    }
                }

                if ($checkOut) {
                    $seconds = $checkIn->diffInSeconds($checkOut);
                    $hoursWorked = round($seconds / 3600, 2);
                    $threshold = (float) ($schedule?->overtime_threshold_daily ?? 8.0);
                    $overtimeHours = max(0.0, round($hoursWorked - $threshold, 2));
                } else {
                    $status ??= 'incomplete';
                }

                $log->date = $date;
            } else {
                $status ??= 'absent';
            }

            $log->forceFill([
                'check_in' => $checkIn,
                'check_out' => $checkOut,
                'hours_worked' => $hoursWorked,
                'overtime_hours' => $overtimeHours,
                'late_minutes' => $lateMinutes,
                'status' => $status,
                'corrected_by' => $actor->id,
                'correction_note' => trim((string) ($payload['correction_note'] ?? '')),
            ])->save();

            return $log->fresh();
        });
    }

    private function resolveSchedule(Employee $employee): ?Schedule
    {
        if ($employee->schedule_id) {
            return Schedule::query()->find($employee->schedule_id);
        }

        return null;
    }
}
