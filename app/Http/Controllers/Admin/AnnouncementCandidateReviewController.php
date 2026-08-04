<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReviewAnnouncementCandidate;
use App\Http\Controllers\Controller;
use App\Http\Requests\ReviewAnnouncementCandidateRequest;
use App\Models\AnnouncementGeneration;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class AnnouncementCandidateReviewController extends Controller
{
    public function approve(
        ReviewAnnouncementCandidateRequest $request,
        Student $student,
        AnnouncementGeneration $generation,
        ReviewAnnouncementCandidate $reviewer,
    ): RedirectResponse {
        Gate::authorize('view', $student);
        $result = $reviewer->approve(
            $student,
            $generation,
            $request->validated('review_notes'),
            $request->user(),
            $request->ip(),
        );

        if ($result->stale) {
            throw ValidationException::withMessages([
                'announcement_review' => 'The source changed after generation. Generate a fresh candidate before approval.',
            ]);
        }

        return back()->with('success', 'Candidate approved and promoted to active stage audio.');
    }

    public function reject(
        ReviewAnnouncementCandidateRequest $request,
        Student $student,
        AnnouncementGeneration $generation,
        ReviewAnnouncementCandidate $reviewer,
    ): RedirectResponse {
        Gate::authorize('view', $student);
        $reviewer->reject(
            $student,
            $generation,
            $request->validated('review_notes'),
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Candidate rejected. Active audio was not changed.');
    }
}
