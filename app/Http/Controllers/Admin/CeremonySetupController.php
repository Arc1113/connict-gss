<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ceremony;
use App\Models\CeremonySetupProgress;
use App\Services\CeremonyPreparationService;
use App\Services\CeremonySetupNavigator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CeremonySetupController extends Controller
{
    public function show(
        Request $request,
        Ceremony $ceremony,
        CeremonyPreparationService $preparation,
        CeremonySetupNavigator $navigator,
    ): Response {
        Gate::authorize('view', $ceremony);
        $contract = $preparation->forCeremony($ceremony);
        $progress = $this->existingProgress($request, $ceremony);
        $setup = $this->setupPayload($progress, $ceremony);
        $next = $navigator->nextStep($contract, $setup);

        return Inertia::render('Admin/Ceremonies/GuidedSetup', [
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'event_date' => $ceremony->event_date?->format('Y-m-d'),
            ],
            'preparation' => $contract,
            'setup' => $setup,
            'currentStep' => $next,
            'urls' => [
                'workspace' => route('admin.ceremonies.show', $ceremony),
                'guided_setup' => route('admin.ceremonies.guided-setup', $ceremony),
                'start' => route('admin.ceremonies.guided-setup.start', $ceremony),
                'continue' => route('admin.ceremonies.guided-setup.continue', $ceremony),
                'skip' => route('admin.ceremonies.guided-setup.skip', $ceremony),
                'skip_setup' => route('admin.ceremonies.guided-setup.skip-setup', $ceremony),
            ],
        ]);
    }

    public function start(Request $request, Ceremony $ceremony, CeremonySetupNavigator $navigator, CeremonyPreparationService $preparation): RedirectResponse
    {
        Gate::authorize('view', $ceremony);
        $progress = $this->progress($request, $ceremony);
        $next = $navigator->nextStep($preparation->forCeremony($ceremony), $progress->toArray());
        $progress->forceFill([
            'started_at' => $progress->started_at ?? now(),
            'completed_at' => null,
            'current_step' => $next['key'] ?? null,
        ])->save();

        return redirect()->route('admin.ceremonies.guided-setup', $ceremony);
    }

    public function continue(Request $request, Ceremony $ceremony, CeremonySetupNavigator $navigator, CeremonyPreparationService $preparation): RedirectResponse
    {
        Gate::authorize('view', $ceremony);
        $progress = $this->progress($request, $ceremony);
        $next = $navigator->nextStep($preparation->forCeremony($ceremony), $progress->toArray());
        $progress->forceFill([
            'started_at' => $progress->started_at ?? now(),
            'current_step' => $next['key'] ?? null,
            'completed_at' => $next === null ? ($progress->completed_at ?? now()) : null,
        ])->save();

        return redirect()->route('admin.ceremonies.guided-setup', $ceremony);
    }

    public function skip(Request $request, Ceremony $ceremony, CeremonyPreparationService $preparation, CeremonySetupNavigator $navigator): RedirectResponse
    {
        Gate::authorize('view', $ceremony);
        $validated = $request->validate(['step' => ['required', 'string', 'max:64']]);
        $contract = $preparation->forCeremony($ceremony);
        $allowed = collect($contract['steps'])->contains(fn (array $step): bool => $step['key'] === $validated['step'] && $step['status'] !== 'ready');

        abort_unless($allowed, 422, 'That preparation step is not available to skip.');

        $progress = $this->progress($request, $ceremony);
        $skipped = array_values(array_unique([
            ...($progress->skipped_steps ?? []),
            $validated['step'],
        ]));
        $next = $navigator->nextStep($contract, ['skipped_steps' => $skipped]);
        $progress->forceFill([
            'started_at' => $progress->started_at ?? now(),
            'skipped_steps' => $skipped,
            'current_step' => $next['key'] ?? null,
            'completed_at' => null,
        ])->save();

        return redirect()->route('admin.ceremonies.guided-setup', $ceremony);
    }

    public function skipSetup(Request $request, Ceremony $ceremony): RedirectResponse
    {
        Gate::authorize('view', $ceremony);

        return redirect()->route('admin.ceremonies.show', $ceremony)
            ->with('success', 'Guided setup skipped. Preparation readiness remains based on ceremony data.');
    }

    private function progress(Request $request, Ceremony $ceremony): CeremonySetupProgress
    {
        return CeremonySetupProgress::query()->firstOrCreate([
            'ceremony_id' => $ceremony->id,
            'user_id' => $request->user()->id,
        ], [
            'skipped_steps' => [],
        ]);
    }

    private function existingProgress(Request $request, Ceremony $ceremony): CeremonySetupProgress
    {
        return CeremonySetupProgress::query()->firstWhere([
            'ceremony_id' => $ceremony->id,
            'user_id' => $request->user()->id,
        ]) ?? new CeremonySetupProgress([
            'ceremony_id' => $ceremony->id,
            'user_id' => $request->user()->id,
            'skipped_steps' => [],
        ]);
    }

    /** @return array<string, mixed> */
    private function setupPayload(CeremonySetupProgress $progress, Ceremony $ceremony): array
    {
        return [
            'current_step' => $progress->current_step,
            'skipped_steps' => $progress->skipped_steps ?? [],
            'started' => $progress->started_at !== null,
            'completed' => $progress->completed_at !== null,
            'started_at' => $progress->started_at?->toIso8601String(),
            'completed_at' => $progress->completed_at?->toIso8601String(),
            'back_url' => route('admin.ceremonies.show', $ceremony),
        ];
    }
}
