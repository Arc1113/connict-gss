<?php

namespace App\Http\Requests;

use App\Enums\AnnouncementProvider;
use App\Enums\PronunciationRuleSection;
use App\Models\PronunciationRule;
use App\Services\NormalizePronunciationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SavePronunciationRuleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can(
            $this->input('ceremony_id') === null
                ? 'manage-common-pronunciation'
                : 'manage-ceremony-pronunciation',
        ) === true;
    }

    public function rules(): array
    {
        return [
            'ceremony_id' => ['nullable', 'integer', 'exists:ceremonies,id'],
            'section' => ['required', Rule::enum(PronunciationRuleSection::class)],
            'match_text' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'is_enabled' => ['required', 'boolean'],
            'variants' => ['required', 'array', 'min:1', 'max:20'],
            'variants.*.provider' => ['required', Rule::enum(AnnouncementProvider::class)],
            'variants.*.locale' => ['nullable', 'string', 'max:16', 'regex:/^[A-Za-z]{2,3}(?:-[A-Za-z0-9]{2,8})?$/'],
            'variants.*.voice' => ['nullable', 'string', 'max:128', 'regex:/^[A-Za-z0-9._-]+$/'],
            'variants.*.replacement_text' => ['required', 'string', 'max:255', 'not_regex:/[<>]/'],
            'variants.*.is_enabled' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [function (Validator $validator): void {
            $seen = [];

            foreach ($this->input('variants', []) as $index => $variant) {
                $key = strtolower(implode('|', [
                    $variant['provider'] ?? '',
                    $variant['locale'] ?? '',
                    $variant['voice'] ?? '',
                ]));

                if (isset($seen[$key])) {
                    $validator->errors()->add(
                        "variants.{$index}.provider",
                        'Each provider, locale, and voice combination may appear only once.',
                    );
                }

                $seen[$key] = true;
            }

            $normalized = app(NormalizePronunciationRule::class)->matchText($this->input('match_text'));
            $query = PronunciationRule::query()
                ->where('section', $this->input('section'))
                ->where('normalized_match', $normalized);
            $ceremonyId = $this->input('ceremony_id');
            $ceremonyId === null
                ? $query->whereNull('ceremony_id')
                : $query->where('ceremony_id', $ceremonyId);
            if ($this->route('pronunciationRule')) {
                $query->where('id', '!=', $this->route('pronunciationRule')->id);
            }
            if ($query->exists()) {
                $validator->errors()->add(
                    'match_text',
                    'This phrase already has a rule in the selected scope and section.',
                );
            }
        }];
    }
}
