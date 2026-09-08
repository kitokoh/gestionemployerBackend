<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmOpportunity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/**
 * #6977 — Sérialisation d'une opportunité CRM (pipeline tenant).
 *
 * Contrat liste `GET /crm/opportunities` (#5712) — consommé par le
 * dashboard client web (`crm/pipeline`, vue kanban par stage).
 *
 * @property CrmOpportunity $resource
 */
class CrmOpportunityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $opportunity = $this->resource;
        // Colonne date nullable : normalisée en `Y-m-d` (la valeur peut être
        // un Carbon (cast modèle) ou une chaîne selon le chemin de lecture).
        $expectedCloseDate = $opportunity->expected_close_date;

        return [
            'id' => $opportunity->id,
            'name' => $opportunity->name,
            'stage' => $opportunity->stage,
            'pipeline_id' => $opportunity->pipeline_id,
            'owner_id' => $opportunity->owner_id,
            'status' => $opportunity->status,
            'amount' => $opportunity->amount,
            'currency' => $opportunity->currency,
            'expected_close_date' => $expectedCloseDate !== null
                ? Carbon::parse((string) $expectedCloseDate)->toDateString()
                : null,
            'created_at' => $opportunity->created_at?->toIso8601String(),
        ];
    }
}
