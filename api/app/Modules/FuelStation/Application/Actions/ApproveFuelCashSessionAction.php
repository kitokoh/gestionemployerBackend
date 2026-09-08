<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Infrastructure\Services\FuelCashSessionService;

/**
 * Cas d'usage : approbation d'une session de caisse clôturée par un manager
 * (FUEL-007, issue #5801).
 *
 * Consommé par `POST .../fuel-station/cash-sessions/{session}/approve`
 * (FuelCashSessionController::approve). L'écriture d'audit
 * (fuel.cash_session.approved) reste portée par l'interface (elle dépend de
 * la requête HTTP).
 */
class ApproveFuelCashSessionAction
{
    public function __construct(
        private readonly FuelCashSessionService $sessions,
    ) {}

    public function execute(FuelCashSession $session, Employee $actor): FuelCashSession
    {
        return $this->sessions->approve($session, $actor);
    }
}
