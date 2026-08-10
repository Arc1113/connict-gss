<?php

namespace App\Http\Requests;

use App\Models\StudentImport;
use App\Support\StudentImportLimits;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rules\File;

class ReplaceStudentImportMediaPackageRequest extends FormRequest
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
            'media_package' => [
                'required',
                File::types(['zip'])
                    ->max(StudentImportLimits::MAX_MEDIA_PACKAGE_KILOBYTES),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'media_package.required' => 'Select the corrected ZIP file.',
            'media_package.mimes' => 'The media package must be a ZIP file.',
        ];
    }
}
