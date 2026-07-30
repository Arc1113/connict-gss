<?php

namespace App\Http\Requests;

use App\Models\StudentImport;
use Illuminate\Foundation\Http\FormRequest;

class ConfirmStudentImportRequest extends FormRequest
{
    public function authorize(): bool
    {
        $studentImport = $this->route('studentImport');

        return $studentImport instanceof StudentImport
            && $this->user()?->can('confirm', $studentImport) === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'confirmed' => ['accepted'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Confirm that you reviewed the ceremony, mapping, warnings, and row count.',
        ];
    }
}
