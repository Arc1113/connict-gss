<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementProvider;
use App\Models\AnnouncementTemplate;
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
        $ceremony = $this->route('ceremony');
        $provider = $ceremony instanceof Ceremony
            ? AnnouncementTemplate::query()->whereBelongsTo($ceremony)->where('is_active', true)->value('provider')
            : null;
        $provider = $provider instanceof AnnouncementProvider ? $provider->value : ($provider ?? 'azure');
        $voices = $provider === AnnouncementProvider::Azure->value
            ? (array) config('announcements.voices', [])
            : (array) config('announcements.provider_voices.'.$provider, []);

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
                Rule::in(array_keys($voices)),
            ],
        ];
    }
}
