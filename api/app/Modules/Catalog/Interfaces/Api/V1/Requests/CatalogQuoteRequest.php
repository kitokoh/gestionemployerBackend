<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Demande de devis/contact B2B sur le catalogue public (BC-28, #6884).
 *
 * Champs du formulaire public : produit concerné (slug), quantité, société
 * acheteuse, contact, email, message. Anti-spam : honeypot `website`
 * (champ caché — rempli = bot → succès factice, aucun traitement) + rate
 * limit `shop-public` sur la route. RGPD : `consent_processing` explicite
 * OBLIGATOIRE (refus → 422). Aucune donnée d'acheteur exposée publiquement.
 */
class CatalogQuoteRequest extends FormRequest
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
            'product_slug' => ['required', 'string', 'max:160'],
            'quantity' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'buyer_company' => ['required', 'string', 'max:255'],
            'contact_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email:rfc', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'message' => ['nullable', 'string', 'max:5000'],
            // RGPD (#6884) : consentement explicite requis pour traiter la demande.
            'consent_processing' => ['required', 'accepted'],
            // Honeypot anti-spam : champ caché que les humains laissent vides.
            'website' => ['nullable', 'string', 'max:255'],
        ];
    }
}
