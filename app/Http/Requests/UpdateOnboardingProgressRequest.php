<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateOnboardingProgressRequest extends FormRequest
{
    public function authorize(): bool
    {
        $definition = config("onboarding.tours.{$this->user()?->role->value}");

        return is_array($definition) && $definition['key'] === $this->route('tourKey');
    }

    public function rules(): array
    {
        return [
            'status' => ['required', Rule::in(['in_progress', 'completed', 'skipped'])],
            'current_step' => ['required', 'integer', 'min:0', 'max:50'],
        ];
    }
}

