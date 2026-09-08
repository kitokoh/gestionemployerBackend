<?php

declare(strict_types=1);

namespace App\Modules\Absence\Domain\Support;

use App\AI\Support\AIToolCatalog;
use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolSensitivity;

/**
 * B3a (#6856) — catalogue de l'outil d'ÉCRITURE LEAVE (BC-06) déclaré au
 * contrat A3 (BC-23, #6850, EPIC #6846). Premier outil `write` du programme
 * B : exécuté UNIQUEMENT après confirmation humaine (flux A4,
 * POST /ai/actions/{id}/confirm|reject) — jamais par le seul tool_call.
 *
 * `absence_decision` approuve ou refuse une demande d'absence (refus motivé)
 * via les Actions canoniques Planning (ApproveAbsence / RejectAbsence →
 * AbsenceService, propriétaire du domaine absence/congé, PA2-ARCH-002) —
 * mêmes règles de statut/transition, mêmes événements métier
 * (AbsenceApproved / AbsenceRejected) et même journal d'audit que les
 * endpoints REST `POST|PUT /api/v1/absences/{absence}/approve|reject`
 * (module façade Absence). RBAC : manager du tenant (permission
 * `absences.approve`, parité AbsenceController) ; isolation tenant fail-closed.
 *
 * Enregistrée par AbsenceServiceProvider::boot() dans
 * AIToolDefinitionRegistry ; l'hôte BC-23 (ToolRegistry) enrichit l'entrée
 * `ai_tool_registry` homonyme sans changer son comportement (tranche A3).
 */
final class AbsenceDecisionToolCatalog implements AIToolCatalog
{
    /**
     * @return list<AIToolDefinition>
     */
    public static function definitions(): array
    {
        return [
            new AIToolDefinition(
                name: 'absence_decision',
                description: "Décision sur une demande d'absence : l'approuver (statut approuvé, solde déduit) ou la refuser avec un motif (statut refusé). Réservé aux managers. Action sensible : confirmation obligatoire avant exécution.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'absence_id' => [
                            'type' => 'integer',
                            'description' => "Identifiant de la demande d'absence à traiter.",
                        ],
                        'decision' => [
                            'type' => 'string',
                            'enum' => ['approve', 'reject'],
                            'description' => "Décision : 'approve' approuve la demande, 'reject' la refuse.",
                        ],
                        'reason' => [
                            'type' => 'string',
                            'description' => "Motif du refus — obligatoire quand decision vaut 'reject' (max 1000 caractères).",
                        ],
                    ],
                    'required' => ['absence_id', 'decision'],
                ],
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'absence_id' => ['type' => 'integer'],
                        'status' => [
                            'type' => 'string',
                            'enum' => ['approved', 'rejected'],
                        ],
                        'approved_by' => [
                            'type' => 'integer',
                            'description' => 'Identifiant du manager approbateur (décision approve).',
                        ],
                        'rejected_reason' => [
                            'type' => 'string',
                            'description' => 'Motif du refus (décision reject).',
                        ],
                    ],
                ],
                permission: 'absences.approve',
                sensitivity: AIToolSensitivity::Write,
                bc: 'BC-06',
                version: 1,
            ),
        ];
    }
}
