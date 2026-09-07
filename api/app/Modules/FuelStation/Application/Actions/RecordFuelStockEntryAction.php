<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Domain\Models\FuelStockEntry;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;

/**
 * Cas d'usage : enregistrement d'une entrée de stock (livraison pompiste ou
 * inventaire — reason obligatoire).
 *
 * Consommé par `POST .../fuel-station/stock-entries`
 * (FuelStockController::store). La logique métier (niveaux, produit,
 * outbox) reste dans FuelStockService (Infrastructure) ; l'Action porte le
 * cas d'usage nommable.
 */
class RecordFuelStockEntryAction
{
    public function __construct(
        private readonly FuelStockService $stocks,
    ) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function execute(Employee $actor, array $validated): FuelStockEntry
    {
        return $this->stocks->recordEntry($actor, $validated);
    }
}
