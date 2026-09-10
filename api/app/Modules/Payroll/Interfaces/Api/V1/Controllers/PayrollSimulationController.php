<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\SuperAdmin;
use App\Http\Controllers\Controller;
use App\Modules\Payroll\Application\Actions\SimulatePayrollDryRun;
use App\Modules\Payroll\Domain\Exceptions\PayrollPlaceholderAcknowledgementRequiredException;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

/**
 * Issue #1814 — Simulation d'impact d'un bareme fiscal (dry-run).
 *
 * POST /api/v1/payroll/simulate (manager principal/comptable) et
 * POST /api/v1/admin/payroll/simulate (platform_admin).
 *
 * Ne persiste RIEN : execute le moteur de paie reel
 * (CountryRulesInterface via PayrollCalculator) avec un bareme fourni en
 * parametre (`slabs_override`), ou le bareme actuel s'il est absent.
 * La réponse détaille le calcul ligne par ligne (cotisations, assiette,
 * impôt par tranche, net, coût employeur).
 *
 * Couche Application (ADR-0020, lot 6 — #6968) : le cas d'usage nommable
 * vit dans `SimulatePayrollDryRun` ; ce controleur ne garde que
 * l'interface HTTP — autorisation (manager principal/comptable ou
 * platform_admin), validation, corrélation, audit HTTP de l'acceptation
 *  placeholder  (#1872) et enveloppe de reponse.
 */
class PayrollSimulationController extends Controller
{
    public function __construct(
        private readonly PayrollCalculator $payrollCalculator,
        private readonly SimulatePayrollDryRun $simulatePayrollDryRun,
    ) {}

    public function simulate(Request $request): JsonResponse
    {
        $user = $request->user();

        if ($user instanceof Employee) {
            if (! $user->isManager()) {
                abort(403);
            }
        } elseif (! $user instanceof SuperAdmin) {
            abort(401);
        }

        $validated = $request->validate([
            'gross_salary' => ['required', 'numeric', 'min:0'],
            // #1951 : contrat partage - memes pays que le moteur de paie
            // (plus de liste in: hardcodee, divergence #1951).
            'country_code' => ['required', 'string', Rule::in($this->payrollCalculator->rulesResolver()->supportedCountryCodes())],
            'slabs_override' => ['sometimes', 'array', 'min:1'],
            'slabs_override.*.min' => ['required_with:slabs_override', 'numeric', 'min:0'],
            'slabs_override.*.max' => ['nullable', 'numeric', 'min:0'],
            'slabs_override.*.rate' => ['required_with:slabs_override', 'numeric', 'min:0', 'max:100'],
            'slabs_override.*.fixed_deduction' => ['sometimes', 'numeric', 'min:0'],
            'ignore_caps' => ['sometimes', 'boolean'],
            // Issue #1872 - regle  placeholder  : confirmation explicite requise.
            'acknowledge_placeholder' => ['sometimes', 'boolean'],
            // Issue #6686 : month/employee_id ne font pas partie du contrat de
            // simulation (champ non utilise, jamais documente) - les rejeter
            // explicitement plutot que de les ignorer silencieusement (un
            // client qui calcule avec un mois errone doit recevoir un 422, pas
            // un resultat chiffre faux). Le vrai calcul mensuel passe par
            // /payroll-runs.
            'month' => ['prohibited'],
            'employee_id' => ['prohibited'],
        ]);

        /** @var array{gross_salary: float|string, country_code: string, slabs_override?: array<int, array{min: float|string, max?: float|string|null, rate: float|string, fixed_deduction?: float|string}>, ignore_caps?: bool, acknowledge_placeholder?: bool} $validated */
        $gross = (float) $validated['gross_salary'];
        $countryCode = $validated['country_code'];

        // Issue #1874 - identifiant de correlation de la requete (logs ↔
        // reponse ↔ audit) : X-Correlation-ID / X-Request-Id header (repli
        // UUID frais), propage aux logs et a la reponse (RequestIdMiddleware).
        $correlationId = correlation_id();
        Log::withContext(['correlation_id' => $correlationId]);

        $companyId = $user instanceof Employee ? (string) $user->company_id : null;
        $acknowledged = $request->boolean('acknowledge_placeholder');

        // Cas d'usage nommable (ADR-0020, lot 6 #6968) - resolution des
        // regles, garde placeholder (#1872/#5623 → 422), overrides dry-run,
        // calcul et audit dans SimulatePayrollDryRun.
        try {
            $result = $this->simulatePayrollDryRun->execute(
                $companyId,
                $countryCode,
                $gross,
                $validated['slabs_override'] ?? null,
                (bool) ($validated['ignore_caps'] ?? false),
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

        // Issue #1872 - l'acceptation d'une regle  placeholder  est
        // AUDITEE (tenant, pays, acteur, navigateur) - jamais de secrets ni
        // de donnees biometriques.
        if ($result['rules_meta']['confidence'] === 'placeholder' && $companyId !== null && $acknowledged) {
            AuditLog::create([
                'company_id' => $companyId,
                'user_id' => $user->id,
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
                // Issue #1874 - correlation requete ↔ logs ↔ audit.
                'correlation_id' => $correlationId,
                'gross' => $result['gross'],
                'country_code' => $result['country_code'],
                // Issue #1872 - conformite : niveau de confiance des regles
                // pays + avertissement localise + source legale + date de
                // verification experte (meme structure que le contrat du
                // PayrollCalculationPresenter, consommee par TaxSlabsView).
                'compliance' => $result['compliance'],
                'social_employee' => $result['social_employee'],
                'social_employer' => $result['social_employer'],
                'tax_base' => $result['tax_base'],
                'income_tax' => $result['income_tax'],
                'income_tax_by_slab' => $result['income_tax_by_slab'],
                'net' => $result['net'],
                'total_cost' => $result['total_cost'],
            ],
        ]);
    }
}
