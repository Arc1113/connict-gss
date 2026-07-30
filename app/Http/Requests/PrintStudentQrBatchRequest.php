<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class PrintStudentQrBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('printQrCodes', Student::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ceremony_id' => ['required', 'integer', 'exists:ceremonies,id'],
            'student_ids' => ['required', 'array', 'min:1', 'max:100'],
            'student_ids.*' => ['required', 'integer', 'min:1'],
            'college' => ['nullable', 'string', 'max:255'],
            'program' => ['nullable', 'string', 'max:255'],
            'eligibility_status' => ['nullable', 'string', 'max:24'],
            'qr_readiness' => ['nullable', 'string', 'max:24'],
            'sequence_from' => ['nullable', 'integer', 'min:1'],
            'sequence_to' => ['nullable', 'integer', 'min:1'],
        ];
    }

    public function messages(): array
    {
        return [
            'student_ids.required' => 'Select at least one graduate to print.',
            'student_ids.min' => 'Select at least one graduate to print.',
            'student_ids.max' => 'A QR batch can contain at most 100 graduates.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $ids = $this->input('student_ids');

        if (is_array($ids)) {
            $this->merge([
                'student_ids' => array_values(array_unique($ids, SORT_REGULAR)),
            ]);
        }
    }
}
