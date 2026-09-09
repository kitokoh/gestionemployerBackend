<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Requests;

use App\Modules\Catalog\Domain\Enums\CatalogProductStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Soumission du formulaire public « Demander un devis » (BC-28 CATALOG,
 * C-LEAD #6884).
 *
 * Anti-spam : honeypot `company_website` (champ caché que les bots
 * remplissent — le contrôleur répond 201 factice SANS persister) + rate
 * limit `throttle:shop-public` (route). Consentement RGPD explicite requis
 * (`consent` = accepted) — horodaté en base (`consent_at`). Le produit
 * référencé doit exister ET être publié (draft → 422, fail-closed).
 */
class StoreCatalogInquiryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            // Honeypot : ce champ n'existe pas dans le formulaire visible.
            'company_website' => ['nullable', 'string', 'max:500'],
            'product_slug' => [
                'required',
                'string',
                'max:160',
                Rule::exists('catalog_products', 'slug')
                    ->where('status', CatalogProductStatus::Published->value),
            ],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000000'],
            'company_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['nullable', 'string', 'max:5000'],
            'consent' => ['required', 'accepted'],
        ];
    }
}
