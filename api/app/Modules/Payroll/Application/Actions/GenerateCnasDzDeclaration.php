<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationGenerator;
use App\Modules\Payroll\Infrastructure\Services\SocialDeclarationService;
use DateTimeInterface;

/**
 * Cas d'usage : génération de la déclaration trimestrielle CNAS (DZ) —
 * une ligne par employé actif du trimestre (NIS, identité, brut, mois
 * travaillés), agrégée depuis les bulletins (route …/generate-cnas).
 *
 * Orchestration pure et nommable (ADR-0020, lot 3a déclarations — #6968) :
 * la collecte des données (employés actifs, mois du trimestre, données de
 * paie agrégées) reste dans `SocialDeclarationService`, le formatage du
 * fichier dans `SocialDeclarationGenerator` (Infrastructure). L'Action
 * construit les lignes et retourne le contenu prêt à servir — l'interface
 * (contrôleur) conserve l'autorisation, la validation et l'enveloppe HTTP.
 *
 * @return array{content: string, employee_count: int, filename: string}
 */
class GenerateCnasDzDeclaration
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
            withMonthsCount: true,
        );

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company?->name ?? 'N/A';
        $companyNis = $this->companyRegistrationNumber($company);

        $declarationRows = $employees->map(function ($emp) use ($payrollData): array {
            $payroll = $payrollData->get($emp->id);

            return [
                'employee_id' => (int) $emp->id,
                'num_ss' => (string) ($emp->national_id ?? ''),
                'last_name' => (string) ($emp->last_name ?? ''),
                'first_name' => (string) ($emp->first_name ?? ''),
                'date_naissance' => $this->dateValue($emp->date_of_birth ?? null),
                'gross_salary' => (float) ($payroll->total_gross ?? 0),
                'months_worked' => (int) ($payroll->months_worked ?? 0),
            ];
        })->filter(fn (array $row): bool => $row['gross_salary'] > 0);

        $generator = new SocialDeclarationGenerator;
        $content = $generator->generateCnasDz(
            $companyName,
            $companyNis,
            $quarter,
            $year,
            $declarationRows->values(),
        );

        return [
            'content' => $content,
            'employee_count' => $declarationRows->count(),
            'filename' => sprintf('CNAS_DZ_%s_%d_%s.txt', $quarter, $year, now()->format('Ymd')),
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

    private function dateValue(mixed $value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return $value === null ? '' : (string) $value;
    }
}
