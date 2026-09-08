<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Support;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolSensitivity;

/**
 * B3b (#6857) — catalogue de l'outil d'ÉCRITURE WORKFORCE (BC-05) déclaré au
 * contrat A3 (BC-23, #6850, EPIC #6846) : `shift_assign` affecte un shift
 * (gabarit horaire `Schedule`, module Planning — BC-05 WORKFORCE) à un
 * employé. Sensibilité `write` → exécution UNIQUEMENT après confirmation
 * humaine (flux A4, POST /ai/actions/{id}/confirm|reject) — jamais par le
 * seul tool_call.
 *
 * Exécution en parité exacte avec l'endpoint REST canonique
 * `POST /api/v1/schedules/{schedule}/assign-employees`
 * (ScheduleController::assignEmployees) : manager du tenant uniquement ;
 * schedule et employé du tenant ; manager d'équipe (dept/superviseur) borné à
 * son périmètre (`visibleToManager`, PA2-SEC-002/003) ; invalidation du cache
 * employés (`TenantCacheService`). Aucune donnée hors tenant, aucun effet de
 * bord avant confirmation.
 *
 * Enregistrée par PlanningServiceProvider::boot() dans
 * AIToolDefinitionRegistry ; l'hôte BC-23 (ToolRegistry) enrichit l'entrée
 * `ai_tool_registry` homonyme sans changer son comportement (tranche A3).
 */
final class ShiftAssignToolCatalog
{
    /**
     * @return list<AIToolDefinition>
     */
    public static function definitions(): array
    {
        return [
            new AIToolDefinition(
                name: 'shift_assign',
                description: "Affecte un shift (gabarit horaire / planning type) à un employé du tenant. Réservé aux managers : l'employé prend le nouveau schedule. Action sensible : confirmation obligatoire avant exécution.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'schedule_id' => [
                            'type' => 'integer',
                            'description' => 'Identifiant du shift (schedule) à affecter.',
                        ],
                        'employee_id' => [
                            'type' => 'integer',
                            'description' => 'Identifiant de l\'employé concerné (du tenant, et du périmètre du manager si manager d\'équipe).',
                        ],
                    ],
                    'required' => ['schedule_id', 'employee_id'],
                ],
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'employee_id' => ['type' => 'integer'],
                        'schedule_id' => ['type' => 'integer'],
                        'schedule_name' => ['type' => 'string'],
                        'status' => ['type' => 'string', 'enum' => ['assigned']],
                    ],
                ],
                permission: 'schedules.assign',
                sensitivity: AIToolSensitivity::Write,
                bc: 'BC-05',
                version: 1,
            ),
        ];
    }
}
