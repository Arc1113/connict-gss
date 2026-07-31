<?php

namespace App\Actions\Admin;

use App\Enums\CeremonyStatus;
use App\Enums\StageQueueStatus;
use App\Models\Ceremony;
use App\Models\StageState;
use App\Models\User;
use App\Support\AdministrativeAuditLogger;
use App\Support\CeremonyThemeStorage;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CeremonyAdministration
{
    private const LIFECYCLE_LOCK_ID = 2026072804;

    public function __construct(
        private readonly AdministrativeAuditLogger $auditLogger,
        private readonly CeremonyThemeStorage $themeStorage,
    ) {}

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(
        array $attributes,
        ?UploadedFile $logo,
        ?UploadedFile $background,
        User $actor,
        ?string $ipAddress,
    ): Ceremony {
        $logoPath = $logo === null ? null : $this->themeStorage->storeLogo($logo);
        $backgroundPath = $background === null
            ? null
            : $this->themeStorage->storeBackground($background);

        try {
            return DB::transaction(function () use (
                $attributes,
                $logoPath,
                $backgroundPath,
                $actor,
                $ipAddress,
            ): Ceremony {
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

                $ceremony = Ceremony::query()->create([
                    ...$attributes,
                    'stage_logo_path' => $logoPath,
                    'stage_background_path' => $backgroundPath,
                ]);

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
        } catch (\Throwable $exception) {
            $this->themeStorage->delete($logoPath);
            $this->themeStorage->delete($backgroundPath);
            throw $exception;
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        Ceremony $ceremony,
        array $attributes,
        ?UploadedFile $logo,
        ?UploadedFile $background,
        User $actor,
        ?string $ipAddress,
    ): Ceremony {
        $newLogoPath = $logo === null ? null : $this->themeStorage->storeLogo($logo);
        $newBackgroundPath = $background === null
            ? null
            : $this->themeStorage->storeBackground($background);
        $oldLogoPath = null;
        $oldBackgroundPath = null;

        try {
            $updated = DB::transaction(function () use (
                $ceremony,
                $attributes,
                $newLogoPath,
                $newBackgroundPath,
                $actor,
                $ipAddress,
                &$oldLogoPath,
                &$oldBackgroundPath,
            ): Ceremony {
                $this->lockLifecycle();

                $locked = Ceremony::query()->lockForUpdate()->findOrFail($ceremony->id);
                $before = $this->snapshot($locked);
                $oldStatus = $locked->status;
                $newStatus = CeremonyStatus::from($attributes['status']);
                $oldLogoPath = $locked->stage_logo_path;
                $oldBackgroundPath = $locked->stage_background_path;

                $this->assertTransitionAllowed($oldStatus, $newStatus);
                $this->assertActivationAvailable($newStatus, $locked->id);

                $locked->fill($attributes);

                if ($newLogoPath !== null) {
                    $locked->stage_logo_path = $newLogoPath;
                }

                if ($newBackgroundPath !== null) {
                    $locked->stage_background_path = $newBackgroundPath;
                }

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
        } catch (\Throwable $exception) {
            $this->themeStorage->delete($newLogoPath);
            $this->themeStorage->delete($newBackgroundPath);
            throw $exception;
        }

        if ($newLogoPath !== null) {
            $this->themeStorage->delete($oldLogoPath);
        }

        if ($newBackgroundPath !== null) {
            $this->themeStorage->delete($oldBackgroundPath);
        }

        return $updated;
    }

    public function delete(
        Ceremony $ceremony,
        User $actor,
        ?string $ipAddress,
    ): void {
        [$logoPath, $backgroundPath] = DB::transaction(function () use ($ceremony, $actor, $ipAddress): array {
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

            return [$locked->stage_logo_path, $locked->stage_background_path];
        }, 3);

        $this->themeStorage->delete($logoPath);
        $this->themeStorage->delete($backgroundPath);
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
            'stage_theme' => $ceremony->stage_theme,
            'stage_logo_configured' => $ceremony->stage_logo_path !== null,
            'stage_background_configured' => $ceremony->stage_background_path !== null,
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
