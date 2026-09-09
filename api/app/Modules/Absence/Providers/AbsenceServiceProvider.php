<?php

declare(strict_types=1);

namespace App\Modules\Absence\Providers;

use App\AI\Support\AIToolDefinitionRegistry;
use App\Modules\Absence\Domain\Support\AbsenceDecisionToolCatalog;
use Illuminate\Support\ServiceProvider;

/**
 * PA2-ARCH-002 : ce module n'est qu'une facade HTTP (Interfaces/ uniquement,
 * plus la déclaration déclarative des outils AI du BC-06 LEAVE, B3a #6856).
 * Les modeles/services metier (Absence, AbsenceType, LeaveBalance,
 * LeaveBalanceLog, AbsenceService) vivent dans App\Modules\Planning\*,
 * proprietaire canonique du domaine absence/conge. Voir api/ARCHITECTURE.md.
 */
class AbsenceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Aucun binding : ce module consomme directement les classes Planning\*.
    }

    public function boot(): void
    {
        // Boot Absence module — routes loaded via routes/modules/absence.php

        // B3a (#6856) — déclaration de l'outil écriture `absence_decision` au
        // contrat A3 (BC-23, #6850) : l'hôte ToolRegistry enrichit l'entrée
        // ai_tool_registry homonyme au boot. Garde d'idempotence (#6947) :
        // AIToolDefinitionRegistry est un collecteur statique re-booté à
        // chaque requête (PHP-FPM) et à chaque test — sans cette garde, la
        // 2e exécution du process lève « AIToolDefinition dupliquée ».
        foreach (AbsenceDecisionToolCatalog::definitions() as $definition) {
            if (! AIToolDefinitionRegistry::has($definition->name)) {
                AIToolDefinitionRegistry::register($definition);
            }
        }
    }
}
