<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class MoveStudentAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('student') instanceof Student
            && $this->user()?->can('manage-student-awards') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'direction' => ['required', Rule::in(['up', 'down'])],
        ];
    }
}
