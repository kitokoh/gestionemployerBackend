<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Application\Actions;

use App\Modules\Payroll\Domain\Models\PayrollRun;

/**
 * Cas d'usage : annulation d'un run de paie (route `POST|DELETE …/cancel`).
 *
 * Orchestration pure (ADR-0020, lot 1b cycle de paie — #6968) : passage en
 * `cancelled`. La garde métier (un run `paid`/`cancelled`/`locked` n'est pas
 * annulable — 422 localisé) et les autorisations restent au niveau interface
 * (contrôleur), comme pour le reste du lot 1.
 */
class CancelPayrollRun
{
    public function execute(PayrollRun $run): PayrollRun
    {
        $run->update(['status' => 'cancelled']);

        return $run->refresh();
    }
}
