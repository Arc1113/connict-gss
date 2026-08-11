<?php

namespace App\Http\Controllers;

use App\Models\Ceremony;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class WorkspaceCeremonyController extends Controller
{
    public function __invoke(
        Request $request,
        WorkingCeremonyResolver $workingCeremonies,
    ): RedirectResponse {
        $validated = $request->validate([
            'ceremony_id' => ['required', 'integer', 'exists:ceremonies,id'],
        ]);

        $ceremony = Ceremony::query()->findOrFail($validated['ceremony_id']);

        Gate::authorize('view', $ceremony);

        $workingCeremonies->store($request, $ceremony);

        return redirect()
            ->to($this->safeReturnPath($request))
            ->with('success', 'Working ceremony updated.');
    }

    private function safeReturnPath(Request $request): string
    {
        $previous = url()->previous();
        $parts = parse_url($previous);

        if (is_string($parts['path'] ?? null) && str_starts_with($parts['path'], '/')) {
            $path = $parts['path'];
            $query = is_string($parts['query'] ?? null)
                ? '?'.$parts['query']
                : '';
            $fragment = is_string($parts['fragment'] ?? null)
                ? '#'.$parts['fragment']
                : '';

            return $path.$query.$fragment;
        }

        return route('dashboard');
    }
}
