<?php

namespace App\Http\Requests;

use App\Models\Ceremony;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class GenerateVoiceComparisonRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('ceremony') instanceof Ceremony
            && $this->user()?->can('manage-announcement-settings') === true;
    }

    public function rules(): array
    {
        return [
            'voices' => [
                'required',
                'array',
                'min:1',
                'max:'.config('announcements.voice_comparison.maximum_voices', 4),
            ],
            'voices.*' => [
                'required',
                'string',
                'distinct',
                Rule::in(array_keys((array) config('announcements.voices', []))),
            ],
        ];
    }
}
