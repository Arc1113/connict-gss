<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class AdvanceFullyAutomaticPresentationRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'expected_version' => ['required', 'integer', 'min:0'],
            'student_id' => ['required', 'integer', 'min:1'],
        ];
    }
}
