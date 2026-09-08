<?php

declare(strict_types=1);

namespace App\Modules\Planning\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Modules\Planning\Domain\Support\AbsenceDecisionToolCatalog;
use Illuminate\Support\ServiceProvider;

class PlanningServiceProvider extends ServiceProvider
{
    public function register(): void {}

    public function boot(): void
    {
        // B3a (#6856) — contrat d'outil `absence_decision` déclaré par le
        // BC-06 LEAVE (module Planning, propriétaire canonique des modèles
        // Absence, PA2-ARCH-002). Garde d'idempotence (#6947) : le collecteur
        // AIToolDefinitionRegistry est statique et survit aux boots
        // applicatifs (PHP-FPM/PHPUnit) — enregistrer seulement si absent.
        foreach (AbsenceDecisionToolCatalog::definitions() as $definition) {
            if (! AIToolDefinitionRegistry::has($definition->name)) {
                AIToolDefinitionRegistry::register($definition);
            }
        }
    }
}
