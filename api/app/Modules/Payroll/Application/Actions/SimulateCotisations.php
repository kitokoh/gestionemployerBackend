<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Payroll\Domain\Contracts\CountryRulesInterface;
use App\Modules\Payroll\Domain\Exceptions\CountryRulesContextMismatchException;
use App\Modules\Payroll\Domain\Exceptions\PayrollPlaceholderAcknowledgementRequiredException;
use App\Modules\Payroll\Domain\Exceptions\UnsupportedCountryRulesException;
use App\Modules\Payroll\Domain\Models\PayrollCalculationAudit;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationAuditRecorder;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Support\Carbon;
use ReflectionClass;
use Throwable;

/**
 * Cas d'usage : simulation de cotisations sociales et d'impôt sur le revenu
 * (#1782) — mêmes règles que le moteur de paie (`PayrollCalculator` →
 * `CountryRulesInterface`, résolution tenant + période effective, #1924),
 * aucun tableau de taux dupliqué.
 *
 * Orchestration pure et nommable (ADR-0020, lot 6 — #6968) : résolution des
 * règles pays (échecs audités), garde « placeholder » (#1872/#5623 — l'Action
 * lève `PayrollPlaceholderAcknowledgementRequiredException`, l'interface la
 * rend en 422), ventilation salarial/patronal des contributions (mêmes règles
 * d'assiette/tranche/plafond que le moteur, #2220), calcul par le pipeline
 * unique des bulletins (`computeNetBreakdown`, #1869) et audit de la
 * simulation (résultats agrégés uniquement, #1874).
 *
 * L'interface (contrôleur) conserve l'autorisation (manager principal/
 * comptable), la validation, l'audit HTTP de l'acceptation placeholder et
 * l'enveloppe de réponse (contrat `PayrollCalculationPresenter` compris).
 *
 * @return array{
 *     gross: float,
 *     country_code: string,
 *     rules_meta: array{short_name: string, confidence: string},
 *     employee_contributions: list<array{name: string, code: string, rate: float, cap: float|null, amount: float}>,
 *     employer_contributions: list<array{name: string, code: string, rate: float, cap: float|null, amount: float}>,
 *     total_employee_deduction: float,
 *     total_employer_cost: float,
 *     taxable_gross: float,
 *     income_tax: float,
 *     bracket_tax: float,
 *     total_deductions: float,
 *     net_before_tax: float,
 *     net_salary: float,
 *     total_cost_employer: float,
 * }
 */
