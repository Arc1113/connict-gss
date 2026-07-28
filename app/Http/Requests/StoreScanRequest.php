<?php

namespace App\Http\Requests;

use App\Enums\ScanStatus;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\Exceptions\HttpResponseException;

class StoreScanRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('operate-scanner') === true;
    }

    /**
     * @return array<string, list<string>>
     */
    public function rules(): array
    {
        return [
            'token' => ['required', 'string', 'max:128'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('token'))) {
            $this->merge([
                'token' => mb_strtoupper(trim($this->string('token')->toString())),
            ]);
        }
    }

    protected function failedAuthorization(): void
    {
        throw new HttpResponseException(response()->json([
            'status' => ScanStatus::Unauthorized->value,
            'message' => 'You are not authorized to operate the scanner.',
        ], ScanStatus::Unauthorized->httpStatus()));
    }

    protected function failedValidation(Validator $validator): void
    {
        throw new HttpResponseException(response()->json([
            'status' => ScanStatus::InvalidToken->value,
            'message' => 'A scan value between 1 and 128 characters is required.',
            'errors' => $validator->errors(),
        ], ScanStatus::InvalidToken->httpStatus()));
    }
}
