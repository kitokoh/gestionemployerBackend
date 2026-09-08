<?php

declare(strict_types=1);

namespace App\Modules\Showcase\Interfaces\Api\V1\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

/**
 * Réordonnancement en bloc des sections d'une vitrine (BC-27 SHOWCASE,
 * #6866). Payload : `sections: [{ id, position }]` — la liste doit couvrir
 * toutes les sections de la vitrine (positions 0..n-1, uniques).
 */
class ReorderShowcaseSectionsRequest extends FormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'sections' => ['required', 'array', 'min:1'],
            'sections.*.id' => ['required', 'integer', 'min:1'],
            'sections.*.position' => ['required', 'integer', 'min:0'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }

            $rows = $this->input('sections');
            if (! is_array($rows)) {
                $validator->errors()->add('sections', 'Invalid reorder payload.');

                return;
            }

            /** @var list<array{id: int, position: int}> $sections */
            $sections = $rows;

            $ids = array_map(static fn (array $row): int => $row['id'], $sections);
            if (count($ids) !== count(array_unique($ids))) {
                $validator->errors()->add('sections', 'Duplicate section ids in reorder payload.');
            }

            $positions = array_map(static fn (array $row): int => $row['position'], $sections);
            $expected = range(0, count($positions) - 1);
            sort($positions);
            if ($positions !== $expected) {
                $validator->errors()->add('sections', 'Positions must be a contiguous 0..n-1 sequence.');
            }
        });
    }
}
