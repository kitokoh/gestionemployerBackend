<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemaRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * BC-27 SHOWCASE (#6866) — création d'une section.
 *
 * Le `content` est un objet JSON validé sémantiquement par l'Action
 * (ShowcaseSectionSchemaValidator, JSON Schema du type) après cette
 * validation de forme — les erreurs de schéma remontent en 422 standard.
 */
final class StoreShowcaseSectionRequest extends FormRequest
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
            'type' => ['required', 'string', Rule::in(ShowcaseSectionType::v1())],
            'content' => ['required', 'array'],
            // #6874 — surcouches de contenu par locale (fr/en/ar/tr) ; le
            // détail (locale supportée, forme du contenu) est validé par
            // ShowcaseSectionTranslationValidator → 422 indexé.
            'translations' => ['sometimes', 'array'],
            'schema_version' => ['sometimes', 'integer', 'min:1', 'max:'.ShowcaseSectionSchemaRegistry::SCHEMA_VERSION],
        ];
    }
}
