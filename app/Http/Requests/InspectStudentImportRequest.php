<?php

namespace App\Http\Requests;

use App\Enums\CeremonyStatus;
use App\Models\StudentImport;
use App\Support\StudentImportLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class InspectStudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', StudentImport::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'ceremony_id' => [
                'required',
                'integer',
                Rule::exists('ceremonies', 'id')
                    ->whereNotIn('status', [
                        CeremonyStatus::Completed->value,
                        CeremonyStatus::Archived->value,
                    ]),
            ],
            'csv_file' => [
                'required',
                File::types(['csv', 'txt'])
                    ->max(StudentImportLimits::MAX_FILE_KILOBYTES),
            ],
            'media_package' => [
                'nullable',
                File::types(['zip'])
                    ->max(StudentImportLimits::MAX_MEDIA_PACKAGE_KILOBYTES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'ceremony_id.exists' => 'Select an available destination ceremony.',
            'csv_file.required' => 'Select a CSV file to inspect.',
            'media_package.mimes' => 'The media package must be a ZIP file.',
        ];
    }
}
