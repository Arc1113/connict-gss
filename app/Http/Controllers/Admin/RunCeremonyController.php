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

class RunCeremonyController extends Controller
{
    public function index(Request $request, CeremonyPreparationService $preparation, WorkingCeremonyResolver $workingCeremonies): Response|RedirectResponse
    {
        Gate::authorize('viewAny', Ceremony::class);
        $ceremony = $workingCeremonies->resolve($request);

        return $ceremony === null
            ? redirect()->route('admin.ceremonies.index')->with('warning', 'Choose a ceremony before opening ceremony operations.')
            : $this->render($request, $ceremony, $preparation);
    }

    public function forCeremony(Request $request, Ceremony $ceremony, CeremonyPreparationService $preparation): Response
    {
        Gate::authorize('view', $ceremony);

        return $this->render($request, $ceremony, $preparation);
    }

    private function render(Request $request, Ceremony $ceremony, CeremonyPreparationService $preparation): Response
    {
        return Inertia::render('Admin/RunCeremony', [
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'event_date' => $ceremony->event_date?->format('Y-m-d'),
                'status' => $ceremony->status->label(),
            ],
            'preparation' => $preparation->forCeremony($ceremony),
            'actions' => [
                'scanner' => $request->user()->can('operate-scanner') ? '/scanner' : null,
                'stage_control' => $request->user()->can('operate-stage') ? '/stage-control' : null,
                'stage_display' => $request->user()->can('operate-stage') ? '/stage' : null,
            ],
        ]);
    }
}
