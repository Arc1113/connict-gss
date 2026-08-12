<?php

namespace App\Http\Requests;

use App\Enums\CeremonyStatus;
use App\Models\StudentImport;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class RetargetStudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $studentImport = $this->route('studentImport');

        return $studentImport instanceof StudentImport
            && $this->user()?->can('updateMapping', $studentImport) === true;
    }

    public function rules(): array
    {
        return [
            'ceremony_id' => [
                'required',
                'integer',
                Rule::exists('ceremonies', 'id')->whereNotIn('status', [
                    CeremonyStatus::Completed->value,
                    CeremonyStatus::Archived->value,
                ]),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ceremony_id.required' => 'Select a destination ceremony.',
            'ceremony_id.exists' => 'Select an available destination ceremony.',
        ];
    }
}
