<?php

namespace App\Actions\Admin;

use App\Enums\CeremonyStatus;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\User;
use App\Services\StudentImportPreparation;
use App\Services\StudentQrTokenGenerator;
use App\Support\AdministrativeAuditLogger;
use App\Support\StudentImportLimits;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class ConfirmStudentImport
{
    private const IMPORT_LOCK_ID = 2026072905;

    public function __construct(
        private readonly StudentImportPreparation $preparation,
        private readonly AdministrativeAuditLogger $auditLogger,
        private readonly StudentQrTokenGenerator $qrTokens,
    ) {}

    public function execute(
        StudentImport $studentImport,
        User $actor,
        ?string $ipAddress,
    ): StudentImport {
        $startedAt = now()->utc()->toImmutable();
        $preview = null;

        try {
            return DB::transaction(function () use (
                $studentImport,
                $actor,
                $ipAddress,
                $startedAt,
                &$preview,
            ): StudentImport {
                DB::select(
                    'SELECT pg_advisory_xact_lock(?)',
                    [self::IMPORT_LOCK_ID],
                );

                $lockedImport = StudentImport::query()
                    ->lockForUpdate()
                    ->findOrFail($studentImport->id);

                if ($lockedImport->user_id !== $actor->id) {
                    throw new AuthorizationException;
                }

                if ($lockedImport->isConsumed()) {
                    throw ValidationException::withMessages([
                        'confirm' => 'This import was already confirmed.',
                    ]);
                }

                if ($lockedImport->isExpired()) {
                    throw ValidationException::withMessages([
                        'confirm' => 'This import preview expired. Upload the CSV again.',
                    ]);
                }

                $ceremony = Ceremony::query()
                    ->lockForUpdate()
                    ->find($lockedImport->ceremony_id);

                if ($ceremony === null) {
                    throw ValidationException::withMessages([
                        'confirm' => 'The selected ceremony no longer exists. Upload the CSV again.',
                    ]);
                }

                if (in_array(
                    $ceremony->status,
                    [CeremonyStatus::Completed, CeremonyStatus::Archived],
                    true,
                )) {
                    throw ValidationException::withMessages([
                        'confirm' => 'Completed or archived ceremonies cannot receive imported graduates.',
                    ]);
                }

                Student::query()
                    ->where('ceremony_id', $ceremony->id)
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get(['id']);

                $preview = $this->preparation->buildPreview($lockedImport);

                if (! $preview['can_confirm']) {
                    throw ValidationException::withMessages([
                        'confirm' => 'The import now has blocking row errors. Review the preview before trying again.',
                    ]);
                }

                $created = 0;

                foreach ($preview['rows'] as $row) {
                    $student = Student::query()->create([
                        'ceremony_id' => $ceremony->id,
                        'qr_token' => $this->qrTokens->generate(),
                        ...$row['normalized'],
                    ]);
                    $created++;

                    $this->auditLogger->record(
                        $actor,
                        'student.created',
                        'student',
                        $student->id,
                        null,
                        $this->studentSnapshot($student, $lockedImport->id),
                        $ipAddress,
                    );
                }

                $completedAt = now()->utc()->toImmutable();
                $summary = [
                    'status' => 'completed',
                    'ceremony_id' => $ceremony->id,
                    'ceremony_name' => $ceremony->name,
                    'mode' => 'create_only',
                    'original_filename' => $lockedImport->original_filename,
                    'total_count' => $preview['total_count'],
                    'created_count' => $created,
                    'updated_count' => 0,
                    'warning_count' => $preview['warning_count'],
                    'skipped_count' => 0,
                    'started_at' => $startedAt->toIso8601String(),
                    'completed_at' => $completedAt->toIso8601String(),
                ];

                $this->auditLogger->record(
                    $actor,
                    'student_import.completed',
                    'student_import',
                    $lockedImport->id,
                    null,
                    $summary,
                    $ipAddress,
                );

                $lockedImport->forceFill([
                    'source_content' => null,
                    'summary' => $summary,
                    'consumed_at' => $completedAt,
                    'expires_at' => $completedAt->addMinutes(
                        StudentImportLimits::EXPIRATION_MINUTES,
                    ),
                ])->save();

                return $lockedImport;
            }, 3);
        } catch (AuthorizationException $exception) {
            throw $exception;
        } catch (ValidationException $exception) {
            $this->recordFailure(
                $studentImport,
                $actor,
                $ipAddress,
                $preview,
                'validation_conflict',
            );

            throw $exception;
        } catch (QueryException $exception) {
            $this->recordFailure(
                $studentImport,
                $actor,
                $ipAddress,
                $preview,
                ($exception->errorInfo[0] ?? null) === '23505'
                    ? 'uniqueness_conflict'
                    : 'database_failure',
            );

            report($exception);

            throw ValidationException::withMessages([
                'confirm' => ($exception->errorInfo[0] ?? null) === '23505'
                    ? 'Graduate data changed after preview. Review the row conflicts and try again.'
                    : 'The import could not be saved. No graduate records were imported.',
            ]);
        } catch (Throwable $exception) {
            $this->recordFailure(
                $studentImport,
                $actor,
                $ipAddress,
                $preview,
                'transaction_failure',
            );

            report($exception);

            throw ValidationException::withMessages([
                'confirm' => 'The import could not be completed. No graduate records were imported.',
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function studentSnapshot(Student $student, int $importId): array
    {
        return [
            'ceremony_id' => $student->ceremony_id,
            'sequence_number' => $student->sequence_number,
            'eligibility_status' => $student->eligibility_status->value,
            'college' => $student->college,
            'program' => $student->program,
            'major' => $student->major,
            'honors' => $student->honors,
            'photo_status' => 'missing',
            'audio_status' => 'missing',
            'qr_token_configured' => true,
            'student_import_id' => $importId,
        ];
    }

    /**
     * @param  array<string, mixed>|null  $preview
     */
    private function recordFailure(
        StudentImport $studentImport,
        User $actor,
        ?string $ipAddress,
        ?array $preview,
        string $reason,
    ): void {
        try {
            $this->auditLogger->record(
                $actor,
                'student_import.failed',
                'student_import',
                $studentImport->id,
                null,
                [
                    'status' => 'failed',
                    'ceremony_id' => $studentImport->ceremony_id,
                    'mode' => 'create_only',
                    'original_filename' => $studentImport->original_filename,
                    'total_count' => $preview['total_count']
                        ?? $studentImport->row_count,
                    'created_count' => 0,
                    'updated_count' => 0,
                    'warning_count' => $preview['warning_count'] ?? 0,
                    'reason' => $reason,
                    'attempted_at' => now()->utc()->toIso8601String(),
                ],
                $ipAddress,
            );
        } catch (Throwable $auditException) {
            report($auditException);
        }
    }
}
