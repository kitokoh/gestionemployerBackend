<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Modules\Payroll\Domain\Models\PaySlip;
use App\Modules\Payroll\Infrastructure\Services\DasDeclarationGenerator;

/**
 * Cas d'usage : génération de la Déclaration Annuelle des Salaires (DAS, DZ) —
 * CSV annuel agrégé depuis les bulletins validés des runs DZ de l'année
 * (une ligne par employé : NIS, nom, mois, brut, CNAS 9 %/26 %, IRG, net +
 * totaux), route …/generate-das (#5243).
 *
 * Orchestration pure et nommable (ADR-0020, lot 3a déclarations — #6968) :
 * la sélection des bulletins et le formatage restent dans les services /
 * générateur Infrastructure ; l'Action retourne le contenu prêt à servir —
 * l'interface (contrôleur) conserve l'autorisation (manager
 * principal/comptable), la validation et l'enveloppe HTTP.
 *
 * @return array{content: string, employee_count: int, filename: string}
 */
class GenerateDasDzDeclaration
{
    public function execute(Employee $actor, int $year): array
    {
        $slips = PaySlip::query()
            ->where('company_id', $actor->company_id)
            ->where('status', 'validated')
            ->whereBetween('period_start', ["{$year}-01-01", "{$year}-12-31"])
            ->whereHas('payrollRun', fn ($query) => $query->where('country_code', 'DZ'))
            ->with(['employee', 'lines'])
            ->get();

        $company = Company::query()->whereKey($actor->company_id)->first();

        $companyName = $company->name ?? 'N/A';
        $companyNis = $this->companyRegistrationNumber($company);

        $content = (new DasDeclarationGenerator)->generate(
            $companyName,
            $companyNis,
            $year,
            $slips,
        );

        return [
            'content' => $content,
            'employee_count' => $slips->groupBy('employee_id')->count(),
            'filename' => sprintf('DAS_DZ_%d_%s.txt', $year, now()->format('Ymd')),
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
