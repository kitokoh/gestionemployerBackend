<?php

declare(strict_types=1);

namespace App\Modules\FuelStation\Application\Actions;

use App\Core\Auth\Domain\Models\Employee;
use App\Modules\FuelStation\Infrastructure\Services\FuelStockService;
use Illuminate\Support\Carbon;

/**
 * Cas d'usage : exécution d'un rapprochement de stock (théorique vs relevé)
 * — idempotent par station/jour.
 *
 * Consommé par deux endpoints du FuelStockController :
 * - `POST .../fuel-station/stock/reconcile` (station optionnelle, jour par
 *   défaut = veille) ;
 * - `POST .../fuel-station/reconciliation-runs` (station obligatoire, jour
 *   par défaut = aujourd'hui).
 * Les défauts de date/station sont des choix d'interface (HTTP) ; l'Action
 * porte le cas d'usage nommable, le calcul des variances reste dans
 * FuelStockService (Infrastructure).
 *
 * @return array{run: \App\Modules\FuelStation\Domain\Models\FuelReconciliationRun, variances: array<int, mixed>}
 */
class RunFuelReconciliationAction
{
    public function __construct(
        private readonly FuelStockService $stocks,
    ) {}

    /**
     * @return array{run: \App\Modules\FuelStation\Domain\Models\FuelReconciliationRun, variances: array<int, mixed>}
     */
    public function execute(Employee $actor, ?int $stationId, Carbon $date): array
    {
        /** @var array{run: \App\Modules\FuelStation\Domain\Models\FuelReconciliationRun, variances: array<int, mixed>} $result */
        $result = $this->stocks->reconcile((string) $actor->company_id, $stationId, $date, $actor->id);

        return $result;
    }
}
