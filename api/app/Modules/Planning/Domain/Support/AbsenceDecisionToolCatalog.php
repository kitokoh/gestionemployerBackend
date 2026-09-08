<?php

declare(strict_types=1);

namespace App\Modules\Planning\Domain\Support;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolSensitivity;

/**
 * B3a (#6856) — catalogue des outils d'ÉCRITURE du domaine absence (BC-06
 * LEAVE, EPIC #6846) déclarés au contrat A3 (#6850).
 *
 * Premier outil `write` livré pour le domaine absence : `absence_decision`
 * (approuver/refuser une demande en attente, motif obligatoire pour un
 * refus), déclaré par le module propriétaire du modèle canonique (Planning,
 * PA2-ARCH-002) et enregistré au boot par PlanningServiceProvider dans
 * AIToolDefinitionRegistry.
 *
 * Sensibilité `write` : l'hôte BC-23 ne l'exécute JAMAIS sur le seul
 * tool_call — la confirmation humaine (flux A4,
 * POST /ai/actions/{id}/confirm|reject) est requise, et l'exécution passe
 * par les cas d'usage canoniques Planning (ApproveAbsence / RejectAbsence →
 * AbsenceService) : transitions d'état, événements métier (AbsenceApproved /
 * AbsenceRejected), verrou ligne et revalidation du solde (#2666) — aucune
 * logique dupliquée côté hôte.
 */
final class AbsenceDecisionToolCatalog
{
    /**
     * @return list<AIToolDefinition>
     */
    public static function definitions(): array
    {
        return [
            new AIToolDefinition(
                name: 'absence_decision',
                description: "Prendre une décision sur une demande d'absence en attente : l'approuver ou la refuser (un motif est obligatoire pour refuser). Réservé aux managers ; l'action n'est exécutée qu'après confirmation explicite.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'absence_id' => [
                            'type' => 'integer',
                            'description' => "Identifiant de la demande d'absence concernée.",
                        ],
                        'decision' => [
                            'type' => 'string',
                            'enum' => ['approve', 'reject'],
                            'description' => "Décision à appliquer : 'approve' (approuver) ou 'reject' (refuser).",
                        ],
                        'reason' => [
                            'type' => 'string',
                            'maxLength' => 1000,
                            'description' => 'Motif de la décision — obligatoire pour un refus.',
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
                        'decision' => [
                            'type' => 'string',
                            'enum' => ['approve', 'reject'],
                        ],
                        'rejected_reason' => ['type' => 'string'],
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
