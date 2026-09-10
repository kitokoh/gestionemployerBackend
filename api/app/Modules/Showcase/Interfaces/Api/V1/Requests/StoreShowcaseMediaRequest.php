<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use App\Modules\Showcase\Domain\Models\ShowcaseMedia;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BC-27 SHOWCASE (#6872 V-MEDIA) — upload d'un média de vitrine.
 *
 * Validation type/taille : PNG/JPEG/WebP partout, SVG toléré pour le seul
 * logo de marque (vectoriel) ; poids borné à 2 Mo (logo) / 5 Mo (image de
 * section). L'autorisation RBAC est tranchée par le contrôleur
 * (CompanyShowcasePolicy::update).
 *
 * Le nom d'origine n'est jamais utilisé comme chemin (sanitisation côté
 * service) ; le média est référencé par son `uuid` stable.
 */
final class StoreShowcaseMediaRequest extends FormRequest
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
        $isLogo = $this->input('kind') === ShowcaseMedia::KIND_LOGO;

        $mimes = $isLogo
            ? 'png,jpg,jpeg,webp,svg'
            : 'png,jpg,jpeg,webp';

        $maxKilobytes = $isLogo ? 2048 : 5120;

        return [
            'kind' => ['required', 'string', Rule::in([ShowcaseMedia::KIND_LOGO, ShowcaseMedia::KIND_IMAGE])],
            'section_id' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'file' => ['required', 'file', 'max:'.$maxKilobytes, 'mimes:'.$mimes],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'file.mimes' => (string) __('showcase.media_invalid_type'),
            'file.max' => (string) __('showcase.media_too_large'),
        ];
    }
}
