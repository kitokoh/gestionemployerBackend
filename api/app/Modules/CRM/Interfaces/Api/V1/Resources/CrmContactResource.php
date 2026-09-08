<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmContact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #6977 — Sérialisation d'un contact CRM (répertoire tenant).
 *
 * Contrat liste `GET /crm/contacts` (#5712) — consommé par le dashboard
 * client web (`crm/contacts`). Le compte parent est embarqué de façon
 * minimale (id + nom) quand la relation est chargée.
 *
 * @property CrmContact $resource
 */
class CrmContactResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $contact = $this->resource;

        return [
            'id' => $contact->id,
            'first_name' => $contact->first_name,
            'last_name' => $contact->last_name,
            'email' => $contact->email,
            'phone' => $contact->phone,
            'account_id' => $contact->account_id,
            'account' => $contact->relationLoaded('account')
                ? [
                    'id' => $contact->account?->id,
                    'name' => $contact->account?->name,
                ]
                : null,
            'created_at' => $contact->created_at?->toIso8601String(),
        ];
    }
}
