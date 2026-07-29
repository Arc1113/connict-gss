<?php

namespace App\Http\Requests;

use App\Enums\CeremonyStatus;
use App\Enums\PresentationMode;
use App\Models\Ceremony;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveCeremonyRequest extends FormRequest
{
    public function authorize(): bool
    {
        $ceremony = $this->route('ceremony');

        return $ceremony instanceof Ceremony
            ? $this->user()?->can('update', $ceremony) === true
            : $this->user()?->can('create', Ceremony::class) === true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'academic_year' => [
                'nullable',
                'string',
                'max:32',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if ($value === null || $value === '') {
                        return;
                    }

                    if (
                        preg_match('/^(\d{4})-(\d{4})$/', (string) $value, $matches) !== 1
                        || (int) $matches[2] !== (int) $matches[1] + 1
                    ) {
                        $fail('The academic year must use consecutive years, for example 2026-2027.');
                    }
                },
            ],
            'venue' => ['nullable', 'string', 'max:255'],
            'event_date' => ['nullable', 'date_format:Y-m-d'],
            'status' => ['required', Rule::enum(CeremonyStatus::class)],
            'presentation_mode' => ['required', Rule::enum(PresentationMode::class)],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'academic_year' => $this->blankToNull('academic_year'),
            'venue' => $this->blankToNull('venue'),
            'event_date' => $this->blankToNull('event_date'),
        ]);
    }

    private function blankToNull(string $key): mixed
    {
        $value = $this->input($key);

        if (! is_string($value)) {
            return $value;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
