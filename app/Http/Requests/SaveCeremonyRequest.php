<?php

namespace App\Http\Requests;

use App\Enums\CeremonyStatus;
use App\Enums\PresentationMode;
use App\Models\Ceremony;
use App\Support\StageTheme;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\File;

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
            'stage_theme' => ['required', 'array'],
            'stage_theme.preset' => ['required', Rule::in(['institutional', 'modern', 'classic'])],
            'stage_theme.layout' => ['required', Rule::in(['centered', 'split', 'banner'])],
            'stage_theme.background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'stage_theme.surface_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'stage_theme.text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'stage_theme.accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'stage_theme.show_photo' => ['required', 'boolean'],
            'stage_logo' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(5 * 1024),
            ],
            'stage_background' => [
                'nullable',
                File::types(['jpg', 'jpeg', 'png', 'webp'])->max(12 * 1024),
            ],
        ];
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'name' => $this->string('name')->trim()->toString(),
            'academic_year' => $this->blankToNull('academic_year'),
            'venue' => $this->blankToNull('venue'),
            'event_date' => $this->blankToNull('event_date'),
            'stage_theme' => $this->input('stage_theme', StageTheme::defaults()),
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
