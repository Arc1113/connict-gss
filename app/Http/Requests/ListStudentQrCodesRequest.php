<?php

namespace App\Http\Requests;

use App\Enums\EligibilityStatus;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListStudentQrCodesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('viewQrCodes', Student::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ceremony_id' => ['nullable', 'integer', 'exists:ceremonies,id'],
            'college' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
            'eligibility_status' => [
                'nullable',
                Rule::enum(EligibilityStatus::class),
            ],
            'qr_readiness' => [
                'nullable',
                Rule::in(['ready', 'regenerated']),
            ],
            'sequence_from' => [
                'nullable',
                'integer',
                'min:1',
                'max:4294967295',
            ],
            'sequence_to' => [
                'nullable',
                'integer',
                'min:1',
                'max:4294967295',
                'gte:sequence_from',
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $values = [];

        foreach ([
            'ceremony_id',
            'college',
            'program',
            'eligibility_status',
            'qr_readiness',
            'sequence_from',
            'sequence_to',
        ] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim($value);
                $values[$field] = $value === '' ? null : $value;
            }
        }

        $this->merge($values);
    }
}
