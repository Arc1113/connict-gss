<?php

namespace App\Actions\Admin;

use App\Models\StudentImport;
use App\Models\User;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Validation\ValidationException;

final class RetargetStudentImport
{
    public function __construct(
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    public function execute(
        StudentImport $studentImport,
        int $ceremonyId,
        User $actor,
        ?string $ipAddress,
    ): StudentImport {
        if ($studentImport->isExpired()) {
            throw ValidationException::withMessages([
                'ceremony_id' => 'This import preview expired. Upload the CSV again.',
            ]);
        }

        if ($studentImport->isConsumed()) {
            throw ValidationException::withMessages([
                'ceremony_id' => 'A confirmed import cannot change ceremonies.',
            ]);
        }

        $oldCeremonyId = $studentImport->ceremony_id;

        if ($oldCeremonyId === $ceremonyId) {
            return $studentImport->load('ceremony');
        }

        $studentImport->update(['ceremony_id' => $ceremonyId]);

        $this->auditLogger->record(
            $actor,
            'student_import.ceremony_changed',
            'student_import',
            $studentImport->id,
            ['ceremony_id' => $oldCeremonyId],
            ['ceremony_id' => $ceremonyId],
            $ipAddress,
        );

        return $studentImport->fresh('ceremony');
    }
}
