<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CeremonyAdministration;
use App\Enums\CeremonyStatus;
use App\Enums\PresentationMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCeremonyRequest;
use App\Models\Ceremony;
use App\Support\StageTheme;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class CeremonyController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Ceremony::class);

        $filters = [
            'search' => trim($request->string('search')->toString()),
            'status' => $request->string('status')->toString(),
            'academic_year' => $request->string('academic_year')->toString(),
        ];

        $ceremonies = Ceremony::query()
            ->withCount('students')
            ->when(
                $filters['search'] !== '',
                fn ($query) => $query->where(
                    'name',
                    'ilike',
                    '%'.$filters['search'].'%',
                ),
            )
            ->when(
                $filters['status'] !== '',
                fn ($query) => $query->where('status', $filters['status']),
            )
            ->when(
                $filters['academic_year'] !== '',
                fn ($query) => $query->where(
                    'academic_year',
                    $filters['academic_year'],
                ),
            )
            ->orderByRaw('event_date DESC NULLS LAST')
            ->orderByDesc('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (Ceremony $ceremony): array => [
                ...$this->serialize($ceremony),
                'students_count' => $ceremony->students_count,
            ]);

        return Inertia::render('Admin/Ceremonies/Index', [
            'ceremonies' => $ceremonies,
            'filters' => $filters,
            'statusOptions' => $this->statusOptions(),
            'academicYears' => Ceremony::query()
                ->whereNotNull('academic_year')
                ->distinct()
                ->orderByDesc('academic_year')
                ->pluck('academic_year'),
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Ceremony::class);

        return Inertia::render('Admin/Ceremonies/Create', [
            'statusOptions' => array_values(array_filter(
                $this->statusOptions(),
                fn (array $option): bool => ! in_array(
                    $option['value'],
                    [CeremonyStatus::Completed->value, CeremonyStatus::Archived->value],
                    true,
                ),
            )),
            'presentationModeOptions' => $this->presentationModeOptions(),
        ]);
    }

    public function store(
        SaveCeremonyRequest $request,
        CeremonyAdministration $administration,
    ): RedirectResponse {
        $ceremony = $administration->create(
            $request->safe()->except(['stage_logo', 'stage_background']),
            $request->file('stage_logo'),
            $request->file('stage_background'),
            $request->user(),
            $request->ip(),
        );

        return redirect()
            ->route('admin.ceremonies.show', $ceremony)
            ->with('success', 'Ceremony created.');
    }

    public function show(Ceremony $ceremony): Response
    {
        Gate::authorize('view', $ceremony);

        $ceremony->loadCount([
            'students',
            'queueItems',
            'scanLogs',
            'stageEvents',
        ])->load('stageState:id,ceremony_id,current_queue_item_id,display_mode,version');

        return Inertia::render('Admin/Ceremonies/Show', [
            'ceremony' => [
                ...$this->serialize($ceremony),
                'students_count' => $ceremony->students_count,
                'queue_items_count' => $ceremony->queue_items_count,
                'scan_logs_count' => $ceremony->scan_logs_count,
                'stage_events_count' => $ceremony->stage_events_count,
                'has_stage_state' => $ceremony->stageState !== null,
                'stage_mode' => $ceremony->stageState?->display_mode->value,
                'stage_version' => $ceremony->stageState?->version,
                'activated_at' => $ceremony->activated_at?->toIso8601String(),
                'completed_at' => $ceremony->completed_at?->toIso8601String(),
            ],
        ]);
    }

    public function edit(Ceremony $ceremony): Response
    {
        Gate::authorize('update', $ceremony);

        return Inertia::render('Admin/Ceremonies/Edit', [
            'ceremony' => $this->serialize($ceremony),
            'statusOptions' => $this->statusOptions(),
            'presentationModeOptions' => $this->presentationModeOptions(),
        ]);
    }

    public function update(
        SaveCeremonyRequest $request,
        Ceremony $ceremony,
        CeremonyAdministration $administration,
    ): RedirectResponse {
        $ceremony = $administration->update(
            $ceremony,
            $request->safe()->except(['stage_logo', 'stage_background']),
            $request->file('stage_logo'),
            $request->file('stage_background'),
            $request->user(),
            $request->ip(),
        );

        return redirect()
            ->route('admin.ceremonies.show', $ceremony)
            ->with('success', 'Ceremony changes saved.');
    }

    public function destroy(
        Request $request,
        Ceremony $ceremony,
        CeremonyAdministration $administration,
    ): RedirectResponse {
        Gate::authorize('delete', $ceremony);

        $administration->delete($ceremony, $request->user(), $request->ip());

        return redirect()
            ->route('admin.ceremonies.index')
            ->with('success', 'Unused ceremony deleted.');
    }

    /**
     * @return array<string, mixed>
     */
    private function serialize(Ceremony $ceremony): array
    {
        return [
            'id' => $ceremony->id,
            'name' => $ceremony->name,
            'academic_year' => $ceremony->academic_year,
            'venue' => $ceremony->venue,
            'event_date' => $ceremony->event_date?->format('Y-m-d'),
            'status' => [
                'value' => $ceremony->status->value,
                'label' => $ceremony->status->label(),
            ],
            'presentation_mode' => [
                'value' => $ceremony->presentation_mode->value,
                'label' => $ceremony->presentation_mode->label(),
            ],
            'stage_theme' => StageTheme::forCeremony($ceremony),
        ];
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function statusOptions(): array
    {
        return array_map(
            fn (CeremonyStatus $status): array => [
                'value' => $status->value,
                'label' => $status->label(),
            ],
            CeremonyStatus::cases(),
        );
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function presentationModeOptions(): array
    {
        return array_map(
            fn (PresentationMode $mode): array => [
                'value' => $mode->value,
                'label' => $mode->label(),
            ],
            PresentationMode::cases(),
        );
    }
}
