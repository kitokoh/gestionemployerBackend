<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationService;
use Illuminate\Support\Facades\DB;

/**
 * Cas d'usage : déclaration trimestrielle CNSS (MA) — une ligne par employé
 * actif du trimestre (n° CNSS, identité, brut, jours travaillés déduits des
 * logs de présence), agrégée depuis les bulletins (route …/generate-cnss-ma).
 *
 * Orchestration pure et nommable (ADR-0020, lot 3b déclarations — #6968) :
 * la collecte (employés actifs, mois du trimestre, données de paie, jours de
 * présence) et le formatage restent dans `SocialDeclarationService` /
 * `SocialDeclarationGenerator` (Infrastructure). L'Action retourne le contenu
 * prêt à servir ; l'interface (contrôleur) conserve l'autorisation, la
 * validation et l'enveloppe HTTP.
 *
 * @return array{content: string, employee_count: int, filename: string}
 */
class GenerateCnssMaDeclaration
{
    public function __construct(
        private readonly SocialDeclarationService $declarations,
    ) {}

    public function execute(Employee $actor, string $quarter, int $year): array
    {
        $employees = $this->declarations->activeEmployees((string) $actor->company_id);

        $quarterMonths = $this->declarations->quarterMonths($quarter);

        $payrollData = $this->declarations->quarterPayrollData(
            (string) $actor->company_id,
            $year,
            $quarterMonths,
        );

        $attendanceData = DB::table('attendance_logs')
            ->where('company_id', $actor->company_id)
            ->whereYear('check_in', $year)
            ->whereIn(DB::raw('EXTRACT(MONTH FROM check_in)'), $quarterMonths)
            ->select([
                'employee_id',
                DB::raw('COUNT(DISTINCT DATE(check_in)) as days_worked'),
            ])
            ->groupBy('employee_id')
            ->get()
            ->keyBy('employee_id');

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company?->name ?? 'N/A';
        $companyAffiliate = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData, $attendanceData): array {
            $payroll = $payrollData->get($emp->id);
            $attendance = $attendanceData->get($emp->id);

            return [
                'employee_id' => (int) $emp->id,
                'num_cnss' => (string) ($emp->national_id ?? ''),
                'last_name' => (string) ($emp->last_name ?? ''),
                'first_name' => (string) ($emp->first_name ?? ''),
                'cin' => '',
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'days_worked' => (int) ($attendance->days_worked ?? 0),
            ];
        })->filter(fn (array $row): bool => $row['gross_salary'] > 0);

        $generator = new SocialDeclarationGenerator;
        $content = $generator->generateCnssMa(
            $companyName,
            $companyAffiliate,
            $quarter,
            $year,
            $declarationRows->values(),
        );

        return [
            'content' => $content,
            'employee_count' => $declarationRows->count(),
            'filename' => sprintf('CNSS_MA_%s_%d_%s.txt', $quarter, $year, now()->format('Ymd')),
        ];
    }

    private function companyRegistrationNumber(?Company $company): string
    {
        if ($company === null) {
            return '';
        }

        $metadata = $company->metadata ?? [];

        return (string) (
            $metadata['tax_id']
            ?? $metadata['nis']
            ?? $metadata['affiliate_number']
            ?? $metadata['siret']
            ?? ''
        );
    }
}
