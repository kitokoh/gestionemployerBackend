<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Api\V1\Requests;

use App\Modules\Catalog\Domain\Enums\CatalogInquiryStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Action back-office sur une demande de devis B2B (BC-28 CATALOG,
 * C-BACKOFFICE #6885) : transition de statut (whitelist spec §7,
 * matrice validée dans le contrôleur via CatalogInquiryStatus) + note
 * interne optionnelle (préfixée d'un horodatage à l'append).
 */
class UpdateCatalogInquiryStatusRequest extends FormRequest
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
        $statuses = array_map(
            static fn (CatalogInquiryStatus $s): string => $s->value,
            CatalogInquiryStatus::cases()
        );

        return [
            'status' => ['required', Rule::in($statuses)],
            'note' => ['nullable', 'string', 'max:2000'],
        ];
    }
}
