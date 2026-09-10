<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Core\Auth\Domain\Models\AuditLog;
use App\Core\Auth\Domain\Models\Employee;
use App\Modules\Payroll\Domain\Exceptions\PayrollPlaceholderAcknowledgementRequiredException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunCalculationFailedException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunCalculationRulesException;
use App\Modules\Payroll\Domain\Exceptions\PayrollRunZeroSlipsException;
use App\Modules\Payroll\Domain\Models\PayrollRun;
use App\Modules\Payroll\Infrastructure\Services\PayrollCalculator;

/**
 * Cas d'usage : calcul d'un run de paie (route `POST /payroll/runs/{id}/calculate`).
 *
 * Orchestration pure et nommable (ADR-0020, lot 1b cycle de paie - #6968),
 * politique metier inchangee par rapport a l'ancien corps de contrôleur :
 * - #6529 : run `error`/`processing` orphelin recalculable (jamais bloqué) ;
 *   statuts de clôture exclus (garde au niveau interface) ;
 * - #2555 : échec de résolution des règles pays → retour `draft` + exception ;
 * - #2332/#5623 : pays placeholder → confirmation explicite obligatoire,
 *   acceptation AUDITÉE (mêmes champs que les simulations #1872) ;
 * - #2221 : échec de calcul → retour `draft` (jamais bloqué en `calculating`) ;
 * - #1767 : 0 bulletin généré → retour `draft` (jamais de validation à vide).
 *
 * La résolution de règles, le calcul et la persistance restent dans
 * `PayrollCalculator` (Infrastructure). L'interface mappe les exceptions vers
 * les reponses 422 localisees et journalise le detail (elle detient le run).
 *
 * @throws PayrollRunCalculationRulesException resolution regles impossible (run → draft)
 * @throws PayrollPlaceholderAcknowledgementRequiredException confirmation placeholder manquante (aucun changement)
 * @throws PayrollRunCalculationFailedException echec de calcul (run → draft)
 * @throws PayrollRunZeroSlipsException 0 bulletin genere (run → draft)
 */
class CalculatePayrollRun
{
    public function __construct(
        private readonly PayrollCalculator $calculator,
    ) {}

    public function execute(
        PayrollRun $run,
        Employee $actor,
        bool $acknowledgePlaceholder = false,
        ?string $ipAddress = null,
        ?string $userAgent = null,
    ): PayrollRun {
        try {
            $rules = $this->calculator->getRules($run->country_code);
        } catch (\Throwable $e) {
            $run->update(['status' => PayrollRun::STATUS_DRAFT]);

            throw new PayrollRunCalculationRulesException($e->getMessage(), 0, $e);
        }

        // #2332/#5623 - confirmation explicite requise AVANT tout changement
        // de statut (jamais de run bloque en `calculating` sur un 422).
        if ($rules->confidenceLevel() === 'placeholder') {
            if (! $acknowledgePlaceholder) {
                throw new PayrollPlaceholderAcknowledgementRequiredException($run->country_code);
            }

            AuditLog::create([
                'company_id' => $run->company_id,
                'user_id' => $actor->id,
                'action' => 'placeholder_warning_acknowledged',
                'auditable_type' => 'App\\Modules\\Payroll\\Infrastructure\\Services\\CountryRules\\CountryRulesResolver',
                'auditable_id' => 0,
                'old_values' => [],
                'new_values' => [
                    'country_code' => $run->country_code,
                    'rules_identifier' => (new \ReflectionClass($rules))->getShortName(),
                    'confidence_level' => 'placeholder',
                    'context' => 'payroll_run_calculate',
                    'run_id' => $run->id,
                ],
                'ip_address' => $ipAddress,
                'user_agent' => $userAgent !== null && $userAgent !== '' ? mb_substr($userAgent, 0, 500) : null,
            ]);
        }

        $run->update(['status' => 'calculating']);

        try {
            $calculated = $this->calculator->calculateRun($run);
        } catch (\Throwable $e) {
            // #2221 : jamais de run laisse bloque en `calculating`.
            $run->update(['status' => PayrollRun::STATUS_DRAFT]);

            throw new PayrollRunCalculationFailedException($e->getMessage(), 0, $e);
        }

        // #1767 : un calcul a 0 bulletin ne reussit pas en silence.
        if ((int) $calculated->employee_count === 0) {
            $calculated->update(['status' => PayrollRun::STATUS_DRAFT]);

            throw new PayrollRunZeroSlipsException('Aucun bulletin genere pour ce run.');
        }

        return $calculated;
    }
}
