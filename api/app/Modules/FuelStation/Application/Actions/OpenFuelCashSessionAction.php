<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Infrastructure\Services\FuelCashSessionService;

/**
 * Cas d'usage : ouverture d'une session de caisse par un pompiste (FUEL-007,
 * issue #5801).
 *
 * Consommé par `POST .../fuel-station/cash-sessions`
 * (FuelCashSessionController::store). La politique d'accès (feature flag
 * solution + Policy create) reste au niveau interface ; l'Action porte le
 * cas d'usage nommable, la logique métier (contrôle de session ouverte,
 * solde d'ouverture) reste dans FuelCashSessionService (Infrastructure).
 */
class OpenFuelCashSessionAction
{
    public function __construct(
        private readonly FuelCashSessionService $sessions,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): FuelCashSession
    {
        return $this->sessions->open($actor, $validated);
    }
}
