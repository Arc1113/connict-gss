<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class SearchScannerStudentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate-scanner') === true;
    }

    /**
     * @return array<string, array<int, string>>
     */
    public function rules(): array
    {
        return [
            'query' => ['required', 'string', 'min:2', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('query'))) {
            $this->merge(['query' => trim($this->string('query')->toString())]);
        }
    }
}
