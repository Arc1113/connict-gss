<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ceremony;
use App\Services\CeremonyPreparationService;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class PreparationController extends Controller
{
    public function index(Request $request, CeremonyPreparationService $preparation, WorkingCeremonyResolver $workingCeremonies): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Ceremony::class);
        $ceremony = $workingCeremonies->resolve($request);

        if ($ceremony === null) {
            return redirect()->route('admin.ceremonies.index')
                ->with('warning', 'Choose a ceremony before opening preparation.');
        }

        return Inertia::render('Admin/Preparation/Index', [
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'event_date' => $ceremony->event_date?->format('Y-m-d'),
            ],
            'preparation' => $preparation->forCeremony($ceremony),
        ]);
    }

    public function forCeremony(Request $request, Ceremony $ceremony, CeremonyPreparationService $preparation): Response
    {
        Gate::authorize('view', $ceremony);

        return Inertia::render('Admin/Preparation/Index', [
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'event_date' => $ceremony->event_date?->format('Y-m-d'),
            ],
            'preparation' => $preparation->forCeremony($ceremony),
        ]);
    }
}
