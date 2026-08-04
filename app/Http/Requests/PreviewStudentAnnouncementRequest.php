<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewStudentAnnouncementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            && $this->user()?->can('view', $student) === true
            && $this->user()?->can('generate-announcement-candidates') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'announcement_template_id' => ['nullable', 'integer', 'exists:announcement_templates,id'],
            'voice' => ['nullable', 'string', Rule::in(array_keys((array) config('announcements.voices', [])))],
        ];
    }
}
