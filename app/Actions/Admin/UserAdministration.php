<?php

namespace App\Actions\Admin;

use App\Enums\UserRole;
use App\Models\User;
use App\Support\AdministrativeAuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UserAdministration
{
    private const LIFECYCLE_LOCK_ID = 2026073012;

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
    ): User {
        return DB::transaction(function () use (
            $attributes,
            $actor,
            $ipAddress,
        ): User {
            $this->lockLifecycle();

            $user = User::query()->create($attributes);

            $this->auditLogger->record(
                $actor,
                'user.created',
                'user',
                $user->id,
                null,
                [
                    ...$this->snapshot($user),
                    'password_set' => true,
                ],
                $ipAddress,
            );

            return $user;
        }, 3);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(
        User $user,
        array $attributes,
        User $actor,
        ?string $ipAddress,
    ): User {
        return DB::transaction(function () use (
            $user,
            $attributes,
            $actor,
            $ipAddress,
        ): User {
            $this->lockLifecycle();

            $locked = User::query()->lockForUpdate()->findOrFail($user->id);
            $before = $this->snapshot($locked);
            $newRole = UserRole::from($attributes['role']);
            $newActive = (bool) $attributes['is_active'];

            if (
                $locked->id === $actor->id
                && (
                    ! $newActive
                    || $newRole !== UserRole::Administrator
                )
            ) {
                throw ValidationException::withMessages([
                    ! $newActive ? 'is_active' : 'role' => ! $newActive
                        ? 'You cannot deactivate the account currently in use.'
                        : 'You cannot change the administrator role of the account currently in use.',
                ]);
            }

            if (
                $locked->is_active
                && $locked->role === UserRole::Administrator
                && (
                    ! $newActive
                    || $newRole !== UserRole::Administrator
                )
                && ! User::query()
                    ->whereKeyNot($locked->id)
                    ->where('role', UserRole::Administrator)
                    ->where('is_active', true)
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    ! $newActive ? 'is_active' : 'role' => 'At least one other active administrator is required before changing this account.',
                ]);
            }

            $passwordChanged = isset($attributes['password'])
                && is_string($attributes['password'])
                && $attributes['password'] !== '';
            $locked->fill($attributes);
            $locked->save();

            $after = $this->snapshot($locked);
            [$oldValues, $newValues] = $this->changedValues($before, $after);

            if ($passwordChanged) {
                $newValues['password_changed'] = true;
            }

            if ($oldValues !== [] || $newValues !== []) {
                $this->auditLogger->record(
                    $actor,
                    'user.updated',
                    'user',
                    $locked->id,
                    $oldValues,
                    $newValues,
                    $ipAddress,
                );
            }

            return $locked;
        }, 3);
    }

    private function lockLifecycle(): void
    {
        DB::select('SELECT pg_advisory_xact_lock(?)', [self::LIFECYCLE_LOCK_ID]);
        User::query()->orderBy('id')->lockForUpdate()->get();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user): array
    {
        return [
            'name' => $user->name,
            'email' => $user->email,
            'role' => $user->role->value,
            'is_active' => $user->is_active,
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
