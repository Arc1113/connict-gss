<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StartAnnouncementBatchRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-announcement-batches') === true;
    }

    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        $maximum = max(1, (int) config('announcements.batch_generation.maximum_students', 50));

        return [
            'student_ids' => ['required', 'array', 'min:1', 'max:'.$maximum],
            'student_ids.*' => ['required', 'integer', 'distinct'],
            'budget_acknowledged' => ['sometimes', 'boolean'],
        ];
    }
}
