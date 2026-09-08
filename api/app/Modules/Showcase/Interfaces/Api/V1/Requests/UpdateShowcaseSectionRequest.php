<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BC-27 SHOWCASE (#6866) — mise à jour d'une section (PATCH partiel).
 *
 * `type` optionnel (absent → type inchangé) ; si présent, le `content`
 * est re-validé contre le schéma du nouveau type par l'Action.
 * `content` optionnel pour une PATCH d'un seul champ — mais s'il est
 * fourni, il remplace intégralement le contenu (validé ensuite).
 */
final class UpdateShowcaseSectionRequest extends FormRequest
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
            'type' => ['sometimes', 'required', 'string', Rule::in(ShowcaseSectionType::v1())],
            'content' => ['sometimes', 'required', 'array'],
            'schema_version' => ['sometimes', 'integer', 'min:1', 'max:'.ShowcaseSectionSchemaRegistry::SCHEMA_VERSION],
        ];
    }
}
