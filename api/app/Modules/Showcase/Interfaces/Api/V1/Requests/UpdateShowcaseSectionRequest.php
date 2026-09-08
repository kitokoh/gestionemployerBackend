<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use App\Modules\Showcase\Domain\Support\ShowcaseSectionContentValidator;
use App\Modules\Showcase\Domain\Support\ShowcaseSectionSchemas;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Mise à jour d'une section de vitrine (BC-27 SHOWCASE, #6866).
 *
 * Le type d'une section existante est immuable (le contenu reste validé
 * contre le schéma de son type) ; `content` et `position` sont modifiables.
 */
class UpdateShowcaseSectionRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'content' => ['sometimes', 'array'],
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

            if (! $this->has('content')) {
                return;
            }

            $schemaVersionInput = $this->input('schema_version', ShowcaseSectionSchemas::VERSION);
            if (! is_int($schemaVersionInput)) {
                $validator->errors()->add('schema_version', 'Invalid schema_version.');

                return;
            }

            if ($schemaVersionInput !== ShowcaseSectionSchemas::VERSION) {
                $validator->errors()->add(
                    'schema_version',
                    "Unsupported schema version {$schemaVersionInput} (current: ".ShowcaseSectionSchemas::VERSION.').'
                );

                return;
            }

            /** @var \App\Modules\Showcase\Domain\Models\ShowcaseSection $section */
            $section = $this->route('section');
            /** @var array<string, mixed> $content */
            $content = $this->input('content');
            $errors = (new ShowcaseSectionContentValidator)->validate($section->type, $content);
            foreach ($errors as $error) {
                $validator->errors()->add('content', $error);
            }
        });
    }
}
