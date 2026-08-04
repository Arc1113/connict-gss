<?php

namespace App\Http\Requests;

use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class SaveStudentAwardRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            && $this->user()?->can('manage-student-awards') === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'award_name' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'spoken_text' => ['nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
            'is_announcement_enabled' => ['required', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $spokenText = $this->input('spoken_text');

            if (! is_string($spokenText) || $spokenText === '') {
                return;
            }

            if (preg_match('/^[,;]|[.!?;:]$/u', $spokenText) === 1) {
                $validator->errors()->add(
                    'spoken_text',
                    'The spoken phrase must be list-ready without leading or trailing punctuation.',
                );
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        foreach (['award_name', 'spoken_text'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
