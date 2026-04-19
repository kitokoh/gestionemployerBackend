<?php

use App\Mail\AttendanceAlertMail;
use App\Models\AttendanceLog;
use App\Models\Company;
use App\Models\Employee;
use App\Models\EmployeeNotification;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('super-admin:reset-password {email} {password}', function (string $email, string $password) {
    DB::statement('SET search_path TO public');

    $affected = DB::table('super_admins')
        ->where('email', $email)
        ->update([
            'password_hash' => Hash::make($password),
        ]);

    if ($affected === 0) {
        $this->error("Aucun super admin trouve pour {$email}");

        return 1;
    }

    $this->info("Mot de passe super admin mis a jour pour {$email}");

    return 0;
})->purpose('Reset a super admin password safely');

Artisan::command(
    'attendance:notify-missing-punches
    {--company= : Limiter a une societe}
    {--window=20 : Fenetre de declenchement en minutes}
    {--missing-check-in-at=09:30 : Heure locale pour les alertes d absence d arrivee}
    {--missing-check-out-at=18:00 : Heure locale pour les alertes d absence de sortie}',
    function () {
        $originalSearchPath = DB::getDriverName() === 'pgsql'
            ? (DB::selectOne('SHOW search_path')->search_path ?? 'public')
            : null;

        $window = max(1, (int) $this->option('window'));
        $companyFilter = $this->option('company');
        $missingCheckInAt = (string) $this->option('missing-check-in-at');
        $missingCheckOutAt = (string) $this->option('missing-check-out-at');

        $companies = Company::withoutGlobalScopes()
            ->when($companyFilter, fn ($query, $companyId) => $query->where('id', $companyId))
            ->where('status', 'active')
            ->orderBy('name')
            ->get();

        $collectMissingCheckIns = function (Carbon $localNow, string $threshold) {
            $today = $localNow->toDateString();

            return Employee::query()
                ->with('schedule')
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->filter(function (Employee $employee) use ($today, $threshold): bool {
                    $schedule = $employee->schedule;

                    if ($schedule && $schedule->crossesMidnight()) {
                        return false;
                    }

                    if ($schedule && $schedule->start_time > $threshold) {
                        return false;
                    }

                    return ! AttendanceLog::query()
                        ->where('employee_id', $employee->id)
                        ->where('date', $today)
                        ->where('session_number', 1)
                        ->exists();
                })
                ->map(fn (Employee $employee): array => [
                    'employee_id' => $employee->id,
                    'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                    'matricule' => $employee->matricule,
                    'context' => 'Aucun pointage d entree enregistre.',
                ]);
        };

        $collectMissingCheckOuts = function (Carbon $localNow, string $threshold) {
            $today = $localNow->toDateString();

            return Employee::query()
                ->with('schedule')
                ->where('status', 'active')
                ->orderBy('id')
                ->get()
                ->filter(function (Employee $employee) use ($today, $threshold): bool {
                    $schedule = $employee->schedule;

                    if ($schedule && $schedule->crossesMidnight()) {
                        return false;
                    }

                    if ($schedule && $schedule->end_time > $threshold) {
                        return false;
                    }

                    return AttendanceLog::query()
                        ->where('employee_id', $employee->id)
                        ->where('date', $today)
                        ->where('session_number', 1)
                        ->whereNotNull('check_in')
                        ->whereNull('check_out')
                        ->exists();
                })
                ->map(fn (Employee $employee): array => [
                    'employee_id' => $employee->id,
                    'name' => trim(($employee->first_name ?? '').' '.($employee->last_name ?? '')),
                    'matricule' => $employee->matricule,
                    'context' => 'Entree enregistree mais sortie manquante.',
                ]);
        };

        foreach ($companies as $company) {
            $timezone = $company->timezone ?: 'Africa/Algiers';
            $localNow = now('UTC')->setTimezone($timezone);

            $dispatches = collect([
                [
                    'type' => 'missing_check_in',
                    'threshold' => $missingCheckInAt,
                    'items' => fn () => $collectMissingCheckIns($localNow, $missingCheckInAt),
                ],
                [
                    'type' => 'missing_check_out',
                    'threshold' => $missingCheckOutAt,
                    'items' => fn () => $collectMissingCheckOuts($localNow, $missingCheckOutAt),
                ],
            ])->filter(function (array $dispatch) use ($localNow, $window): bool {
                $threshold = Carbon::parse($localNow->toDateString().' '.$dispatch['threshold'], $localNow->timezone);

                return $localNow->betweenIncluded(
                    $threshold->copy()->subMinutes($window),
                    $threshold->copy()->addMinutes($window)
                );
            });

            if ($dispatches->isEmpty()) {
                continue;
            }

            if (DB::getDriverName() === 'pgsql') {
                $searchPath = $company->tenancy_type === 'schema'
                    ? "{$company->schema_name},public"
                    : 'shared_tenants,public';
                DB::statement("SET search_path TO {$searchPath}");
            }

            app()->instance('current_company', $company);

            try {
                $recipients = Employee::query()
                    ->where('role', 'manager')
                    ->where('status', 'active')
                    ->orderBy('id')
                    ->get();

                if ($recipients->isEmpty()) {
                    continue;
                }

                foreach ($dispatches as $dispatch) {
                    $items = $dispatch['items']();

                    if ($items->isEmpty()) {
                        continue;
                    }

                    foreach ($recipients as $recipient) {
                        $title = sprintf(
                            '%s - %s %s',
                            $dispatch['type'] === 'missing_check_out' ? 'Sorties manquantes' : 'Pointages manquants',
                            $localNow->toDateString(),
                            $dispatch['threshold']
                        );

                        $alreadySent = EmployeeNotification::query()
                            ->where('employee_id', $recipient->id)
                            ->where('type', $dispatch['type'])
                            ->where('title', $title)
                            ->exists();

                        if ($alreadySent) {
                            continue;
                        }

                        EmployeeNotification::query()->create([
                            'company_id' => $company->id,
                            'employee_id' => $recipient->id,
                            'type' => $dispatch['type'],
                            'title' => $title,
                            'body' => sprintf('%d pointage(s) a verifier.', $items->count()),
                            'data' => [
                                'date' => $localNow->toDateString(),
                                'threshold' => $dispatch['threshold'],
                                'employees' => $items->values()->all(),
                            ],
                            'created_at' => now(),
                        ]);

                        Mail::to($recipient->email)->send(new AttendanceAlertMail(
                            company: $company,
                            recipient: $recipient,
                            alertType: $dispatch['type'],
                            thresholdTime: $dispatch['threshold'],
                            items: $items->values()->all(),
                            localDate: $localNow->translatedFormat('d/m/Y'),
                        ));
                    }
                }
            } finally {
                app()->forgetInstance('current_company');

                if (DB::getDriverName() === 'pgsql' && $originalSearchPath) {
                    DB::statement("SET search_path TO {$originalSearchPath}");
                }
            }
        }

        $this->info('Alertes de pointage traitees.');

        return 0;
    }
)->purpose('Envoyer les alertes RH automatiques sur les pointages manquants');

Schedule::command('attendance:notify-missing-punches')->everyThirtyMinutes();
