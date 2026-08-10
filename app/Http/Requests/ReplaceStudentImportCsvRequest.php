<?php

namespace App\Http\Requests;

use App\Models\StudentImport;
use App\Support\StudentImportLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ReplaceStudentImportCsvRequest extends FormRequest
{
    public function authorize(): bool
    {
        $studentImport = $this->route('studentImport');

        return $studentImport instanceof StudentImport
            && $this->user()?->can('updateMapping', $studentImport) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'csv_file' => [
                'required',
                File::types(['csv', 'txt'])
                    ->max(StudentImportLimits::MAX_FILE_KILOBYTES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'csv_file.required' => 'Select the corrected CSV file.',
        ];
    }
}
