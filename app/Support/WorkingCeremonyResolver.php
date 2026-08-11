<?php

namespace App\Support;

use App\Models\Ceremony;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

final class WorkingCeremonyResolver
{
    public const SESSION_KEY = 'working_ceremony_id';

    private const REQUEST_ATTRIBUTE = 'working_ceremony_override';

    public function canSwitch(?User $user): bool
    {
        return $user !== null && $user->can('viewAny', Ceremony::class);
    }

    public function resolve(Request $request): ?Ceremony
    {
        $user = $request->user();

        if (! $this->canSwitch($user)) {
            $request->session()->forget(self::SESSION_KEY);

            return null;
        }

        $override = $request->attributes->get(self::REQUEST_ATTRIBUTE);

        if ($override instanceof Ceremony && $user->can('view', $override)) {
            return $override;
        }

        $storedCeremonyId = $request->session()->get(self::SESSION_KEY);

        if (is_numeric($storedCeremonyId)) {
            $storedCeremony = $this->findAccessibleCeremony(
                $user,
                (int) $storedCeremonyId,
            );

            if ($storedCeremony !== null) {
                return $storedCeremony;
            }

            $request->session()->forget(self::SESSION_KEY);
        }

        $liveCeremony = app(ActiveCeremonyResolver::class)->resolve()->ceremony;

        if ($liveCeremony !== null && $user->can('view', $liveCeremony)) {
            return $liveCeremony;
        }

        return null;
    }

    /**
     * @return Collection<int, Ceremony>
     */
    public function availableFor(?User $user): Collection
    {
        if (! $this->canSwitch($user)) {
            return collect();
        }

        return Ceremony::query()
            ->orderByRaw(
                "case when status = 'active' then 0 else 1 end",
            )
            ->orderByRaw('event_date DESC NULLS LAST')
            ->orderByDesc('id')
            ->get([
                'id',
                'name',
                'academic_year',
                'event_date',
                'status',
            ]);
    }

    public function store(Request $request, Ceremony $ceremony): void
    {
        $request->session()->put(self::SESSION_KEY, $ceremony->id);
        $request->attributes->set(self::REQUEST_ATTRIBUTE, $ceremony);
    }

    private function findAccessibleCeremony(User $user, int $ceremonyId): ?Ceremony
    {
        $ceremony = Ceremony::query()->find($ceremonyId);

        if ($ceremony === null || ! $user->can('view', $ceremony)) {
            return null;
        }

        return $ceremony;
    }
}
