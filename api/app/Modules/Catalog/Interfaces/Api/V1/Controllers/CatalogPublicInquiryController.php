<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Controllers;

use App\Events\CatalogInquiryReceived;
use App\Http\Controllers\Controller;
use App\Modules\Catalog\Domain\Enums\CatalogInquiryStatus;
use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use App\Modules\Catalog\Domain\Models\CatalogProduct;
use App\Modules\Catalog\Interfaces\Api\V1\Requests\StoreCatalogInquiryRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

/**
 * Formulaire public « Demander un devis » du catalogue B2B (BC-28 CATALOG,
 * C-LEAD #6884).
 *
 * `POST /v1/public/catalog/{companySlug}/inquiries` — SANS auth (route
 * isolée, `throttle:shop-public` + `catalog.public` ; le tenant est résolu
 * par slug, 404 fail-closed, contexte tenant déjà posé par le middleware).
 *
 * Flux spec §7 : honeypot (201 factice, rien n'est persisté) → validation
 * stricte (produit publié exigé) → stockage `catalog_inquiries`
 * (minimisation RGPD, consentement horodaté, conservation bornée) →
 * événement `CatalogInquiryReceived` (contrat cross-BC : lead CRM BC-11 +
 * notification tenant BC-13) → accusé de réception acheteur (sans
 * engagement). Aucune donnée acheteur n'est jamais exposée publiquement.
 */
class CatalogPublicInquiryController extends Controller
{
    public function store(StoreCatalogInquiryRequest $request): JsonResponse
    {
        // Honeypot anti-spam : un bot qui remplit le champ caché reçoit un
        // accusé factice — aucun lead n'est persisté, le bot ne sait pas
        // qu'il est détecté.
        if ($request->filled('company_website')) {
            return new JsonResponse(['data' => ['status' => 'received']], 201);
        }

        // Re-vérification fail-closed produit publié (la validation a déjà
        // couru, mais le produit peut avoir été dépublié entre-temps).
        $product = CatalogProduct::query()
            ->where('slug', (string) $request->validated('product_slug'))
            ->where('status', CatalogProductStatus::Published->value)
            ->first();

        if (! $product instanceof CatalogProduct) {
            return new JsonResponse(['error' => 'PRODUCT_NOT_PUBLISHED'], 422);
        }

        $company = currentCompany();
        $consentAt = now();
        $retentionDays = (int) config('catalog.inquiry_retention_days', 90);
        $ip = $request->ip();

        $inquiry = DB::transaction(function () use (
            $company,
            $product,
            $request,
            $consentAt,
            $retentionDays,
            $ip
        ): CatalogInquiry {
            /** @var CatalogInquiry $inquiry */
            $inquiry = CatalogInquiry::query()->create([
                'company_id' => (string) $company->id,
                'product_slug' => (string) $product->slug,
                'product_name' => (string) $product->name,
                'quantity' => $request->validated('quantity'),
                'company_name' => (string) $request->validated('company_name'),
                'email' => (string) $request->validated('email'),
                'message' => $request->validated('message'),
                'status' => CatalogInquiryStatus::New->value,
                'consent_at' => $consentAt,
                'retention_until' => $consentAt->copy()->addDays($retentionDays)->toDateString(),
                'ip_hash' => is_string($ip) && $ip !== '' && $ip !== '127.0.0.1'
                    ? hash('sha256', $ip)
                    : null,
            ]);

            return $inquiry;
        });

        // Contrat cross-BC : le lead CRM BC-11 et la notification tenant
        // BC-13 sont créés par les listeners (même requête, contexte tenant
        // posé — jamais d'import cross-BC direct depuis Catalog).
        event(new CatalogInquiryReceived((string) $company->id, $inquiry));

        return new JsonResponse([
            'data' => [
                'status' => 'received',
                'reference' => (int) $inquiry->id,
            ],
        ], 201);
    }
}
