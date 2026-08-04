<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;

class ManageStudentAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('student') instanceof Student
            && $this->user()?->can('manage-student-awards') === true;
    }

    public function rules(): array
    {
        return [];
    }
}
