<?php

namespace App\Http\Requests;

use App\Models\StageDesignTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveStageDesignTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-stage-designs') === true;
    }

    public function rules(): array
    {
        $template = $this->route('stageDesignTemplate');

        return [
            'name' => [
                'required',
                'string',
                'max:255',
                'not_regex:/[<>]/',
                Rule::unique('stage_design_templates')->ignore(
                    $template instanceof StageDesignTemplate ? $template->id : null,
                ),
            ],
            'description' => ['nullable', 'string', 'max:500', 'not_regex:/[<>]/'],
            'theme' => ['required', 'array'],
            'theme.preset' => ['required', Rule::in(['institutional', 'modern', 'classic'])],
            'theme.layout' => ['required', Rule::in(['centered', 'split', 'banner'])],
            'theme.background_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.surface_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.text_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.accent_color' => ['required', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'theme.show_photo' => ['required', 'boolean'],
        ];
    }

    protected function prepareForValidation(): void
    {
        foreach (['name', 'description'] as $field) {
            $value = $this->input($field);
            if (is_string($value)) {
                $value = trim(preg_replace('/\s+/u', ' ', $value) ?? $value);
                $this->merge([$field => $value === '' ? null : $value]);
            }
        }
    }
}
