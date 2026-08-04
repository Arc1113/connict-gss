<?php

namespace App\Http\Requests;

use App\Enums\EligibilityStatus;
use App\Models\Student;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

class SaveStudentRequest extends FormRequest
{
    public function authorize(): bool
    {
        $student = $this->route('student');

        return $student instanceof Student
            ? $this->user()?->can('update', $student) === true
            : $this->user()?->can('create', Student::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $student = $this->route('student');
        $studentId = $student instanceof Student ? $student->id : null;

        return [
            'ceremony_id' => ['required', 'integer', 'exists:ceremonies,id'],
            'student_number' => [
                'required',
                'string',
                'max:64',
                Rule::unique('students', 'student_number')
                    ->where(fn ($query) => $query->where(
                        'ceremony_id',
                        $this->integer('ceremony_id'),
                    ))
                    ->ignore($studentId),
            ],
            'first_name' => ['required', 'string', 'max:255'],
            'middle_name' => ['nullable', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'suffix' => ['nullable', 'string', 'max:32'],
            'display_name' => ['required', 'string', 'max:255'],
            'announcement_name' => ['required', 'string', 'max:255'],
            'pronunciation_guide' => ['nullable', 'string', 'max:255'],
            'tts_spoken_name' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'college' => ['nullable', 'string', 'max:255'],
            'program' => ['required', 'string', 'max:255'],
            'major' => ['nullable', 'string', 'max:255'],
            'honors' => ['nullable', 'string', 'max:1000'],
            'announcement_additional_text' => ['nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
            'sequence_number' => ['required', 'integer', 'min:1', 'max:4294967295',
                Rule::unique('students', 'sequence_number')
                    ->where(fn ($query) => $query->where(
                        'ceremony_id',
                        $this->integer('ceremony_id'),
                    ))
                    ->ignore($studentId),
            ],
            'eligibility_status' => ['required', Rule::enum(EligibilityStatus::class)],
            'photo' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
            'audio' => [
                'nullable',
                File::types(['mp3', 'wav', 'ogg', 'webm'])->max(15 * 1024),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'student_number.unique' => 'That student number is already used in the selected ceremony.',
            'sequence_number.unique' => 'That sequence number is already used in the selected ceremony.',
            'photo.mimes' => 'The photo must be a JPEG, PNG, or WebP image.',
            'audio.mimes' => 'The audio must be an MP3, WAV, OGG, or WebM file.',
        ];
    }

    protected function prepareForValidation(): void
    {
        $trimmed = [];

        foreach ([
            'student_number',
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'display_name',
            'announcement_name',
            'pronunciation_guide',
            'tts_spoken_name',
            'college',
            'program',
            'major',
            'honors',
            'announcement_additional_text',
        ] as $field) {
            $value = $this->input($field);

            if (! is_string($value)) {
                continue;
            }

            $value = trim($value);
            $trimmed[$field] = $value === '' ? null : $value;
        }

        $this->merge($trimmed);
    }
}
