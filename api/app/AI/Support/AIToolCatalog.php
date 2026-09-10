<?php

declare(strict_types=1);

namespace App\AI\Support;

/**
 * A3 (#6850) — convention de localisation des catalogues d'outils par BC.
 *
 * Chaque BC propriétaire expose ses `AIToolDefinition` via UN catalogue
 * `<Module>/Domain/Support/<X>ToolCatalog` qui implémente cette interface
 * (méthode statique `definitions()`), enregistré au boot dans le
 * ServiceProvider du module (garde d'idempotence #6947) :
 *
 *   App\Modules\HR\Domain\Support\HrReadToolCatalog            (BC-04, B1 #6854)
 *   App\Modules\Absence\Domain\Support\AbsenceDecisionToolCatalog (BC-06, B3a #6856)
 *   App\Modules\Planning\Domain\Support\ShiftAssignToolCatalog  (BC-05, B3b #6857)
 *   (liste non exhaustive — tout BC ajoute son catalogue au fil des lots)
 * L'hôte BC-23 ne connaît pas les noms d'outils en dur : il découvre les
 * définitions via `AIToolDefinitionRegistry` et ne consomme que le contrat
 * (`AIToolDefinition`). La cohérence de l'ensemble (format `bc`,
 * permission accordée, entrée `ai_tool_registry` présente, schémas) est
 * gardée par `AIToolContractGuardTest` (boot réel).
 */
interface AIToolCatalog
{
    /**
     * @return list<AIToolDefinition>
     */
    public static function definitions(): array;
}
