<?php

use App\Enums\CeremonyStatus;
use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('stage.{ceremonyId}', function (
    User $user,
    int $ceremonyId,
): bool {
    $activeCeremonyIds = Ceremony::query()
        ->where('status', CeremonyStatus::Active)
        ->orderBy('id')
        ->limit(2)
        ->pluck('id');

    return $user->can('operate-stage')
        && $activeCeremonyIds->count() === 1
        && (int) $activeCeremonyIds->first() === $ceremonyId;
});

Broadcast::channel('stage-control.{ceremonyId}', function (
    User $user,
    int $ceremonyId,
): bool {
    $activeCeremonyIds = Ceremony::query()
        ->where('status', CeremonyStatus::Active)
        ->orderBy('id')
        ->limit(2)
        ->pluck('id');

    return $user->can('operate-stage')
        && $activeCeremonyIds->count() === 1
        && (int) $activeCeremonyIds->first() === $ceremonyId;
});
