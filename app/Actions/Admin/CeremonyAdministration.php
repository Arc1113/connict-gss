<?php

namespace App\Actions\Admin;

use App\Enums\CeremonyStatus;
use App\Enums\StageQueueStatus;
use App\Models\Ceremony;
use App\Models\StageState;
use App\Models\User;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CeremonyAdministration
{
    private const LIFECYCLE_LOCK_ID = 2026072804;

    public function __construct(
        private readonly AdministrativeAuditLogger $auditLogger,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        array $attributes,
        User $actor,
        ?string $ipAddress,
    ): Ceremony {
        return DB::transaction(function () use ($attributes, $actor, $ipAddress): Ceremony {
            $this->lockLifecycle();

            $status = CeremonyStatus::from($attributes['status']);

            if (in_array($status, [CeremonyStatus::Completed, CeremonyStatus::Archived], true)) {
                throw ValidationException::withMessages([
                    'status' => 'A new ceremony must begin as draft, prepared, or active.',
                ]);
            }

            $this->assertActivationAvailable($status);

            if ($status === CeremonyStatus::Active) {
                $attributes['activated_at'] = now();
            }

            $ceremony = Ceremony::query()->create($attributes);

            $this->auditLogger->record(
                $actor,
                'ceremony.created',
                'ceremony',
                $ceremony->id,
                null,
                $this->snapshot($ceremony),
                $ipAddress,
            );

            return $ceremony;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Ceremony $ceremony,
        array $attributes,
        User $actor,
        ?string $ipAddress,
    ): Ceremony {
        return DB::transaction(function () use (
            $ceremony,
            $attributes,
            $actor,
            $ipAddress,
        ): Ceremony {
            $this->lockLifecycle();

            $locked = Ceremony::query()->lockForUpdate()->findOrFail($ceremony->id);
            $before = $this->snapshot($locked);
            $oldStatus = $locked->status;
            $newStatus = CeremonyStatus::from($attributes['status']);

            $this->assertTransitionAllowed($oldStatus, $newStatus);
            $this->assertActivationAvailable($newStatus, $locked->id);

            $locked->fill($attributes);

            if ($locked->isDirty() && $this->isPresenting($locked)) {
                throw ValidationException::withMessages([
                    'status' => 'This ceremony cannot be changed while a graduate is being presented. Complete the current presentation first.',
                ]);
            }

            if ($oldStatus !== CeremonyStatus::Active && $newStatus === CeremonyStatus::Active) {
                $locked->activated_at = now();
            }

            if ($oldStatus !== CeremonyStatus::Completed && $newStatus === CeremonyStatus::Completed) {
                $locked->completed_at = now();
            }

            $locked->save();
            $after = $this->snapshot($locked);
            [$oldValues, $newValues] = $this->changedValues($before, $after);

            if ($oldValues !== []) {
                $action = $oldStatus === $newStatus
                    ? 'ceremony.updated'
                    : 'ceremony.status_changed';

                $this->auditLogger->record(
                    $actor,
                    $action,
                    'ceremony',
                    $locked->id,
                    $oldValues,
                    $newValues,
                    $ipAddress,
                );
            }

            return $locked;
        }, 3);
    }

    public function delete(
        Ceremony $ceremony,
        User $actor,
        ?string $ipAddress,
    ): void {
        DB::transaction(function () use ($ceremony, $actor, $ipAddress): void {
            $this->lockLifecycle();

            $locked = Ceremony::query()->lockForUpdate()->findOrFail($ceremony->id);

            if (in_array(
                $locked->status,
                [CeremonyStatus::Active, CeremonyStatus::Completed, CeremonyStatus::Archived],
                true,
            )) {
                throw ValidationException::withMessages([
                    'ceremony' => 'Active, completed, or archived ceremonies cannot be deleted.',
                ]);
            }

            $protected = [
                'graduates' => $locked->students()->exists(),
                'scan history' => $locked->scanLogs()->exists(),
                'queue or presentation history' => $locked->queueItems()->exists(),
                'stage events' => $locked->stageEvents()->exists(),
                'stage state' => $locked->stageState()->exists(),
            ];
            $reasons = array_keys(array_filter($protected));

            if ($reasons !== []) {
                throw ValidationException::withMessages([
                    'ceremony' => 'This ceremony cannot be deleted because it has '.implode(', ', $reasons).'.',
                ]);
            }

            $snapshot = $this->snapshot($locked);
            $id = $locked->id;

            $this->auditLogger->record(
                $actor,
                'ceremony.deleted',
                'ceremony',
                $id,
                $snapshot,
                null,
                $ipAddress,
            );

            $locked->delete();
        }, 3);
    }

    private function lockLifecycle(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(?)', [self::LIFECYCLE_LOCK_ID]);
        Ceremony::query()->orderBy('id')->lockForUpdate()->get();
    }

    private function assertActivationAvailable(
        CeremonyStatus $status,
        ?int $exceptCeremonyId = null,
    ): void {
        if ($status !== CeremonyStatus::Active) {
            return;
        }

        $query = Ceremony::query()->where('status', CeremonyStatus::Active);

        if ($exceptCeremonyId !== null) {
            $query->whereKeyNot($exceptCeremonyId);
        }

        if ($query->exists()) {
            throw ValidationException::withMessages([
                'status' => 'Another ceremony is active. Pause or complete it before activating this ceremony.',
            ]);
        }
    }

    private function assertTransitionAllowed(
        CeremonyStatus $from,
        CeremonyStatus $to,
    ): void {
        if ($from === $to) {
            return;
        }

        $allowed = match ($from) {
            CeremonyStatus::Draft => [
                CeremonyStatus::Prepared,
                CeremonyStatus::Active,
                CeremonyStatus::Archived,
            ],
            CeremonyStatus::Prepared => [
                CeremonyStatus::Draft,
                CeremonyStatus::Active,
                CeremonyStatus::Archived,
            ],
            CeremonyStatus::Active => [
                CeremonyStatus::Paused,
                CeremonyStatus::Completed,
            ],
            CeremonyStatus::Paused => [
                CeremonyStatus::Prepared,
                CeremonyStatus::Active,
                CeremonyStatus::Completed,
                CeremonyStatus::Archived,
            ],
            CeremonyStatus::Completed => [CeremonyStatus::Archived],
            CeremonyStatus::Archived => [],
        };

        if (! in_array($to, $allowed, true)) {
            throw ValidationException::withMessages([
                'status' => "A {$from->label()} ceremony cannot move directly to {$to->label()}.",
            ]);
        }
    }

    private function isPresenting(Ceremony $ceremony): bool
    {
        return StageState::query()
            ->where('ceremony_id', $ceremony->id)
            ->whereHas(
                'currentQueueItem',
                fn ($query) => $query->where(
                    'status',
                    StageQueueStatus::Presenting->value,
                ),
            )
            ->exists();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Ceremony $ceremony): array
    {
        return [
            'name' => $ceremony->name,
            'academic_year' => $ceremony->academic_year,
            'venue' => $ceremony->venue,
            'event_date' => $ceremony->event_date?->format('Y-m-d'),
            'status' => $ceremony->status->value,
            'presentation_mode' => $ceremony->presentation_mode->value,
            'activated_at' => $ceremony->activated_at?->toIso8601String(),
            'completed_at' => $ceremony->completed_at?->toIso8601String(),
        ];
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
