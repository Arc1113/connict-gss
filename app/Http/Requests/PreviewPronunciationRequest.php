<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementProvider;
use App\Enums\PronunciationRuleSection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class PreviewPronunciationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('view-pronunciation-rules') === true;
    }

    public function rules(): array
    {
        return [
            'text' => ['required', 'string', 'max:1000', 'not_regex:/[<>]/'],
            'section' => ['required', Rule::enum(PronunciationRuleSection::class)],
            'ceremony_id' => ['nullable', 'integer', 'exists:ceremonies,id'],
            'provider' => ['required', Rule::enum(AnnouncementProvider::class)],
            'locale' => ['required', 'string', 'max:16'],
            'voice' => ['required', 'string', 'max:128'],
            'candidate_match_text' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
            'candidate_replacement_text' => ['nullable', 'string', 'max:255', 'not_regex:/[<>]/'],
        ];
    }
}
