<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Core\Tenant\Domain\Models\Company;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Application\Actions\SubmitCatalogQuote;
use App\Modules\Catalog\Domain\Support\CatalogFeatures;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\CatalogQuoteRequest;
use Illuminate\Http\JsonResponse;

/**
 * Demandes de devis/contact B2B sur le catalogue public (BC-28, #6884).
 *
 * SANS auth : le tenant est résolu par slug d'entreprise (pattern
 * CatalogPublicController #6882). Anti-spam : honeypot (`website` rempli =
 * succès factice sans traitement) + throttle `shop-public` sur la route.
 * RGPD : consentement explicite requis (422 sinon).
 */
class CatalogPublicQuoteController extends Controller
{
    public function __construct(private readonly SubmitCatalogQuote $submitQuote) {}

    /**
     * POST /api/v1/public/catalog/{companySlug}/leads
     */
    public function store(CatalogQuoteRequest $request, string $companySlug): JsonResponse
    {
        $company = $this->resolveCompany($companySlug);

        // Honeypot : un robot a rempli le champ caché → succès factice,
        // aucune donnée traitée, aucune fuite (réponse identique au succès).
        if (is_string($request->input('website')) && $request->input('website') !== '') {
            return $this->received('00000000-0000-0000-0000-000000000000');
        }

        $result = $this->submitQuote->execute($company, $request->validated());

        return $this->received((string) $result['reference']);
    }

    private function received(string $reference): JsonResponse
    {
        return response()->json([
            'data' => [
                'status' => 'received',
                'reference' => $reference,
            ],
        ], 201);
    }

    private function resolveCompany(string $companySlug): Company
    {
        $company = Company::query()
            ->where('slug', $companySlug)
            ->where('status', '!=', 'suspended')
            ->first();

        if ($company === null) {
            abort(404);
        }

        if (! $company->hasFeature(CatalogFeatures::B2B_CATALOG)) {
            abort(404);
        }

        return $company;
    }
}
