<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Infrastructure\Services\FuelCashSessionService;

/**
 * Cas d'usage : clôture d'une session de caisse par le pompiste (FUEL-007,
 * issue #5801).
 *
 * Consommé par `POST .../fuel-station/cash-sessions/{session}/close`
 * (FuelCashSessionController::close). Clôture idempotente ; l'écart
 * (variance) est calculé serveur dans FuelCashSessionService. L'écriture
 * d'audit (fuel.cash_session.closed) reste portée par l'interface (elle
 * dépend de la requête HTTP).
 */
class CloseFuelCashSessionAction
{
    public function __construct(
        private readonly FuelCashSessionService $sessions,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(FuelCashSession $session, Employee $actor, array $validated): FuelCashSession
    {
        return $this->sessions->close($session, $actor, $validated);
    }
}
