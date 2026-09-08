<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use App\Modules\Showcase\Domain\Enums\ShowcaseSectionType;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionContentValidator;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Création d'une section de vitrine (BC-27 SHOWCASE, #6866).
 *
 * Le `content` est validé contre le JSON Schema versionné du `type`
 * (ShowcaseSectionSchemas) — clés inconnues refusées, longueurs/tailles
 * bornées. La position est facultative (fin de page par défaut).
 */
class StoreShowcaseSectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', 'string', Rule::enum(ShowcaseSectionType::class)],
            'content' => ['required', 'array'],
            'schema_version' => ['sometimes', 'integer', 'min:1', 'max:'.ShowcaseSectionSchemas::VERSION],
            'position' => ['sometimes', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $type = $this->input('type');
            $schemaVersionInput = $this->input('schema_version', ShowcaseSectionSchemas::VERSION);
            if (! is_string($type) || ! is_int($schemaVersionInput)) {
                $validator->errors()->add('content', 'Invalid type or schema_version.');

                return;
            }

            if ($schemaVersionInput !== ShowcaseSectionSchemas::VERSION) {
                $validator->errors()->add(
                    'schema_version',
                    "Unsupported schema version {$schemaVersionInput} (current: ".ShowcaseSectionSchemas::VERSION.').'
                );

                return;
            }

            /** @var array<string, mixed> $content */
            $content = $this->input('content');
            $errors = (new ShowcaseSectionContentValidator)->validate($type, $content);
            foreach ($errors as $error) {
                $validator->errors()->add('content', $error);
            }
        });
    }
}
