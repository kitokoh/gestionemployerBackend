<?php

declare(strict_types=1);

namespace App\Modules\Notification\Domain\Support;

use App\AI\Support\AIToolDefinition;
use App\AI\Support\AIToolSensitivity;

/**
 * B3c (#6858) — catalogue de l'outil d'envoi COMMS (BC-13) déclaré au
 * contrat A3 (BC-23, #6850, EPIC #6846) : `notify_team` notifie une équipe
 * (message court du responsable) via le système canonique d'annonces
 * (`CompanyAnnouncement` + `AnnouncementService::publish`), qui fan-out vers
 * les destinataires résolus (préférences et canaux BC-13 respectés).
 *
 * Sensibilité `send` → exécution UNIQUEMENT après confirmation humaine
 * (flux A4, POST /ai/actions/{id}/confirm|reject) — jamais par le seul
 * tool_call, et bornée par un plafond anti-spam par acteur et par heure.
 *
 * RBAC en parité exacte avec l'endpoint REST canonique
 * `POST /api/v1/announcements` (AnnouncementController::store +
 * authorizeAudience) : manager du tenant ; audience `company` réservée aux
 * rôles principal/RH ; audience `department` bornée à son propre département
 * pour un manager de département (principal/RH : tout département).
 *
 * Enregistrée par NotificationServiceProvider::boot() dans
 * AIToolDefinitionRegistry ; l'hôte BC-23 (ToolRegistry) enrichit l'entrée
 * `ai_tool_registry` homonyme sans changer son comportement (tranche A3).
 */
final class NotifyTeamToolCatalog
{
    /**
     * @return list<AIToolDefinition>
     */
    public static function definitions(): array
    {
        return [
            new AIToolDefinition(
                name: 'notify_team',
                description: "Envoie un message court à une équipe du tenant : toute l'entreprise (réservé principal/RH) ou un département (manager du département). Chaque destinataire reçoit une notification dans l'application. Action sensible : confirmation obligatoire avant envoi, plafond anti-spam par heure.",
                inputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'title' => [
                            'type' => 'string',
                            'description' => 'Titre du message (max 200 caractères).',
                        ],
                        'message' => [
                            'type' => 'string',
                            'description' => 'Contenu du message (max 5000 caractères).',
                        ],
                        'audience_type' => [
                            'type' => 'string',
                            'enum' => ['company', 'department'],
                            'description' => "Cible : 'company' (toute l'entreprise, principal/RH uniquement) ou 'department' (défaut, un département).",
                        ],
                        'department_id' => [
                            'type' => 'integer',
                            'description' => "Identifiant du département ciblé — requis quand audience_type vaut 'department'.",
                        ],
                    ],
                    'required' => ['title', 'message'],
                ],
                outputSchema: [
                    'type' => 'object',
                    'properties' => [
                        'announcement_id' => ['type' => 'integer'],
                        'status' => ['type' => 'string', 'enum' => ['published']],
                        'audience_type' => ['type' => 'string'],
                        'recipients_count' => ['type' => 'integer'],
                    ],
                ],
                permission: 'announcements.create',
                sensitivity: AIToolSensitivity::Send,
                bc: 'BC-13',
                version: 1,
            ),
        ];
    }
}
