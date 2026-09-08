<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelSale;
use App\Modules\FuelStation\Infrastructure\Services\FuelSaleService;

/**
 * Cas d'usage : enregistrement d'une vente carburant (idempotent par
 * external_id), par tout employé authentifié.
 *
 * Consommé par `POST .../fuel-station/sales`
 * (FuelSaleController::store). La validation métier (tenant, pompe/cuve,
 * prix, idempotence) reste dans FuelSaleService (Infrastructure) ; l'Action
 * porte le cas d'usage nommable (FUEL-006).
 */
class RecordFuelSaleAction
{
    public function __construct(
        private readonly FuelSaleService $sales,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): FuelSale
    {
        return $this->sales->record($actor, $validated);
    }
}
