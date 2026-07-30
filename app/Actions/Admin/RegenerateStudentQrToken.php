<?php

namespace App\Actions\Admin;

use App\Enums\StageQueueStatus;
use App\Models\Student;
use App\Models\User;
use App\Services\StudentQrTokenGenerator;
use App\Services\StudentQrTokenVersion;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class RegenerateStudentQrToken
{
    public function __construct(
        private readonly StudentQrTokenGenerator $tokens,
        private readonly StudentQrTokenVersion $versions,
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    public function execute(
        Student $student,
        string $expectedVersion,
        User $actor,
        ?string $ipAddress,
    ): Student {
        return DB::transaction(function () use (
            $student,
            $expectedVersion,
            $actor,
            $ipAddress,
        ): Student {
            $locked = Student::query()
                ->lockForUpdate()
                ->findOrFail($student->id);
            if (! $this->versions->matches($locked, $expectedVersion)) {
                throw ValidationException::withMessages([
                    'regeneration' => 'This graduate changed after the page opened. Reload and review the record before replacing its QR token.',
                ]);
            }

            if ($locked->queueItems()
                ->where('status', StageQueueStatus::Presenting)
                ->exists()) {
                throw ValidationException::withMessages([
                    'regeneration' => 'The QR token cannot be replaced while this graduate is presenting.',
                ]);
            }

            if ($locked->queueItems()
                ->where('status', StageQueueStatus::Completed)
                ->exists()) {
                throw ValidationException::withMessages([
                    'regeneration' => 'The QR token cannot be replaced after a completed presentation.',
                ]);
            }

            $locked->qr_token = $this->tokens->generate();
            $locked->save();

            $this->auditLogger->record(
                $actor,
                'student.qr_token_regenerated',
                'student',
                $locked->id,
                ['token_state' => 'previous_token_invalidated'],
                [
                    'token_state' => 'replacement_available',
                    'ceremony_id' => $locked->ceremony_id,
                ],
                $ipAddress,
            );

            return $locked;
        }, 3);
    }
}
