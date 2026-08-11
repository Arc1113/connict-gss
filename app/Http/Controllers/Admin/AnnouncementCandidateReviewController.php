<?php

namespace App\Http\Controllers\Admin;

use App\Actions\ReviewAnnouncementCandidate;
use App\Enums\AnnouncementGenerationStatus;
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

        if ($request->boolean('continue_review')) {
            return $this->continueReviewing(
                $student,
                'Candidate approved. The next voice is ready for review.',
            );
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

        if ($request->boolean('continue_review')) {
            return $this->continueReviewing(
                $student,
                'Candidate rejected. The next voice is ready for review.',
            );
        }

        return back()->with('success', 'Candidate rejected. Active audio was not changed.');
    }

    private function continueReviewing(Student $student, string $message): RedirectResponse
    {
        $next = AnnouncementGeneration::query()
            ->where('announcement_generations.ceremony_id', $student->ceremony_id)
            ->where('announcement_generations.status', AnnouncementGenerationStatus::NeedsReview->value)
            ->join('students', 'students.id', '=', 'announcement_generations.student_id')
            ->orderBy('students.sequence_number')
            ->orderBy('students.id')
            ->orderBy('announcement_generations.id')
            ->select('announcement_generations.*')
            ->first();

        if ($next === null) {
            return redirect()
                ->to(route('admin.ceremonies.announcements', $student->ceremony).'#review-queue')
                ->with('success', 'Review queue complete. No generated voices are awaiting approval.');
        }

        return redirect()
            ->to(route('admin.ceremonies.students.show', [$student->ceremony, $next->student_id]).'?section=announcement#announcement-candidate-'.$next->id)
            ->with('success', $message);
    }
}
