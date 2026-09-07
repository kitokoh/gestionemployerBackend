<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelCashSession;
use App\Modules\FuelStation\Domain\Models\FuelCashSessionMovement;
use App\Modules\FuelStation\Infrastructure\Services\FuelCashSessionService;

/**
 * Cas d'usage : ajout d'un mouvement (apport/retrait) sur une session de
 * caisse ouverte (FUEL-007, issue #5801).
 *
 * Consommé par `POST .../fuel-station/cash-sessions/{session}/movements`
 * (FuelCashSessionController::addMovement). L'accès (404 cross-tenant +
 * Policy addMovement) reste au niveau interface ; l'Action porte le cas
 * d'usage nommable.
 */
class AddFuelCashSessionMovementAction
{
    public function __construct(
        private readonly FuelCashSessionService $sessions,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(FuelCashSession $session, Employee $actor, array $validated): FuelCashSessionMovement
    {
        return $this->sessions->addMovement($session, $actor, $validated);
    }
}
