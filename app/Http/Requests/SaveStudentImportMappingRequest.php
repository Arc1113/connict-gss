<?php

namespace App\Http\Requests;

use App\Models\StudentImport;
use App\Support\StudentImportLimits;
use Illuminate\Foundation\Http\FormRequest;

class SaveStudentImportMappingRequest extends FormRequest
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
            'mapping' => [
                'required',
                'array',
                'max:'.StudentImportLimits::MAX_COLUMNS,
            ],
            'mapping.*' => ['nullable', 'string', 'max:64'],
        ];
    }
}
