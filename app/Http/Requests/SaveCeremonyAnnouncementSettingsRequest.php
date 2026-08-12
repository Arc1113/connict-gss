<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementProvider;
use App\Models\AnnouncementTemplate;
use App\Models\Ceremony;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveCeremonyAnnouncementSettingsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('ceremony') instanceof Ceremony
            && $this->user()?->can('manage-announcement-settings') === true;
    }

    public function rules(): array
    {
        $provider = AnnouncementProvider::tryFrom((string) $this->input('provider'))
            ?? AnnouncementProvider::Azure;
        $voices = $provider === AnnouncementProvider::Azure
            ? (array) config('announcements.voices', [])
            : (array) config("announcements.provider_voices.{$provider->value}", []);

        return [
            'provider' => ['required', Rule::enum(AnnouncementProvider::class)],
            'name' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'introduction_text' => ['required', 'string', 'max:500', 'not_regex:/[<>]/'],
            'introduction_frequency' => ['required', Rule::in(['first_per_program', 'every_student', 'never'])],
            'include_program' => ['required', 'boolean'],
            'include_major' => ['required', 'boolean'],
            'include_honors' => ['required', 'boolean'],
            'include_awards' => ['required', 'boolean'],
            'include_additional_text' => ['required', 'boolean'],
            'awards_lead_in' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'closing_text' => ['nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
            'default_voice' => ['required', 'string', Rule::in(array_keys($voices))],
            'default_locale' => ['required', 'string', Rule::in(array_values($voices))],
            'speaking_rate_percent' => [
                'required',
                'integer',
                'between:'.config('announcements.speaking_rate_percent.minimum', -20).','.config('announcements.speaking_rate_percent.maximum', 10),
            ],
            'pause_after_name_ms' => $this->pauseRules(),
            'pause_between_sections_ms' => $this->pauseRules(),
            'recommended_character_limit' => ['required', 'integer', 'between:100,2000'],
            'maximum_character_limit' => ['required', 'integer', 'gte:recommended_character_limit', 'max:5000'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $voice = $this->input('default_voice');
            $locale = $this->input('default_locale');

            $provider = AnnouncementProvider::tryFrom((string) $this->input('provider'))
                ?? AnnouncementProvider::Azure;
            $voices = $provider === AnnouncementProvider::Azure
                ? (array) config('announcements.voices', [])
                : (array) config("announcements.provider_voices.{$provider->value}", []);

            if (is_string($voice) && is_string($locale)
                && ($voices[$voice] ?? null) !== $locale) {
                $validator->errors()->add(
                    'default_locale',
                    'The locale must match the selected approved voice.',
                );
            }
        }];
    }

    protected function prepareForValidation(): void
    {
        if (! $this->has('provider')) {
            $ceremony = $this->route('ceremony');
            $provider = $ceremony instanceof Ceremony
                ? AnnouncementTemplate::query()
                    ->whereBelongsTo($ceremony)
                    ->where('is_active', true)
                    ->value('provider')
                : null;
            $this->merge(['provider' => $provider instanceof AnnouncementProvider
                ? $provider->value
                : ($provider ?? AnnouncementProvider::Azure->value)]);
        }

        if (! $this->has('introduction_frequency')) {
            $this->merge(['introduction_frequency' => 'first_per_program']);
        }

        foreach (['name', 'introduction_text', 'awards_lead_in', 'closing_text'] as $field) {
            $value = $this->input($field);

            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }

    /** @return list<string> */
    private function pauseRules(): array
    {
        return [
            'required',
            'integer',
            'between:'.config('announcements.pause_ms.minimum', 0).','.config('announcements.pause_ms.maximum', 1500),
        ];
    }
}
