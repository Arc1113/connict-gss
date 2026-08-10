<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReportStageAudioStatusRequest extends FormRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'version' => ['required', 'integer', 'min:0'],
            'student_id' => ['required', 'integer', 'min:1'],
            'status' => [
                'required',
                'string',
                Rule::in(['playing', 'stopped', 'failed']),
            ],
        ];
    }
}
