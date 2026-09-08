<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Resources;

use App\Modules\CRM\Domain\Models\CrmAccount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * #6977 — Sérialisation d'un compte CRM (répertoire tenant).
 *
 * Contrat liste `GET /crm/accounts` (#5712) — consommé par le dashboard
 * client web (`crm/accounts`). PII protégée : email/téléphone exposés dans
 * le périmètre autorisé (lecture manager tenant, cf. #5713).
 *
 * @property CrmAccount $resource
 */
class CrmAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $account = $this->resource;

        return [
            'id' => $account->id,
            'name' => $account->name,
            'status' => $account->status,
            'email' => $account->email,
            'phone' => $account->phone,
            'owner_id' => $account->owner_id,
            'created_at' => $account->created_at?->toIso8601String(),
        ];
    }
}
