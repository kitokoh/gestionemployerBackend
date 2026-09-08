<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Application\Actions\SimulateCotisations;
use App\Modules\Payroll\Domain\Exceptions\PayrollPlaceholderAcknowledgementRequiredException;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculationPresenter;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Simulation de cotisations sociales et d'impôt sur le revenu.
 *
 * Issue #1782 : ce contrôleur ne duplique PLUS aucune table de taux.
 * La source de vérité unique est le moteur de paie
 * (`PayrollCalculator::getRules()` → `CountryRulesInterface`), qui résout
 * DZ, MA, TN, FR, TR, SN, CEMAC×6, CEDEAO×6 et CA avec les mêmes règles que
 * les vrais bulletins — taux, caps, barèmes et abattements compris.
 *
 * Issue #1869 : la simulation et le bulletin passent par le MÊME noyau de
 * calcul (`PayrollCalculator::computeNetBreakdown()`), ce qui garantit des
 * résultats identiques pour un même brut et un même contexte de règles.
 * La réponse expose :
 *   - au niveau racine, les champs historiques (rétro-compatibles) ;
 *   - sous `contract`, le contrat complet et explicable (pays, devise,
 *     identifiant/version des règles, période, politique d'arrondi,
 *     bracket_tax, retenues totales…) — docs/payroll/CALCULATION_CONTRACT.md.
 *
 * Couche Application (ADR-0020, lot 6 — #6968) : le cas d'usage nommable
 * vit dans `SimulateCotisations` ; ce contrôleur ne garde que l'interface
 * HTTP — autorisation (manager principal/comptable), validation,
 * corrélation #1874, audit HTTP de l'acceptation « placeholder » (#1872),
 * présentation du contrat et enveloppe de réponse.
 */
class CotisationSimulationController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly PayrollCalculationPresenter $presenter,
        private readonly SimulateCotisations $simulateCotisations,
    ) {}

    public function simulate(Request $request): JsonResponse
    {
        /** @var Employee $actor */
        $actor = $request->user();

        if (! $actor->isManager()) {
            abort(403);
        }

        $validated = $request->validate([
            'gross_salary' => 'required|numeric|min:0',
            // #1951 : contrat partagé du moteur (plus de liste in: hardcodée).
            'country_code' => ['required', 'string', Rule::in($this->payrollCalculator->rulesResolver()->supportedCountryCodes())],
            'rules_period' => ['nullable', 'date'],
            // Issue #1872 — une règle « placeholder » (aucune valeur légale
            // implémentée) exige une confirmation explicite.
            'acknowledge_placeholder' => ['nullable', 'boolean'],
        ]);

        /** @var array{gross_salary: float|string, country_code: string, rules_period?: string|null, acknowledge_placeholder?: bool|null} $validated */
        $gross = (float) $validated['gross_salary'];
        $countryCode = $validated['country_code'];
        $rulesPeriodValue = $validated['rules_period'] ?? null;
        $rulesPeriod = $rulesPeriodValue !== null ? Carbon::parse($rulesPeriodValue) : null;
        $acknowledged = $request->boolean('acknowledge_placeholder');

        // Issue #1874 — identifiant de corrélation de la requête (logs ↔
        // réponse ↔ audit) : X-Correlation-ID / X-Request-Id header (repli
        // UUID frais), propagé aux logs et à la réponse (RequestIdMiddleware).
        $correlationId = correlation_id();
        Log::withContext(['correlation_id' => $correlationId]);

        $companyId = (string) $actor->company_id;

        // Cas d'usage nommable (ADR-0020, lot 6 #6968) — résolution des
        // règles tenant+période (#1924), garde placeholder (#1872 → 422),
        // ventilation salarial/patronal et audit dans SimulateCotisations.
        try {
            $result = $this->simulateCotisations->execute(
                $companyId,
                $countryCode,
                $gross,
                $rulesPeriod,
                $acknowledged,
                $correlationId,
            );
        } catch (PayrollPlaceholderAcknowledgementRequiredException $e) {
            return response()->json([
                'message' => __('payroll.placeholder_acknowledge_required', ['country' => $e->countryCode]),
                'errors' => [
                    'acknowledge_placeholder' => [__('payroll.placeholder_acknowledge_required', ['country' => $e->countryCode])],
                ],
            ], 422);
        }

        // Issue #1872 — l'acceptation d'une règle « placeholder » est
        // AUDITÉE (tenant, pays, acteur, navigateur) — jamais de secrets ni
        // de données biométriques.
        if ($result['rules_meta']['confidence'] === 'placeholder' && $acknowledged) {
            AuditLog::create([
                'company_id' => $actor->company_id,
                'user_id' => $actor->id,
                'action' => 'placeholder_warning_acknowledged',
                'auditable_type' => 'App\Modules\Payroll\Infrastructure\Services\CountryRules\CountryRulesResolver',
                'auditable_id' => 0,
                'old_values' => [],
                'new_values' => [
                    'country_code' => $countryCode,
                    'rules_identifier' => $result['rules_meta']['short_name'],
                    'confidence_level' => 'placeholder',
                ],
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
            ]);
        }

        return response()->json([
            'data' => [
                // Issue #1874 — corrélation requête ↔ logs ↔ audit.
                'correlation_id' => $correlationId,
                // ── Champs historiques (rétro-compatibles) ───────────────────
                'country_code' => $result['country_code'],
                'gross_salary' => $result['gross'],
                'employee_contributions' => $result['employee_contributions'],
                'employer_contributions' => $result['employer_contributions'],
                'total_employee_deduction' => $result['total_employee_deduction'],
                'total_employer_cost' => $result['total_employer_cost'],
                'taxable_gross' => $result['taxable_gross'],
                'income_tax' => $result['income_tax'],
                'bracket_tax' => $result['bracket_tax'],
                'total_deductions' => $result['total_deductions'],
                'net_before_tax' => $result['net_before_tax'],
                // Net réel = brut − retenues totales (issue #1782 + #1869).
                'net_salary' => $result['net_salary'],
                'total_cost_employer' => $result['total_cost_employer'],
                // ── Contrat complet et explicable (issue #1869) ──────────────
                // Le contrat reflète les overrides entreprise et la période
                // effective, comme le bulletin réel.
                'contract' => $this->presenter->present(
                    $result['country_code'],
                    $result['gross'],
                    $companyId,
                    $rulesPeriod,
                ),
            ],
        ]);
    }
}
