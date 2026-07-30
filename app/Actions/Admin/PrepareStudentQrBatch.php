<?php

namespace App\Actions\Admin;

use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\QrCodeRenderer;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class PrepareStudentQrBatch
{
    public const MAXIMUM_SIZE = 100;

    public function __construct(
        private readonly QrCodeRenderer $renderer,
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<int, int>  $studentIds
     * @param  array<string, mixed>  $safeFilters
     * @return array<int, array<string, mixed>>
     */
    public function execute(
        int $ceremonyId,
        array $studentIds,
        array $safeFilters,
        User $actor,
        ?string $ipAddress,
    ): array {
        $studentIds = array_values(array_unique($studentIds));

        if ($studentIds === []) {
            throw ValidationException::withMessages([
                'student_ids' => 'Select at least one graduate to print.',
            ]);
        }

        if (count($studentIds) > self::MAXIMUM_SIZE) {
            throw ValidationException::withMessages([
                'student_ids' => 'A QR batch can contain at most 100 graduates.',
            ]);
        }

        return DB::transaction(function () use (
            $ceremonyId,
            $studentIds,
            $safeFilters,
            $actor,
            $ipAddress,
        ): array {
            $students = Student::query()
                ->whereIn('id', $studentIds)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            if (
                $students->count() !== count($studentIds)
                || $students->contains(
                    fn (Student $student): bool => $student->ceremony_id !== $ceremonyId,
                )
            ) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Every selected graduate must belong to the chosen ceremony.',
                ]);
            }

            $students->loadCount([
                'queueItems as completed_queue_items_count' => fn ($query) => $query
                    ->where('status', StageQueueStatus::Completed->value),
            ]);

            $blocked = $students->filter(
                fn (Student $student): bool => $student->qr_token === ''
                    || $student->eligibility_status !== EligibilityStatus::Eligible
                    || $student->completed_queue_items_count > 0,
            );

            if ($blocked->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'student_ids' => 'Every selected graduate must be eligible, have a secure token, and have no completed presentation history.',
                ]);
            }

            $cards = $students
                ->sortBy([
                    ['sequence_number', 'asc'],
                    ['display_name', 'asc'],
                    ['id', 'asc'],
                ])
                ->values()
                ->map(fn (Student $student): array => [
                    'id' => $student->id,
                    'display_name' => $student->display_name,
                    'student_number' => $student->student_number,
                    'program' => $student->program,
                    'sequence_number' => $student->sequence_number,
                    'qr_data_uri' => $this->renderer->dataUri($student->qr_token),
                ])
                ->all();

            $auditValues = [
                'ceremony_id' => $ceremonyId,
                'graduate_count' => count($cards),
                'maximum_batch_size' => self::MAXIMUM_SIZE,
            ];

            foreach ([
                'college',
                'program',
                'eligibility_status',
                'qr_readiness',
                'sequence_from',
                'sequence_to',
            ] as $filter) {
                if (($safeFilters[$filter] ?? null) !== null) {
                    $auditValues[$filter] = $safeFilters[$filter];
                }
            }

            $this->auditLogger->record(
                $actor,
                'student.qr_batch_generated',
                'ceremony',
                $ceremonyId,
                null,
                $auditValues,
                $ipAddress,
            );

            return $cards;
        }, 3);
    }
}
