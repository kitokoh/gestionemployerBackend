<?php

namespace App\Http\Requests\Api\V1\Attendance;

use App\Models\AttendanceLog;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Validator;

class UpdateAttendanceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'check_in' => ['sometimes', 'nullable', 'date'],
            'check_out' => ['sometimes', 'nullable', 'date'],
            'status' => ['sometimes', 'nullable', 'in:ontime,late,absent,incomplete'],
            'correction_note' => ['required', 'string', 'max:1000'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            /** @var AttendanceLog|null $attendance */
            $attendance = $this->route('attendance');

            if (! $this->hasAny(['check_in', 'check_out', 'status'])) {
                $validator->errors()->add('check_in', 'Au moins un champ de correction doit etre fourni.');

                return;
            }

            $resolvedCheckIn = $this->filled('check_in')
                ? Carbon::parse($this->input('check_in'))
                : $attendance?->check_in;

            $resolvedCheckOut = $this->filled('check_out')
                ? Carbon::parse($this->input('check_out'))
                : $attendance?->check_out;

            if ($resolvedCheckOut && ! $resolvedCheckIn) {
                $validator->errors()->add('check_in', 'Un pointage d entree est requis avant de definir la sortie.');
            }

            if ($resolvedCheckIn && $resolvedCheckOut && $resolvedCheckOut->lessThanOrEqualTo($resolvedCheckIn)) {
                $validator->errors()->add('check_out', 'L heure de sortie doit etre posterieure a l heure d entree.');
            }
        });
    }
}
