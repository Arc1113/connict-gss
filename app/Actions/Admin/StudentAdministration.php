<?php

namespace App\Actions\Admin;

use App\Models\Student;
use App\Models\User;
use App\Services\StudentQrTokenGenerator;
use App\Support\AdministrativeAuditLogger;
use App\Support\StudentMediaStorage;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

final class StudentAdministration
{
    private const DATA_FIELDS = [
        'ceremony_id',
        'student_number',
        'first_name',
        'middle_name',
        'last_name',
        'suffix',
        'display_name',
        'announcement_name',
        'pronunciation_guide',
        'college',
        'program',
        'major',
        'honors',
        'sequence_number',
        'eligibility_status',
    ];

    public function __construct(
        private readonly AdministrativeAuditLogger $auditLogger,
        private readonly StudentMediaStorage $mediaStorage,
        private readonly StudentQrTokenGenerator $qrTokens,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        array $attributes,
        ?UploadedFile $photo,
        ?UploadedFile $audio,
        User $actor,
        ?string $ipAddress,
    ): Student {
        $photoPath = null;
        $audioPath = null;

        try {
            $photoPath = $photo === null ? null : $this->mediaStorage->storePhoto($photo);
            $audioPath = $audio === null ? null : $this->mediaStorage->storeAudio($audio);

            return DB::transaction(function () use (
                $attributes,
                $photoPath,
                $audioPath,
                $actor,
                $ipAddress,
            ): Student {
                $student = Student::query()->create([
                    ...Arr::only($attributes, self::DATA_FIELDS),
                    'qr_token' => $this->qrTokens->generate(),
                    'photo_path' => $photoPath,
                    'audio_path' => $audioPath,
                ]);

                $this->auditLogger->record(
                    $actor,
                    'student.created',
                    'student',
                    $student->id,
                    null,
                    $this->snapshot($student),
                    $ipAddress,
                );

                return $student;
            }, 3);
        } catch (Throwable $exception) {
            $this->mediaStorage->deleteManaged($photoPath);
            $this->mediaStorage->deleteManaged($audioPath);
            $this->throwUniqueViolationAsValidation($exception);

            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Student $student,
        array $attributes,
        ?UploadedFile $photo,
        ?UploadedFile $audio,
        User $actor,
        ?string $ipAddress,
    ): Student {
        $newPhotoPath = null;
        $newAudioPath = null;
        $oldPhotoPath = null;
        $oldAudioPath = null;

        try {
            $newPhotoPath = $photo === null ? null : $this->mediaStorage->storePhoto($photo);
            $newAudioPath = $audio === null ? null : $this->mediaStorage->storeAudio($audio);

            $updated = DB::transaction(function () use (
                $student,
                $attributes,
                $newPhotoPath,
                $newAudioPath,
                $actor,
                $ipAddress,
                &$oldPhotoPath,
                &$oldAudioPath,
            ): Student {
                $locked = Student::query()->lockForUpdate()->findOrFail($student->id);
                $before = $this->snapshot($locked);
                $oldPhotoPath = $locked->photo_path;
                $oldAudioPath = $locked->audio_path;

                $data = Arr::only($attributes, self::DATA_FIELDS);

                $identityFields = [];

                foreach ([
                    'student_number',
                    'first_name',
                    'middle_name',
                    'last_name',
                    'suffix',
                    'display_name',
                    'announcement_name',
                    'pronunciation_guide',
                ] as $field) {
                    if (
                        array_key_exists($field, $data)
                        && $locked->getAttribute($field) !== $data[$field]
                    ) {
                        $identityFields[] = $field;
                    }
                }

                $locked->fill($data);

                if ($newPhotoPath !== null) {
                    $locked->photo_path = $newPhotoPath;
                }

                if ($newAudioPath !== null) {
                    $locked->audio_path = $newAudioPath;
                }

                $locked->save();
                $after = $this->snapshot($locked);
                [$oldValues, $newValues] = $this->changedValues($before, $after);

                if ($identityFields !== []) {
                    $oldValues['identity_fields_changed'] = [];
                    $newValues['identity_fields_changed'] = $identityFields;
                }

                if ($oldValues !== []) {
                    $this->auditLogger->record(
                        $actor,
                        'student.updated',
                        'student',
                        $locked->id,
                        $oldValues,
                        $newValues,
                        $ipAddress,
                    );
                }

                if ($newPhotoPath !== null) {
                    $this->recordMediaAudit(
                        $actor,
                        $locked,
                        'photo',
                        $oldPhotoPath === null ? 'added' : 'replaced',
                        $oldPhotoPath,
                        $ipAddress,
                    );
                }

                if ($newAudioPath !== null) {
                    $this->recordMediaAudit(
                        $actor,
                        $locked,
                        'audio',
                        $oldAudioPath === null ? 'added' : 'replaced',
                        $oldAudioPath,
                        $ipAddress,
                    );
                }

                return $locked;
            }, 3);
        } catch (Throwable $exception) {
            $this->mediaStorage->deleteManaged($newPhotoPath);
            $this->mediaStorage->deleteManaged($newAudioPath);
            $this->throwUniqueViolationAsValidation($exception);

            throw $exception;
        }

        if ($newPhotoPath !== null) {
            $this->mediaStorage->deleteManaged($oldPhotoPath);
        }

        if ($newAudioPath !== null) {
            $this->mediaStorage->deleteManaged($oldAudioPath);
        }

        return $updated;
    }

    public function removeMedia(
        Student $student,
        string $type,
        User $actor,
        ?string $ipAddress,
    ): Student {
        if (! in_array($type, ['photo', 'audio'], true)) {
            throw new \InvalidArgumentException('Unsupported student media type.');
        }

        $column = $type.'_path';
        $oldPath = null;

        $updated = DB::transaction(function () use (
            $student,
            $type,
            $column,
            $actor,
            $ipAddress,
            &$oldPath,
        ): Student {
            $locked = Student::query()->lockForUpdate()->findOrFail($student->id);
            $oldPath = $locked->getAttribute($column);

            if ($oldPath === null) {
                throw ValidationException::withMessages([
                    $type => "This graduate does not have {$type} media to remove.",
                ]);
            }

            $locked->setAttribute($column, null);
            $locked->save();

            $this->recordMediaAudit(
                $actor,
                $locked,
                $type,
                'removed',
                $oldPath,
                $ipAddress,
            );

            return $locked;
        }, 3);

        $this->mediaStorage->deleteManaged($oldPath);

        return $updated;
    }

    public function delete(
        Student $student,
        User $actor,
        ?string $ipAddress,
    ): void {
        [$photoPath, $audioPath] = DB::transaction(function () use (
            $student,
            $actor,
            $ipAddress,
        ): array {
            $locked = Student::query()->lockForUpdate()->findOrFail($student->id);

            if ($locked->scanLogs()->exists() || $locked->queueItems()->exists()) {
                throw ValidationException::withMessages([
                    'student' => 'This graduate cannot be deleted because scan, queue, or presentation history exists.',
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'student.deleted',
                'student',
                $locked->id,
                $this->snapshot($locked),
                null,
                $ipAddress,
            );

            $paths = [$locked->photo_path, $locked->audio_path];
            $locked->delete();

            return $paths;
        }, 3);

        $this->mediaStorage->deleteManaged($photoPath);
        $this->mediaStorage->deleteManaged($audioPath);
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Student $student): array
    {
        return [
            'ceremony_id' => $student->ceremony_id,
            'sequence_number' => $student->sequence_number,
            'eligibility_status' => $student->eligibility_status->value,
            'college' => $student->college,
            'program' => $student->program,
            'major' => $student->major,
            'honors' => $student->honors,
            'photo_status' => $student->photo_path === null ? 'missing' : 'ready',
            'audio_status' => $student->audio_path === null ? 'missing' : 'ready',
            'qr_token_configured' => $student->qr_token !== '',
        ];
    }

    private function recordMediaAudit(
        User $actor,
        Student $student,
        string $type,
        string $operation,
        ?string $oldPath,
        ?string $ipAddress,
    ): void {
        $oldStatus = $oldPath === null ? 'missing' : 'ready';
        $newStatus = $operation === 'removed' ? 'missing' : 'ready';

        $this->auditLogger->record(
            $actor,
            "student.{$type}_{$operation}",
            'student',
            $student->id,
            [$type.'_status' => $oldStatus],
            [$type.'_status' => $newStatus],
            $ipAddress,
        );
    }

    private function throwUniqueViolationAsValidation(
        Throwable $exception,
    ): void {
        if (
            ! $exception instanceof QueryException
            || ($exception->errorInfo[0] ?? null) !== '23505'
        ) {
            return;
        }

        $message = $exception->getMessage();
        $errors = match (true) {
            str_contains(
                $message,
                'students_ceremony_student_number_unique',
            ) => [
                'student_number' => 'That student number is already used in the selected ceremony.',
            ],
            str_contains(
                $message,
                'students_ceremony_sequence_unique',
            ) => [
                'sequence_number' => 'That sequence number is already used in the selected ceremony.',
            ],
            str_contains($message, 'students_qr_token_unique') => [
                'student' => 'A secure QR token could not be assigned. Try saving the graduate again.',
            ],
            default => null,
        };

        if ($errors !== null) {
            throw ValidationException::withMessages($errors);
        }
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @return array{0: array<string, mixed>, 1: array<string, mixed>}
     */
    private function changedValues(array $before, array $after): array
    {
        $oldValues = [];
        $newValues = [];

        foreach ($before as $key => $oldValue) {
            if ($oldValue === $after[$key]) {
                continue;
            }

            $oldValues[$key] = $oldValue;
            $newValues[$key] = $after[$key];
        }

        return [$oldValues, $newValues];
    }
}
