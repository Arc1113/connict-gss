<?php

namespace App\Http\Controllers\Admin;

use App\Actions\CancelAnnouncementGenerationBatch;
use App\Actions\ResumeAnnouncementGenerationBatch;
use App\Actions\StartAnnouncementGenerationBatch;
use App\Exceptions\AnnouncementBatchException;
use App\Http\Controllers\Controller;
use App\Http\Requests\StartAnnouncementBatchRequest;
use App\Models\AnnouncementGenerationBatch;
use App\Models\Ceremony;
use App\Services\AnnouncementBatchWorkspace;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class AnnouncementBatchController extends Controller
{
    public function index(Ceremony $ceremony, AnnouncementBatchWorkspace $workspace): Response
    {
        Gate::authorize('view', $ceremony);
        Gate::authorize('manage-announcement-batches');

        return Inertia::render('Admin/Ceremonies/AnnouncementBatches', [
            'workspace' => $workspace->forCeremony($ceremony),
        ]);
    }

    public function store(
        StartAnnouncementBatchRequest $request,
        Ceremony $ceremony,
        StartAnnouncementGenerationBatch $starter,
    ): RedirectResponse {
        try {
            $result = $starter->start(
                $ceremony,
                $request->validated('student_ids'),
                $request->boolean('budget_acknowledged'),
                $request->user(),
                $request->ip(),
            );
        } catch (AnnouncementBatchException $exception) {
            throw ValidationException::withMessages([
                'announcement_batch' => $exception->getMessage(),
            ]);
        }

        return back()->with(
            'success',
            $result->reused
                ? 'The identical active batch was reused; no duplicate work was queued.'
                : 'Announcement batch queued for sequential preparation.',
        );
    }

    public function cancel(
        Request $request,
        Ceremony $ceremony,
        AnnouncementGenerationBatch $batch,
        CancelAnnouncementGenerationBatch $canceller,
    ): RedirectResponse {
        $this->ensureNested($ceremony, $batch);

        try {
            $canceller->cancel($batch, $request->user(), $request->ip());
        } catch (AnnouncementBatchException $exception) {
            throw ValidationException::withMessages([
                'announcement_batch' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Batch cancellation recorded. Any in-flight request will finish safely.');
    }

    public function resume(
        Request $request,
        Ceremony $ceremony,
        AnnouncementGenerationBatch $batch,
        ResumeAnnouncementGenerationBatch $resumer,
    ): RedirectResponse {
        $this->ensureNested($ceremony, $batch);

        try {
            $resumer->resume($batch, $request->user(), $request->ip());
        } catch (AnnouncementBatchException $exception) {
            throw ValidationException::withMessages([
                'announcement_batch' => $exception->getMessage(),
            ]);
        }

        return back()->with('success', 'Batch resumed on the sequential preparation queue.');
    }

    private function ensureNested(Ceremony $ceremony, AnnouncementGenerationBatch $batch): void
    {
        abort_unless($batch->ceremony_id === $ceremony->id, 404);
        Gate::authorize('manage-announcement-batches');
    }
}
