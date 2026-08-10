<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ReviewAnnouncementCandidateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('review-announcement-candidates') === true;
    }

    public function rules(): array
    {
        return [
            'review_notes' => [$this->routeIs('admin.students.announcement-candidates.reject') ? 'required' : 'nullable', 'string', 'max:1000'],
            'continue_review' => ['sometimes', 'boolean'],
        ];
    }
}