class SimulateCotisations
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly PayrollCalculationAuditRecorder $auditRecorder,
    ) {}

    /**
     * @return array{
     *     gross: float,
     *     country_code: string,
     *     rules_meta: array{short_name: string, confidence: string},
     *     employee_contributions: list<array{name: mixed, code: mixed, rate: mixed, cap: mixed, amount: float}>,
     *     employer_contributions: list<array{name: mixed, code: mixed, rate: mixed, cap: mixed, amount: float}>,
     *     total_employee_deduction: float|int,
     *     total_employer_cost: float|int,
     *     taxable_gross: float,
     *     income_tax: float|int,
     *     bracket_tax: float|int,
     *     total_deductions: float,
     *     net_before_tax: float,
     *     net_salary: float|int,
     *     total_cost_employer: float|int
     * }
     */
    public function execute(
        string $companyId,
        string $countryCode,
        float $gross,
        ?Carbon $rulesPeriod,
        bool $acknowledgePlaceholder,
        string $correlationId,
    ): array {
        // Issue #1924/#1871 — le tenant et la période effective sont transmis
        // afin que les overrides entreprise et les règles historiques soient
        // identiques à ceux appliqués par un bulletin réel.
        $rules = $this->resolveRules($correlationId, $companyId, $countryCode, $gross, $rulesPeriod);

        // Issue #1872 — les règles « placeholder » (BJ/TG/NE/CF/TD/GQ : aucune
        // valeur légale sourcée) ne peuvent pas alimenter une simulation sans
        // confirmation explicite ; l'acceptation est AUDITÉE côté interface.
        if ($rules->confidenceLevel() === 'placeholder' && ! $acknowledgePlaceholder) {
            throw new PayrollPlaceholderAcknowledgementRequiredException($countryCode);
        }

        // Issue #1869 — mêmes appels métier que PayrollCalculator::calculateSlip().
        $breakdown = $this->payrollCalculator->computeNetBreakdown($gross, $rules);
        $social = $breakdown['social'];

        $employeeContributions = [];
        $employerContributions = [];
        foreach ($rules->socialContributions() as $contribution) {
            // Issue #2220 — la base suit la VRAIE règle du moteur :
            //  1. assiette_rate (ex. CSG/CRDS FR sur 98,25 % du brut) ;
            //  2. tranche floor/ceiling (ex. IPRES T2 SN 432 001–2 160 000) ;
            //  3. cap simple (plafond classique) ;
            //  4. sinon brut entier.
            if (isset($contribution['assiette_rate'])) {
                $base = $gross * ((float) $contribution['assiette_rate'] / 100);
            } elseif (isset($contribution['floor'])) {
                $base = $gross > (float) $contribution['floor']
                    ? min($gross, (float) ($contribution['ceiling'] ?? PHP_FLOAT_MAX)) - (float) $contribution['floor']
                    : 0.0;
            } else {
                $base = ($contribution['cap'] ?? null) === null
                    ? $gross
                    : min($gross, (float) $contribution['cap']);
            }

            $item = [
                'name' => $contribution['name'],
                'code' => $contribution['code'],
                'rate' => $contribution['rate'],
                'cap' => $contribution['cap'] ?? null,
                'amount' => round($base * (float) $contribution['rate'] / 100, 2),
            ];

            if ($contribution['type'] === 'employee') {
                $employeeContributions[] = $item;
            } else {
                $employerContributions[] = $item;
            }
        }

        // Issue #1874 — audit de la simulation (résultats agrégés uniquement,
        // jamais de salaires individuels ni de secrets).
        $this->auditRecorder->recordSimulation(
            $correlationId,
            $companyId,
            $countryCode,
            ['gross_salary' => $gross, 'rules_period' => $rulesPeriod?->toDateString()],
            [
                'total_employee_deduction' => round($social['employee'], 2),
                'total_employer_cost' => round($social['employer'], 2),
                'income_tax' => $breakdown['income_tax'],
                'bracket_tax' => $breakdown['bracket_tax'],
                'total_deductions' => round($breakdown['base_deductions'], 2),
                'net_salary' => $breakdown['net_salary'],
                'total_cost_employer' => $breakdown['total_cost'],
            ],
            PayrollCalculationAudit::STATUS_SUCCESS,
            null,
            $rules->rulesVersion(),
            (new ReflectionClass($rules))->getShortName(),
        );

        return [
            'gross' => $gross,
            'country_code' => $countryCode,
            // Consommé par l'interface pour l'audit HTTP de l'acceptation
            // placeholder (jamais exposé dans la réponse).
            'rules_meta' => [
                'short_name' => (new ReflectionClass($rules))->getShortName(),
                'confidence' => $rules->confidenceLevel(),
            ],
            'employee_contributions' => $employeeContributions,
            'employer_contributions' => $employerContributions,
            'total_employee_deduction' => $social['employee'],
            'total_employer_cost' => $social['employer'],
            'taxable_gross' => round($breakdown['taxable_gross'], 2),
            'income_tax' => $breakdown['income_tax'],
            'bracket_tax' => $breakdown['bracket_tax'],
            'total_deductions' => round($breakdown['base_deductions'], 2),
            // Rétro-compatible : brut − cotisations salariales (sans impôt).
            'net_before_tax' => round($gross - $social['employee'], 2),
            // Net réel = brut − retenues totales (issue #1782 + #1869).
            'net_salary' => $breakdown['net_salary'],
            'total_cost_employer' => $breakdown['total_cost'],
        ];
    }

    /**
     * Résout les règles pays pour la simulation ; toute erreur de résolution
     * est tracée dans l'audit (rule_missing / validation_error /
     * provider_error) puis relancée — la réponse HTTP reste inchangée.
     */
    private function resolveRules(
        string $correlationId,
        string $companyId,
        string $countryCode,
        float $gross,
        ?Carbon $rulesPeriod,
    ): CountryRulesInterface {
        $input = ['gross_salary' => $gross, 'rules_period' => $rulesPeriod?->toDateString()];

        try {
            return $this->payrollCalculator->rulesResolver()->resolve($countryCode, $companyId, $rulesPeriod);
        } catch (UnsupportedCountryRulesException $exception) {
            $this->auditRecorder->recordSimulation(
                $correlationId,
                $companyId,
                $countryCode,
                $input,
                null,
                PayrollCalculationAudit::STATUS_RULE_MISSING,
            );

            throw $exception;
        } catch (CountryRulesContextMismatchException $exception) {
            $this->auditRecorder->recordSimulation(
                $correlationId,
                $companyId,
                $countryCode,
                $input,
                null,
                PayrollCalculationAudit::STATUS_VALIDATION_ERROR,
            );

            throw $exception;
        } catch (Throwable $exception) {
            $this->auditRecorder->recordSimulation(
                $correlationId,
                $companyId,
                $countryCode,
                $input,
                null,
                PayrollCalculationAudit::STATUS_PROVIDER_ERROR,
            );

            throw $exception;
        }
    }
}
