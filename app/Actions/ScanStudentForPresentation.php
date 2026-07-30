<?php

namespace App\Actions;

use App\Data\ScanAttemptResult;
use App\Enums\EligibilityStatus;
use App\Enums\ScanResult;
use App\Enums\ScanStatus;
use App\Enums\StageAudioStatus;
use App\Enums\StageDisplayMode;
use App\Enums\StageQueueStatus;
use App\Events\StudentPresented;
use App\Models\ScanLog;
use App\Models\StageEvent;
use App\Models\StageQueueItem;
use App\Models\StageState;
use App\Models\Student;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use App\Support\StageStatePayload;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

final readonly class ScanStudentForPresentation
{
    public function __construct(
        private ActiveCeremonyResolver $ceremonies,
        private StageStatePayload $stagePayload,
    ) {}

    public function execute(string $token, User $operator): ScanAttemptResult
    {
        $normalizedToken = mb_strtoupper(trim($token));

        return $this->executeSubject($normalizedToken, null, $operator);
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

        /** @var array{result: ScanAttemptResult, ceremony_id: int|null, broadcast: array<string, mixed>|null} $committed */
        $committed = DB::transaction(function () use (
            $normalizedToken,
            $manualStudentId,
            $operator,
            $tokenHash,
        ): array {
            $resolution = $this->ceremonies->resolve(lockForUpdate: true);

            if (! $resolution->isResolved()) {
                $this->logAttempt(
                    ceremonyId: null,
                    studentId: null,
                    tokenHash: $tokenHash,
                    result: ScanResult::Rejected,
                    reason: $resolution->status,
                    operator: $operator,
                );

                $message = $resolution->status === 'multiple_active_ceremonies'
                    ? 'Multiple active ceremonies require administrator correction.'
                    : 'No active ceremony is configured.';

                return [
                    'result' => new ScanAttemptResult(
                        ScanStatus::CeremonyInactive,
                        $message,
                    ),
                    'ceremony_id' => null,
                    'broadcast' => null,
                ];
            }

            $ceremony = $resolution->ceremony;
            $stageState = StageState::query()
                ->where('ceremony_id', $ceremony->id)
                ->lockForUpdate()
                ->first();

            if ($stageState === null) {
                $stageState = StageState::query()->create([
                    'ceremony_id' => $ceremony->id,
                    'current_queue_item_id' => null,
                    'display_mode' => StageDisplayMode::Idle,
                    'audio_status' => StageAudioStatus::Stopped,
                    'is_muted' => false,
                    'version' => 0,
                ]);
            }

            $studentQuery = Student::query()
                ->where('ceremony_id', $ceremony->id);
            $student = $manualStudentId === null
                ? $studentQuery->where('qr_token', $normalizedToken)->first()
                : $studentQuery->whereKey($manualStudentId)->first();

            if ($student === null) {
                return $this->rejected(
                    ceremonyId: $ceremony->id,
                    studentId: null,
                    tokenHash: $tokenHash,
                    result: ScanResult::Invalid,
                    reason: 'invalid_token',
                    status: ScanStatus::InvalidToken,
                    message: 'Code not recognized for the active ceremony.',
                    operator: $operator,
                );
            }

            if ($student->eligibility_status !== EligibilityStatus::Eligible) {
                return $this->rejected(
                    ceremonyId: $ceremony->id,
                    studentId: $student->id,
                    tokenHash: $tokenHash,
                    result: ScanResult::Ineligible,
                    reason: 'student_ineligible',
                    status: ScanStatus::StudentIneligible,
                    message: 'This graduate is not eligible for presentation.',
                    operator: $operator,
                );
            }

            $activeStatuses = [
                StageQueueStatus::Waiting->value,
                StageQueueStatus::Ready->value,
                StageQueueStatus::Presenting->value,
                StageQueueStatus::Announced->value,
            ];

            $hasActiveItem = StageQueueItem::query()
                ->where('ceremony_id', $ceremony->id)
                ->where('student_id', $student->id)
                ->whereIn('status', $activeStatuses)
                ->exists();

            if ($hasActiveItem) {
                return $this->rejected(
                    ceremonyId: $ceremony->id,
                    studentId: $student->id,
                    tokenHash: $tokenHash,
                    result: ScanResult::Duplicate,
                    reason: 'duplicate_active',
                    status: ScanStatus::DuplicateActive,
                    message: 'This graduate already has an active presentation.',
                    operator: $operator,
                );
            }

            $hasCompletedItem = StageQueueItem::query()
                ->where('ceremony_id', $ceremony->id)
                ->where('student_id', $student->id)
                ->where('status', StageQueueStatus::Completed)
                ->exists();

            if ($hasCompletedItem) {
                return $this->rejected(
                    ceremonyId: $ceremony->id,
                    studentId: $student->id,
                    tokenHash: $tokenHash,
                    result: ScanResult::Duplicate,
                    reason: 'already_completed',
                    status: ScanStatus::AlreadyCompleted,
                    message: 'This graduate has already been completed.',
                    operator: $operator,
                );
            }

            if (
                $stageState->current_queue_item_id !== null
                || $stageState->display_mode !== StageDisplayMode::Idle
            ) {
                return $this->rejected(
                    ceremonyId: $ceremony->id,
                    studentId: $student->id,
                    tokenHash: $tokenHash,
                    result: ScanResult::Rejected,
                    reason: 'stage_busy',
                    status: ScanStatus::StageBusy,
                    message: 'The stage is busy. Complete the current presentation first.',
                    operator: $operator,
                );
            }

            $presentedAt = now();
            $queueItem = StageQueueItem::query()->create([
                'ceremony_id' => $ceremony->id,
                'student_id' => $student->id,
                'status' => StageQueueStatus::Presenting,
                'queue_position' => null,
                'scanned_at' => $presentedAt,
                'presented_at' => $presentedAt,
                'scanned_by' => $operator->id,
                'presented_by' => null,
            ]);

            $stageState->fill([
                'current_queue_item_id' => $queueItem->id,
                'display_mode' => StageDisplayMode::Graduate,
                'audio_status' => StageAudioStatus::Stopped,
                'version' => $stageState->version + 1,
            ])->save();

            $stageEvent = StageEvent::query()->create([
                'ceremony_id' => $ceremony->id,
                'queue_item_id' => $queueItem->id,
                'event_type' => 'student_presented',
                'payload' => [
                    'version' => $stageState->version,
                    'student_id' => $student->id,
                ],
                'performed_by' => $operator->id,
                'created_at' => $presentedAt,
            ]);

            $this->logAttempt(
                ceremonyId: $ceremony->id,
                studentId: $student->id,
                tokenHash: $tokenHash,
                result: ScanResult::Accepted,
                reason: 'accepted',
                operator: $operator,
            );

            $queueItem->setRelation('student', $student);
            $presentation = $this->stagePayload->presentation(
                $queueItem,
                $stageState->version,
                $stageEvent->id,
            );

            return [
                'result' => new ScanAttemptResult(
                    ScanStatus::Accepted,
                    'Graduate accepted and presented.',
                    $presentation,
                ),
                'ceremony_id' => $ceremony->id,
                'broadcast' => $presentation,
            ];
        }, attempts: 3);

        if ($committed['broadcast'] !== null && $committed['ceremony_id'] !== null) {
            try {
                StudentPresented::dispatch(
                    $committed['ceremony_id'],
                    $committed['broadcast'],
                );
            } catch (Throwable $exception) {
                $committed['result']->broadcastDelivered = false;

                Log::warning('Presentation committed but real-time delivery failed.', [
                    'ceremony_id' => $committed['ceremony_id'],
                    'event_id' => $committed['broadcast']['event_id'],
                    'exception' => $exception::class,
                ]);
            }
        }

        return $committed['result'];
    }

    /**
     * @return array{result: ScanAttemptResult, ceremony_id: int, broadcast: null}
     */
    private function rejected(
        int $ceremonyId,
        ?int $studentId,
        ?string $tokenHash,
        ScanResult $result,
        string $reason,
        ScanStatus $status,
        string $message,
        User $operator,
    ): array {
        $this->logAttempt(
            ceremonyId: $ceremonyId,
            studentId: $studentId,
            tokenHash: $tokenHash,
            result: $result,
            reason: $reason,
            operator: $operator,
        );

        return [
            'result' => new ScanAttemptResult($status, $message),
            'ceremony_id' => $ceremonyId,
            'broadcast' => null,
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
