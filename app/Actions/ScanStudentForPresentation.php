<?php

namespace App\Actions;

use App\Data\ScanAttemptResult;
use App\Enums\EligibilityStatus;
use App\Enums\PresentationMode;
use App\Enums\ScanResult;
use App\Enums\ScanStatus;
use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\QueueUpdated;
use App\Events\StudentPresented;
use App\Models\ScanLog;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use App\Support\StageQueueTransitions;
use App\Support\StageStatePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ScanStudentForPresentation
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
        private StageQueueTransitions $transitions,
        private StageStatePayload $stagePayload,
    ) {}

    public function execute(string $token, User $operator): ScanAttemptResult
    {
        return $this->executeSubject(
            mb_strtoupper(trim($token)),
            null,
            $operator,
        );
    }

    public function executeStudent(int $studentId, User $operator): ScanAttemptResult
    {
        return $this->executeSubject(null, $studentId, $operator);
    }

    private function executeSubject(
        ?string $normalizedToken,
        ?int $manualStudentId,
        User $operator,
    ): ScanAttemptResult {
        $tokenHash = $normalizedToken === null
            ? null
            : hash('sha256', $normalizedToken);

        /**
         * @var array{
         *     result: ScanAttemptResult,
         *     ceremony_id: int|null,
         *     version: int|null,
         *     queue_item_id: int|null,
         *     presentation: array<string, mixed>|null
         * } $committed
         */
        $committed = DB::transaction(function () use (
            $normalizedToken,
            $manualStudentId,
            $operator,
            $tokenHash,
        ): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                $this->logAttempt(
                    null,
                    null,
                    $tokenHash,
                    ScanResult::Rejected,
                    $resolution->status,
                    $operator,
                );

                return $this->transactionResult(
                    new ScanAttemptResult(
                        ScanStatus::CeremonyInactive,
                        $resolution->status === 'multiple_active_ceremonies'
                            ? 'Multiple active ceremonies require administrator correction.'
                            : 'No active ceremony is configured.',
                    ),
                );
            }

            $ceremony = $resolution->ceremony;
            $stageState = StageState::query()
                ->whereBelongsTo($ceremony)
                ->lockForUpdate()
                ->first();

            if ($stageState === null) {
                $stageState = StageState::query()->create([
                    'ceremony_id' => $ceremony->id,
                    'current_queue_item_id' => null,
                    'display_mode' => StageDisplayMode::Idle,
                    'audio_status' => StageAudioStatus::Stopped,
                    'is_muted' => false,
                    'automatic_paused' => false,
                    'version' => 0,
                ]);
            }

            $studentQuery = Student::query()
                ->whereBelongsTo($ceremony);
            $student = $manualStudentId === null
                ? $studentQuery->where('qr_token', $normalizedToken)->first()
                : $studentQuery->whereKey($manualStudentId)->first();

            if ($student === null) {
                return $this->rejected(
                    $ceremony->id,
                    null,
                    $tokenHash,
                    ScanResult::Invalid,
                    'invalid_token',
                    ScanStatus::InvalidToken,
                    'Code not recognized for the active ceremony.',
                    $operator,
                );
            }

            if ($student->eligibility_status !== EligibilityStatus::Eligible) {
                return $this->rejected(
                    $ceremony->id,
                    $student,
                    $tokenHash,
                    ScanResult::Ineligible,
                    'student_ineligible',
                    ScanStatus::StudentIneligible,
                    'This graduate is not eligible for presentation.',
                    $operator,
                );
            }

            $activeItem = StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->whereBelongsTo($student)
                ->whereIn('status', [
                    StageQueueStatus::Waiting,
                    StageQueueStatus::Ready,
                    StageQueueStatus::Presenting,
                    StageQueueStatus::Announced,
                ])
                ->lockForUpdate()
                ->latest('id')
                ->first();

            if ($activeItem !== null) {
                $presenting = in_array($activeItem->status, [
                    StageQueueStatus::Presenting,
                    StageQueueStatus::Announced,
                ], true);

                return $this->rejected(
                    $ceremony->id,
                    $student,
                    $tokenHash,
                    ScanResult::Duplicate,
                    $presenting ? 'already_presenting' : 'already_queued',
                    $presenting
                        ? ScanStatus::AlreadyPresenting
                        : ScanStatus::AlreadyQueued,
                    $presenting
                        ? 'This graduate is already being presented.'
                        : 'This graduate is already waiting in the stage queue.',
                    $operator,
                    $activeItem,
                );
            }

            $completedItem = StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->whereBelongsTo($student)
                ->where('status', StageQueueStatus::Completed)
                ->latest('id')
                ->first();

            if ($completedItem !== null) {
                return $this->rejected(
                    $ceremony->id,
                    $student,
                    $tokenHash,
                    ScanResult::Duplicate,
                    'already_completed',
                    ScanStatus::AlreadyCompleted,
                    'This graduate has already completed the stage.',
                    $operator,
                    $completedItem,
                );
            }

            $skippedItem = StageQueueItem::query()
                ->whereBelongsTo($ceremony)
                ->whereBelongsTo($student)
                ->where('status', StageQueueStatus::Skipped)
                ->latest('id')
                ->first();

            if ($skippedItem !== null) {
                return $this->rejected(
                    $ceremony->id,
                    $student,
                    $tokenHash,
                    ScanResult::Duplicate,
                    'skipped_requires_stage_action',
                    ScanStatus::SkippedRequiresStageAction,
                    'This graduate was skipped. Ask the stage operator to return them to the queue.',
                    $operator,
                    $skippedItem,
                );
            }

            $queuedAt = now();
            $queueItem = StageQueueItem::query()->create([
                'ceremony_id' => $ceremony->id,
                'student_id' => $student->id,
                'status' => StageQueueStatus::Waiting,
                'queue_position' => $this->transitions->nextPosition($ceremony->id),
                'scanned_at' => $queuedAt,
                'scanned_by' => $operator->id,
            ]);

            $stageState->version++;
            $stageState->save();

            StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $queueItem->id,
                'event_type' => 'student_queued',
                'payload' => [
                    'version' => $stageState->version,
                    'queue_position' => $queueItem->queue_position,
                ],
                'performed_by' => $operator->id,
                'created_at' => $queuedAt,
            ]);

            $presentation = null;
            $automaticallyPresented = false;

            if (
                in_array($ceremony->presentation_mode, [PresentationMode::Automatic, PresentationMode::FullyAutomatic], true)
                && ! $stageState->automatic_paused
                && $stageState->current_queue_item_id === null
                && $stageState->display_mode === StageDisplayMode::Idle
            ) {
                $firstWaitingId = StageQueueItem::query()
                    ->whereBelongsTo($ceremony)
                    ->whereIn('status', [
                        StageQueueStatus::Waiting,
                        StageQueueStatus::Ready,
                    ])
                    ->orderBy('queue_position')
                    ->orderBy('scanned_at')
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->value('id');

                if ((int) $firstWaitingId === $queueItem->id) {
                    $presentedAt = now();
                    $this->transitions->transition(
                        $queueItem,
                        StageQueueStatus::Presenting,
                    );
                    $queueItem->fill([
                        'presented_at' => $presentedAt,
                        'presented_by' => $operator->id,
                    ])->save();

                    $stageState->fill([
                        'current_queue_item_id' => $queueItem->id,
                        'display_mode' => StageDisplayMode::Graduate,
                        'audio_status' => StageAudioStatus::Stopped,
                        'version' => $stageState->version + 1,
                    ])->save();

                    $presentedEvent = StageEvent::query()->create([
                        'ceremony_id' => $ceremony->id,
                        'queue_item_id' => $queueItem->id,
                        'event_type' => 'student_presented_automatically',
                        'payload' => [
                            'version' => $stageState->version,
                            'student_id' => $student->id,
                        ],
                        'performed_by' => $operator->id,
                        'created_at' => $presentedAt,
                    ]);

                    $queueItem->setRelation('student', $student);
                    $presentation = $this->stagePayload->presentation(
                        $queueItem,
                        $stageState->version,
                        $presentedEvent->id,
                    );
                    $automaticallyPresented = true;
                }
            }

            $this->logAttempt(
                $ceremony->id,
                $student->id,
                $tokenHash,
                ScanResult::Accepted,
                $automaticallyPresented ? 'queued_and_presented' : 'queued',
                $operator,
            );

            return [
                'result' => new ScanAttemptResult(
                    ScanStatus::Queued,
                    $automaticallyPresented
                        ? 'Graduate queued and presented automatically.'
                        : 'Graduate added to the stage queue.',
                    $student->id,
                    $student->display_name,
                    $queueItem->queue_position,
                    $queueItem->status->value,
                    $automaticallyPresented,
                ),
                'ceremony_id' => $ceremony->id,
                'version' => $stageState->version,
                'queue_item_id' => $queueItem->id,
                'presentation' => $presentation,
            ];
        }, attempts: 3);

        if ($committed['ceremony_id'] !== null && $committed['version'] !== null) {
            try {
                QueueUpdated::dispatch(
                    $committed['ceremony_id'],
                    $committed['version'],
                    'student_queued',
                    $committed['queue_item_id'],
                );

                if ($committed['presentation'] !== null) {
                    StudentPresented::dispatch(
                        $committed['ceremony_id'],
                        $committed['presentation'],
                    );
                }
            } catch (Throwable $exception) {
                $committed['result']->broadcastDelivered = false;

                Log::warning('Queue change committed but real-time delivery failed.', [
                    'ceremony_id' => $committed['ceremony_id'],
                    'queue_item_id' => $committed['queue_item_id'],
                    'exception' => $exception::class,
                ]);
            }
        }

        return $committed['result'];
    }

    /**
     * @return array{
     *     result: ScanAttemptResult,
     *     ceremony_id: int|null,
     *     version: int|null,
     *     queue_item_id: int|null,
     *     presentation: null
     * }
     */
    private function transactionResult(ScanAttemptResult $result): array
    {
        return [
            'result' => $result,
            'ceremony_id' => null,
            'version' => null,
            'queue_item_id' => null,
            'presentation' => null,
        ];
    }

    /**
     * @return array{
     *     result: ScanAttemptResult,
     *     ceremony_id: int,
     *     version: null,
     *     queue_item_id: null,
     *     presentation: null
     * }
     */
    private function rejected(
        int $ceremonyId,
        ?Student $student,
        ?string $tokenHash,
        ScanResult $result,
        string $reason,
        ScanStatus $status,
        string $message,
        User $operator,
        ?StageQueueItem $queueItem = null,
    ): array {
        $this->logAttempt(
            $ceremonyId,
            $student?->id,
            $tokenHash,
            $result,
            $reason,
            $operator,
        );

        return [
            'result' => new ScanAttemptResult(
                $status,
                $message,
                $student?->id,
                $student?->display_name,
                $queueItem?->queue_position,
                $queueItem?->status->value,
            ),
            'ceremony_id' => $ceremonyId,
            'version' => null,
            'queue_item_id' => null,
            'presentation' => null,
        ];
    }

    private function logAttempt(
        ?int $ceremonyId,
        ?int $studentId,
        ?string $tokenHash,
        ScanResult $result,
        string $reason,
        User $operator,
    ): void {
        ScanLog::query()->create([
            'ceremony_id' => $ceremonyId,
            'student_id' => $studentId,
            'device_id' => null,
            'raw_token_hash' => $tokenHash,
            'result' => $result,
            'message' => $reason,
            'scanned_by' => $operator->id,
            'scanned_at' => now(),
        ]);
    }
}
