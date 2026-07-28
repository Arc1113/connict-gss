<?php

namespace App\Support;

use App\Data\ActiveCeremonyResolution;
use App\Enums\CeremonyStatus;
use App\Models\Ceremony;

final class ActiveCeremonyResolver
{
    public function resolve(bool $lockForUpdate = false): ActiveCeremonyResolution
    {
        $query = Ceremony::query()
            ->where('status', CeremonyStatus::Active)
            ->orderBy('id');

        if ($lockForUpdate) {
            $query->lockForUpdate();
        }

        $ceremonies = $query->limit(3)->get();

        return match ($ceremonies->count()) {
            1 => new ActiveCeremonyResolution($ceremonies->first(), 'ready'),
            0 => new ActiveCeremonyResolution(null, 'no_active_ceremony'),
            default => new ActiveCeremonyResolution(null, 'multiple_active_ceremonies'),
        };
    }
}
