<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Soumission du formulaire de contact public d'une vitrine (BC-27 SHOWCASE,
 * #6875 V-RGPD).
 *
 * Anti-spam : honeypot `company_website` (champ caché que les bots
 * remplissent — le contrôleur répond 201 factice SANS persister) + rate
 * limit dédié `throttle:showcase-contact` (route). Consentement RGPD
 * explicite requis (`consent` = accepted) — horodaté en base
 * (`consent_at`). Minimisation : champs bornés, aucun champ interne.
 */
class StoreShowcaseContactRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
            'consent' => ['required', 'accepted'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'consent.accepted' => (string) __('showcase.contact_consent_required'),
        ];
    }
}
