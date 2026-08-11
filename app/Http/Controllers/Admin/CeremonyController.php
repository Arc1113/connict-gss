<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\CeremonyAdministration;
use App\Enums\CeremonyStatus;
use App\Enums\PresentationMode;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveCeremonyRequest;
use App\Models\BackupRecord;
use App\Models\Ceremony;
use App\Models\StageDesignTemplate;
use App\Models\Student;
use App\Models\StudentImport;
use App\Support\CeremonyWorkspaceSummary;
use App\Support\StageTheme;
use App\Support\WorkingCeremonyResolver;
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
            'stageDesignTemplates' => $this->stageDesignTemplates(),
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

    public function show(
        Request $request,
        Ceremony $ceremony,
        WorkingCeremonyResolver $workingCeremonies,
        CeremonyWorkspaceSummary $summary,
    ): Response {
        Gate::authorize('view', $ceremony);
        $this->synchronizeWorkingCeremony($request, $ceremony, $workingCeremonies);
        $workspaceSummary = $summary->forCeremony($ceremony);
        $counts = $workspaceSummary['counts'];

        return Inertia::render('Admin/Ceremonies/Show', [
            'ceremony' => [
                ...$this->serialize($ceremony),
                'activated_at' => $ceremony->activated_at?->toIso8601String(),
                'completed_at' => $ceremony->completed_at?->toIso8601String(),
            ],
            'overview' => [
                'summary' => $counts,
                'blockers' => $this->workspaceBlockers($ceremony, $counts),
                'next_action' => $this->workspaceNextAction($ceremony, $counts),
                'details' => [
                    'event_date' => $ceremony->event_date?->toDateString(),
                    'venue' => $ceremony->venue,
                    'presentation_mode' => $ceremony->presentation_mode->label(),
                    'live_status' => $ceremony->status->label(),
                ],
            ],
            'workingCeremonyContext' => $this->serializeWorkingCeremonyContext(
                $request,
                $workingCeremonies,
                $ceremony,
            ),
        ]);
    }

    public function setup(
        Request $request,
        Ceremony $ceremony,
        WorkingCeremonyResolver $workingCeremonies,
    ): Response {
        Gate::authorize('update', $ceremony);
        $this->synchronizeWorkingCeremony($request, $ceremony, $workingCeremonies);

        return Inertia::render('Admin/Ceremonies/Setup', [
            'ceremony' => $this->serialize($ceremony),
            'statusOptions' => $this->statusOptions(),
            'presentationModeOptions' => $this->presentationModeOptions(),
            'stageDesignTemplates' => $this->stageDesignTemplates(),
            'workingCeremonyContext' => $this->serializeWorkingCeremonyContext(
                $request,
                $workingCeremonies,
                $ceremony,
            ),
        ]);
    }

    public function edit(Ceremony $ceremony): RedirectResponse
    {
        Gate::authorize('update', $ceremony);

        return redirect()->route('admin.ceremonies.setup', $ceremony);
    }

    public function section(
        Request $request,
        Ceremony $ceremony,
        string $section,
        WorkingCeremonyResolver $workingCeremonies,
    ): Response|RedirectResponse {
        Gate::authorize('view', $ceremony);
        $this->synchronizeWorkingCeremony($request, $ceremony, $workingCeremonies);

        return match ($section) {
            'announcements' => $this->renderWorkspaceBridge(
                $request,
                $ceremony,
                'announcements',
                'Announcements',
                'The existing announcement preparation flow remains available, but it still depends on the live ceremony until Phase 5 migrates working-ceremony scoping.',
                [
                    [
                        'href' => '/admin/voice-generation',
                        'label' => 'Open existing announcement workspace',
                        'tone' => 'primary',
                    ],
                    ...($request->user()->can('manage-announcement-settings') ? [[
                        'href' => route('admin.ceremonies.announcement-settings.show', $ceremony),
                        'label' => 'Open announcement settings',
                        'tone' => 'secondary',
                    ]] : []),
                    ...($request->user()->can('manage-announcement-batches') ? [[
                        'href' => route('admin.ceremonies.announcement-batches.index', $ceremony),
                        'label' => 'Open announcement batches',
                        'tone' => 'secondary',
                    ]] : []),
                ],
            ),
            'stage-design' => Inertia::render('Admin/Ceremonies/StageDesign', [
                'ceremony' => $this->serialize($ceremony),
                'stageDesign' => [
                    'setup_url' => route('admin.ceremonies.setup', $ceremony),
                    'library_url' => '/admin/stage-designs',
                ],
            ]),
            'readiness' => Inertia::render('Admin/Ceremonies/Readiness', [
                'ceremony' => $this->serialize($ceremony),
                'readiness' => [
                    'blockers' => $this->workspaceBlockers($ceremony, app(CeremonyWorkspaceSummary::class)->forCeremony($ceremony)['counts']),
                    'sections' => $this->readinessSections($ceremony),
                    'last_backup' => $this->lastBackupSummary(),
                ],
            ]),
            default => abort(404),
        };
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

    /** @return list<array<string, mixed>> */
    private function stageDesignTemplates(): array
    {
        return StageDesignTemplate::query()
            ->orderBy('name')
            ->get(['id', 'name', 'description', 'theme'])
            ->map(static fn (StageDesignTemplate $template): array => [
                'id' => $template->id,
                'name' => $template->name,
                'description' => $template->description,
                'theme' => [...StageTheme::defaults(), ...$template->theme],
            ])
            ->all();
    }

    private function synchronizeWorkingCeremony(
        Request $request,
        Ceremony $ceremony,
        WorkingCeremonyResolver $workingCeremonies,
    ): void {
        if ($workingCeremonies->canSwitch($request->user())) {
            $workingCeremonies->store($request, $ceremony);
        }
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array<string, string|int>>
     */
    private function workspaceBlockers(Ceremony $ceremony, array $counts): array
    {
        return array_map(
            fn (array $blocker): array => [
                'count' => $blocker['count'],
                'label' => $blocker['label'],
                'detail' => $blocker['detail'],
                'href' => match ($blocker['key']) {
                    'graduates_empty' => route('admin.ceremonies.students.import.index', $ceremony),
                    'eligibility_issues' => route('admin.ceremonies.students.index', $ceremony).'?eligibility_status=pending',
                    'photos_missing' => route('admin.ceremonies.students.index', $ceremony).'?media=missing_photo',
                    'announcements_needs_review', 'announcements_missing' => route('admin.ceremonies.announcements', $ceremony),
                },
            ],
            app(CeremonyWorkspaceSummary::class)->blockers($counts),
        );
    }

    /**
     * @param  array<string, int>  $counts
     * @return array<string, string|int>|null
     */
    private function workspaceNextAction(Ceremony $ceremony, array $counts): ?array
    {
        return $this->workspaceBlockers($ceremony, $counts)[0] ?? null;
    }

    private function redirectToStudents(Ceremony $ceremony): RedirectResponse
    {
        Gate::authorize('viewAny', Student::class);

        return redirect()->route('admin.ceremonies.students.index', $ceremony);
    }

    private function redirectToImport(): RedirectResponse
    {
        Gate::authorize('create', StudentImport::class);

        return redirect()->to('/admin/students/import');
    }

    private function redirectToQr(Ceremony $ceremony): RedirectResponse
    {
        Gate::authorize('viewQrCodes', Student::class);

        return redirect()->route('admin.ceremonies.students.qr.index', $ceremony);
    }

    /**
     * @return list<array<string, string|int>>
     */
    private function readinessSections(Ceremony $ceremony): array
    {
        $counts = app(CeremonyWorkspaceSummary::class)->forCeremony($ceremony)['counts'];

        return [
            [
                'title' => 'Graduate readiness',
                'detail' => "{$counts['graduates_total']} records prepared",
                'action_label' => 'Review graduates',
                'action_href' => route('admin.ceremonies.students.index', $ceremony),
            ],
            [
                'title' => 'QR readiness',
                'detail' => "{$counts['qr_ready']} ready / {$counts['qr_missing']} missing",
                'action_label' => 'Open QR & Media',
                'action_href' => route('admin.ceremonies.students.qr.index', $ceremony),
            ],
            [
                'title' => 'Media readiness',
                'detail' => "{$counts['photos_missing']} photos missing",
                'action_label' => 'Review media gaps',
                'action_href' => route('admin.ceremonies.students.index', $ceremony).'?media=missing_photo',
            ],
            [
                'title' => 'Announcement readiness',
                'detail' => "{$counts['announcements_ready']} ready / {$counts['announcements_needs_review']} need review / {$counts['announcements_missing']} missing",
                'action_label' => 'Review announcements',
                'action_href' => route('admin.ceremonies.announcements', $ceremony),
            ],
            [
                'title' => 'Stage configuration',
                'detail' => 'Stage appearance and presentation settings are stored on this ceremony.',
                'action_label' => 'Open stage design',
                'action_href' => route('admin.ceremonies.section', [
                    'ceremony' => $ceremony,
                    'section' => 'stage-design',
                ]),
            ],
            [
                'title' => 'Live-operation readiness',
                'detail' => "Presentation mode: {$ceremony->presentation_mode->label()}",
                'action_label' => 'Open setup',
                'action_href' => route('admin.ceremonies.setup', $ceremony),
            ],
        ];
    }

    /**
     * @return array<string, string|null>|null
     */
    private function lastBackupSummary(): ?array
    {
        $lastBackup = BackupRecord::query()
            ->latest('started_at')
            ->latest('id')
            ->first();

        if ($lastBackup === null) {
            return null;
        }

        return [
            'identifier' => $lastBackup->identifier,
            'status' => $lastBackup->status,
            'verification_status' => $lastBackup->verification_status,
            'completed_at' => $lastBackup->completed_at?->toIso8601String(),
        ];
    }

    /**
     * @param  list<array{href: string, label: string, tone: string}>  $actions
     */
    private function renderWorkspaceBridge(
        Request $request,
        Ceremony $ceremony,
        string $section,
        string $title,
        string $message,
        array $actions,
    ): Response {
        return Inertia::render('Admin/Ceremonies/WorkspaceBridge', [
            'ceremony' => $this->serialize($ceremony),
            'bridge' => [
                'section' => $section,
                'title' => $title,
                'message' => $message,
                'actions' => $actions,
                'legacy_edit_url' => $request->user()->can('update', $ceremony)
                    ? route('admin.ceremonies.edit', $ceremony)
                    : null,
            ],
            'workingCeremonyContext' => $this->serializeWorkingCeremonyContext(
                $request,
                app(WorkingCeremonyResolver::class),
                $ceremony,
            ),
        ]);
    }

    /**
     * @return array{
     *     can_switch: bool,
     *     ceremony: array<string, mixed>|null,
     *     available: list<array<string, mixed>>
     * }
     */
    private function serializeWorkingCeremonyContext(
        Request $request,
        WorkingCeremonyResolver $workingCeremonies,
        Ceremony $ceremony,
    ): array {
        $available = $workingCeremonies->availableFor($request->user());

        return [
            'can_switch' => $workingCeremonies->canSwitch($request->user()),
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'event_date' => $ceremony->event_date?->format('Y-m-d'),
                'status' => [
                    'value' => $ceremony->status->value,
                    'label' => $ceremony->status->label(),
                ],
            ],
            'available' => $available->map(static fn (Ceremony $availableCeremony): array => [
                'id' => $availableCeremony->id,
                'name' => $availableCeremony->name,
                'academic_year' => $availableCeremony->academic_year,
                'event_date' => $availableCeremony->event_date?->format('Y-m-d'),
                'status' => [
                    'value' => $availableCeremony->status->value,
                    'label' => $availableCeremony->status->label(),
                ],
            ])->values()->all(),
        ];
    }
}
