<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * BC-27 SHOWCASE (#6866) — réordonnancement des sections (POST bulk).
 *
 * `ids` = ordre cible complet : exactement les sections existantes de la
 * vitrine, sans doublon (contrat vérifié par ReorderShowcaseSectionsAction
 * → 422 sinon).
 */
final class ReorderShowcaseSectionsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // CompanyShowcasePolicy::update() tranche (contrôleur)
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['required', 'integer', 'distinct'],
        ];
    }
}
