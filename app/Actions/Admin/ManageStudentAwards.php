<?php

namespace App\Actions\Admin;

use App\Models\Student;
use App\Models\StudentAward;
use App\Models\User;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ManageStudentAwards
{
    public function __construct(
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array{award_name: string, spoken_text: ?string, is_announcement_enabled: bool}  $attributes
     */
    public function create(
        Student $student,
        array $attributes,
        User $actor,
        ?string $ipAddress,
    ): StudentAward {
        try {
            return DB::transaction(function () use ($student, $attributes, $actor, $ipAddress): StudentAward {
                $lockedStudent = Student::query()->lockForUpdate()->findOrFail($student->id);
                $nextOrder = (int) $lockedStudent->awards()->max('display_order') + 1;
                $award = $lockedStudent->awards()->create([
                    ...$attributes,
                    'display_order' => $nextOrder,
                    'created_by' => $actor->id,
                    'updated_by' => $actor->id,
                ]);

                $this->auditLogger->record(
                    $actor,
                    'student_award.created',
                    'student_award',
                    $award->id,
                    null,
                    $this->snapshot($award),
                    $ipAddress,
                );

                return $award;
            }, 3);
        } catch (QueryException $exception) {
            $this->throwDuplicateAsValidation($exception);

            throw $exception;
        }
    }

    /**
     * @param  array{award_name: string, spoken_text: ?string, is_announcement_enabled: bool}  $attributes
     */
    public function update(
        Student $student,
        StudentAward $award,
        array $attributes,
        User $actor,
        ?string $ipAddress,
    ): StudentAward {
        try {
            return DB::transaction(function () use (
                $student,
                $award,
                $attributes,
                $actor,
                $ipAddress,
            ): StudentAward {
                Student::query()->lockForUpdate()->findOrFail($student->id);
                $locked = $this->ownedAward($student, $award, true);
                $before = $this->snapshot($locked);
                $locked->fill([
                    ...$attributes,
                    'updated_by' => $actor->id,
                ])->save();

                $this->auditLogger->record(
                    $actor,
                    'student_award.updated',
                    'student_award',
                    $locked->id,
                    $before,
                    $this->snapshot($locked),
                    $ipAddress,
                );

                return $locked;
            }, 3);
        } catch (QueryException $exception) {
            $this->throwDuplicateAsValidation($exception);

            throw $exception;
        }
    }

    public function delete(
        Student $student,
        StudentAward $award,
        User $actor,
        ?string $ipAddress,
    ): void {
        DB::transaction(function () use ($student, $award, $actor, $ipAddress): void {
            Student::query()->lockForUpdate()->findOrFail($student->id);
            $locked = $this->ownedAward($student, $award, true);
            $before = $this->snapshot($locked);
            $locked->delete();
            $this->normalizeOrder($student);

            $this->auditLogger->record(
                $actor,
                'student_award.deleted',
                'student_award',
                $award->id,
                $before,
                null,
                $ipAddress,
            );
        }, 3);
    }

    public function move(
        Student $student,
        StudentAward $award,
        string $direction,
        User $actor,
        ?string $ipAddress,
    ): void {
        DB::transaction(function () use (
            $student,
            $award,
            $direction,
            $actor,
            $ipAddress,
        ): void {
            Student::query()->lockForUpdate()->findOrFail($student->id);
            $awards = $student->awards()->lockForUpdate()->get()->values();
            $index = $awards->search(fn (StudentAward $item): bool => $item->is($award));

            if ($index === false) {
                abort(404);
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if (! $awards->has($target)) {
                return;
            }

            $reordered = $awards->all();
            [$reordered[$index], $reordered[$target]] = [$reordered[$target], $reordered[$index]];

            foreach ($reordered as $position => $item) {
                $item->update([
                    'display_order' => $position,
                    'updated_by' => $actor->id,
                ]);
            }

            $this->auditLogger->record(
                $actor,
                'student_award.reordered',
                'student_award',
                $award->id,
                ['position' => $index],
                ['position' => $target],
                $ipAddress,
            );
        }, 3);
    }

    private function ownedAward(
        Student $student,
        StudentAward $award,
        bool $lock = false,
    ): StudentAward {
        $query = StudentAward::query()
            ->whereBelongsTo($student)
            ->whereKey($award->id);

        return ($lock ? $query->lockForUpdate() : $query)->firstOrFail();
    }

    private function normalizeOrder(Student $student): void
    {
        foreach ($student->awards()->lockForUpdate()->get()->values() as $position => $award) {
            if ($award->display_order !== $position) {
                $award->update(['display_order' => $position]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(StudentAward $award): array
    {
        return [
            'student_id' => $award->student_id,
            'award_name' => $award->award_name,
            'spoken_text' => $award->spoken_text,
            'display_order' => $award->display_order,
            'is_announcement_enabled' => $award->is_announcement_enabled,
        ];
    }

    private function throwDuplicateAsValidation(QueryException $exception): void
    {
        if ($exception->getCode() === '23505'
            && str_contains($exception->getMessage(), 'student_awards_exact_content_unique')) {
            throw ValidationException::withMessages([
                'award_name' => 'This graduate already has the same award and spoken phrase.',
            ]);
        }
    }
}
