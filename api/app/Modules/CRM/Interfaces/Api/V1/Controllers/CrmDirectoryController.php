<?php

declare(strict_types=1);

namespace App\Modules\CRM\Interfaces\Api\V1\Controllers;

use App\Core\Auth\Domain\Models\Employee;
use App\Http\Controllers\Controller;
use App\Modules\CRM\Domain\Models\CrmAccount;
use App\Modules\CRM\Domain\Models\CrmContact;
use App\Modules\CRM\Domain\Models\CrmLead;
use App\Modules\CRM\Domain\Models\CrmOpportunity;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmAccountResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmContactResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmLeadResource;
use App\Modules\CRM\Interfaces\Api\V1\Resources\CrmOpportunityResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * Répertoire CRM client (lectures paginées tenant) — issues #5712/#6977.
 *
 * Endpoints de liste consommés par le dashboard client web (`/crm/leads`,
 * `/crm/accounts`, `/crm/contacts`, `/crm/pipeline`) : l'UI #5715 a été
 * livrée contre ce contrat, les routes manquaient au backend. Lecture =
 * managers du tenant (middleware `api.manager`), isolation tenant
 * fail-closed (scoping `company_id`), données non archivées uniquement.
 * ADR-CRM-002 : strictement séparé du CRM commercial plateforme.
 */
class CrmDirectoryController extends Controller
{
    public function leads(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return CrmLeadResource::collection(
            CrmLead::query()
                ->where('company_id', $actor->company_id)
                ->whereNull('converted_at')
                ->orderByDesc('created_at')
                ->paginate($this->perPage($request, 25))
        );
    }

    public function accounts(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return CrmAccountResource::collection(
            CrmAccount::query()
                ->where('company_id', $actor->company_id)
                ->whereNull('archived_at')
                ->orderBy('name')
                ->paginate($this->perPage($request, 25))
        );
    }

    public function contacts(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return CrmContactResource::collection(
            CrmContact::query()
                ->with(['account:id,name' => fn ($query) => $query->where('company_id', $actor->company_id)])
                ->where('company_id', $actor->company_id)
                ->whereNull('archived_at')
                ->orderByDesc('created_at')
                ->paginate($this->perPage($request, 25))
        );
    }

    public function opportunities(Request $request): AnonymousResourceCollection
    {
        /** @var Employee $actor */
        $actor = $request->user();

        return CrmOpportunityResource::collection(
            CrmOpportunity::query()
                ->where('company_id', $actor->company_id)
                ->orderByDesc('created_at')
                ->paginate($this->perPage($request, 100))
        );
    }

    private function perPage(Request $request, int $default): int
    {
        return max(1, min(100, $request->integer('per_page', $default)));
    }
}
