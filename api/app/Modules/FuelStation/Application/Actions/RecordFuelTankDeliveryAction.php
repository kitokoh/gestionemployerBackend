<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelTank;
use App\Modules\FuelStation\Domain\Models\FuelTankDelivery;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;

/**
 * Cas d'usage : enregistrement d'un dépôt carburant sur une cuve (livraison
 * fournisseur).
 *
 * Consommé par `POST .../fuel-station/tanks/{tank}/deliveries`
 * (FuelStockController::storeDelivery). L'appartenance de la cuve au tenant
 * (404) et la Policy createDelivery restent au niveau interface ; la logique
 * métier (niveau cuve, outbox) reste dans FuelStockService.
 */
class RecordFuelTankDeliveryAction
{
    public function __construct(
        private readonly FuelStockService $stocks,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(FuelTank $tank, Employee $actor, array $validated): FuelTankDelivery
    {
        return $this->stocks->recordDelivery($tank, $actor, $validated);
    }
}
