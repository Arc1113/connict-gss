<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class RegenerateStudentQrCodeRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            && $this->user()?->can('regenerateQrCode', $student) === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'confirmed' => ['accepted'],
            'expected_version' => ['required', 'string', 'size:64'],
        ];
    }

    public function messages(): array
    {
        return [
            'confirmed.accepted' => 'Confirm that previously printed QR codes will stop working.',
            'expected_version.required' => 'Reload the QR page before replacing this token.',
        ];
    }
}
