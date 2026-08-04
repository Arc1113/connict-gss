<?php

namespace App\Http\Requests;

class GenerateStudentAnnouncementRequest extends PreviewStudentAnnouncementRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'force' => ['sometimes', 'boolean'],
        ];
    }
}
