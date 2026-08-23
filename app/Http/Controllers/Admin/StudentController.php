<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StudentAdministration;
use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStudentRequest;
use App\Models\Ceremony;
use App\Models\Student;
use App\Services\StudentAnnouncementWorkspace;
use App\Support\AnnouncementAudio;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function __construct(
        private readonly AnnouncementAudio $announcementAudio,
        private readonly StudentAnnouncementWorkspace $announcementWorkspace,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Student::class);

        $workspaceCeremony = $this->resolveWorkspaceCeremony($request);

        $filters = [
            'search' => trim($request->string('search')->toString()),
            'ceremony_id' => $this->resolvedCeremonyFilter($request, $workspaceCeremony),
            'college' => $request->string('college')->toString(),
            'program' => $request->string('program')->toString(),
            'preparation_status' => $request->string('preparation_status')->toString(),
            'eligibility_status' => $request->string('eligibility_status')->toString(),
            'media' => $request->string('media')->toString(),
            'stage_status' => $request->string('stage_status')->toString(),
            'sort' => $request->string('sort', 'sequence')->toString(),
        ];

        $students = Student::query()
            ->with([
                'ceremony:id,name',
                'latestQueueItem' => fn ($query) => $query->select([
                    'stage_queue_items.id',
                    'stage_queue_items.student_id',
                    'stage_queue_items.status',
                ]),
            ])
            ->when($filters['search'] !== '', function (Builder $query) use ($filters): void {
                $search = '%'.$filters['search'].'%';

                $query->where(function (Builder $query) use ($search): void {
                    $query
                        ->where('display_name', 'ilike', $search)
                        ->orWhere('announcement_name', 'ilike', $search)
                        ->orWhere('first_name', 'ilike', $search)
                        ->orWhere('last_name', 'ilike', $search)
                        ->orWhere('student_number', 'ilike', $search);
                });
            })
            ->when(
                $filters['ceremony_id'] !== '',
                fn (Builder $query) => $query->where(
                    'ceremony_id',
                    $filters['ceremony_id'],
                ),
            )
            ->when(
                $filters['college'] !== '',
                fn (Builder $query) => $query->where('college', $filters['college']),
            )
            ->when(
                $filters['program'] !== '',
                fn (Builder $query) => $query->where('program', $filters['program']),
            )
            ->when(
                $filters['preparation_status'] !== '',
                fn (Builder $query) => $this->applyPreparationStatusFilter(
                    $query,
                    $filters['preparation_status'],
                ),
            )
            ->when(
                $filters['eligibility_status'] !== '',
                fn (Builder $query) => $query->where(
                    'eligibility_status',
                    $filters['eligibility_status'],
                ),
            )
            ->when(
                $filters['media'] !== '',
                fn (Builder $query) => $this->applyMediaFilter(
                    $query,
                    $filters['media'],
                ),
            )
            ->when(
                $filters['stage_status'] !== '',
                fn (Builder $query) => $this->applyStageStatusFilter(
                    $query,
                    $filters['stage_status'],
                ),
            );

        $this->applySort($students, $filters['sort']);

        $students = $students
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Student $student): array => $this->serializeListItem($student));

        return Inertia::render('Admin/Students/Index', [
            'students' => $students,
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'workspace' => $this->workspaceContext(
                $workspaceCeremony,
                $request->route('ceremony') !== null,
            ),
        ]);
    }

    public function create(Request $request): Response
    {
        Gate::authorize('create', Student::class);

        $workspaceCeremony = $this->resolveWorkspaceCeremony($request);

        return Inertia::render('Admin/Students/Create', [
            'formOptions' => $this->formOptions($workspaceCeremony),
            'workspace' => $this->workspaceContext(
                $workspaceCeremony,
                $request->route('ceremony') !== null,
            ),
        ]);
    }

    public function store(
        SaveStudentRequest $request,
        StudentAdministration $administration,
    ): RedirectResponse {
        $student = $administration->create(
            $request->validated(),
            $request->file('photo'),
            $request->file('audio'),
            $request->user(),
            $request->ip(),
        );

        return redirect()
            ->route('admin.students.show', $student)
            ->with('success', 'Graduate record created.');
    }

    public function show(Request $request, Student $student): Response
    {
        return $this->detail($request, $student);
    }

    public function showInCeremony(
        Request $request,
        Ceremony $ceremony,
        Student $student,
    ): Response {
        $this->ensureStudentBelongsToCeremony($student, $ceremony);

        return $this->detail($request, $student);
    }

    private function detail(Request $request, Student $student): Response
    {
        Gate::authorize('view', $student);

        $section = $request->string('section', 'overview')->toString();
        abort_unless(in_array($section, [
            'overview', 'academic', 'media', 'announcement', 'history',
        ], true), 404);

        $student->load([
            'ceremony:id,name,academic_year',
            'audioApprover:id,name',
            'latestQueueItem' => fn ($query) => $query->select([
                'stage_queue_items.id',
                'stage_queue_items.student_id',
                'stage_queue_items.status',
                'stage_queue_items.scanned_at',
                'stage_queue_items.presented_at',
                'stage_queue_items.completed_at',
            ]),
        ]);

        if (in_array($section, ['overview', 'history'], true)) {
            $student->loadCount(['queueItems', 'scanLogs']);
        }

        if ($section === 'history') {
            $student->load([
                'queueItems' => fn ($query) => $query->latest('id')->limit(20),
                'scanLogs' => fn ($query) => $query->latest('scanned_at')->limit(20),
            ]);
        }

        return Inertia::render('Admin/Students/Show', [
            'section' => $section,
            'student' => $this->serializeDetail($student, $section),
            'workspace' => $this->workspaceContextForStudent($request, $student),
            ...($section === 'announcement' ? [
                'announcement' => $this->announcementWorkspace->forStudent($student, $request->user()),
            ] : []),
        ]);
    }

    public function edit(Request $request, Student $student): Response
    {
        return $this->editDetail($request, $student);
    }

    public function editInCeremony(Request $request, Ceremony $ceremony, Student $student): Response
    {
        $this->ensureStudentBelongsToCeremony($student, $ceremony);

        return $this->editDetail($request, $student);
    }

    private function editDetail(Request $request, Student $student): Response
    {
        Gate::authorize('update', $student);

        $student->load(['ceremony:id,name', 'audioApprover:id,name']);

        return Inertia::render('Admin/Students/Edit', [
            'student' => $this->serializeForm($student),
            'formOptions' => $this->formOptions($student->ceremony),
            'workspace' => $this->workspaceContextForStudent($request, $student),
        ]);
    }

    public function update(
        SaveStudentRequest $request,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        return $this->saveStudent($request, $student, $administration);
    }

    public function updateInCeremony(
        SaveStudentRequest $request,
        Ceremony $ceremony,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        $this->ensureStudentBelongsToCeremony($student, $ceremony);

        return $this->saveStudent($request, $student, $administration, $ceremony);
    }

    private function saveStudent(
        SaveStudentRequest $request,
        Student $student,
        StudentAdministration $administration,
        ?Ceremony $ceremony = null,
    ): RedirectResponse {
        $student = $administration->update(
            $student,
            $request->validated(),
            $request->file('photo'),
            $request->file('audio'),
            $request->user(),
            $request->ip(),
        );

        return redirect()->to(
            $ceremony === null
                ? route('admin.students.show', $student)
                : route('admin.ceremonies.students.show', [$ceremony, $student]).'?section=academic',
        )
            ->with('success', 'Graduate changes saved.');
    }

    public function destroy(
        Request $request,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        Gate::authorize('delete', $student);

        $administration->delete($student, $request->user(), $request->ip());

        return redirect()
            ->route('admin.students.index')
            ->with('success', 'Unused graduate record deleted.');
    }

    public function destroyPhoto(
        Request $request,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        Gate::authorize('update', $student);

        $administration->removeMedia(
            $student,
            'photo',
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Graduate photo removed.');
    }

    public function destroyAudio(
        Request $request,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        Gate::authorize('update', $student);

        $administration->removeMedia(
            $student,
            'audio',
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Announcement audio removed.');
    }

    public function markAudioNeedsReview(
        Request $request,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        Gate::authorize('markAudioNeedsReview', $student);

        $validated = $request->validate([
            'audio_review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $administration->markAudioNeedsReview(
            $student,
            $validated['audio_review_notes'] ?? null,
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Announcement audio marked for review.');
    }

    public function approveAudio(
        Request $request,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        Gate::authorize('approveAudio', $student);

        $validated = $request->validate([
            'audio_review_notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $administration->approveAudio(
            $student,
            $validated['audio_review_notes'] ?? null,
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Announcement audio approved.');
    }

    public function rejectAudio(
        Request $request,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        Gate::authorize('rejectAudio', $student);

        $validated = $request->validate([
            'audio_review_notes' => ['required', 'string', 'max:1000'],
        ]);

        $administration->rejectAudio(
            $student,
            $validated['audio_review_notes'],
            $request->user(),
            $request->ip(),
        );

        return back()->with('success', 'Announcement audio rejected.');
    }

    private function applyMediaFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'ready' => $query
                ->whereNotNull('photo_path')
                ->where('audio_readiness_status', AudioReadinessStatus::Approved),
            'missing_photo' => $query->whereNull('photo_path'),
            'missing_audio' => $query->where('audio_readiness_status', AudioReadinessStatus::Missing),
            'audio_uploaded' => $query->where('audio_readiness_status', AudioReadinessStatus::Uploaded),
            'audio_needs_review' => $query->where('audio_readiness_status', AudioReadinessStatus::NeedsReview),
            'audio_approved' => $query->where('audio_readiness_status', AudioReadinessStatus::Approved),
            'audio_rejected' => $query->where('audio_readiness_status', AudioReadinessStatus::Rejected),
            'missing_any' => $query->where(
                fn (Builder $query) => $query
                    ->whereNull('photo_path')
                    ->orWhereNull('audio_path'),
            ),
            default => null,
        };
    }

    private function applyPreparationStatusFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'ready' => $query
                ->where('eligibility_status', EligibilityStatus::Eligible)
                ->whereNotNull('photo_path')
                ->where('audio_readiness_status', AudioReadinessStatus::Approved),
            'eligibility_review' => $query->where('eligibility_status', '!=', EligibilityStatus::Eligible),
            'photo_missing' => $query->whereNull('photo_path'),
            'announcement_missing' => $query->where('audio_readiness_status', AudioReadinessStatus::Missing),
            'announcement_review' => $query->whereIn('audio_readiness_status', [
                AudioReadinessStatus::Uploaded,
                AudioReadinessStatus::NeedsReview,
                AudioReadinessStatus::Rejected,
            ]),
            default => null,
        };
    }

    private function applyStageStatusFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'presenting' => $query->whereHas(
                'queueItems',
                fn (Builder $query) => $query->where(
                    'status',
                    StageQueueStatus::Presenting->value,
                ),
            ),
            'completed' => $query->whereHas(
                'queueItems',
                fn (Builder $query) => $query->where(
                    'status',
                    StageQueueStatus::Completed->value,
                ),
            ),
            'not_presented' => $query->whereDoesntHave(
                'queueItems',
                fn (Builder $query) => $query->whereIn('status', [
                    StageQueueStatus::Presenting->value,
                    StageQueueStatus::Announced->value,
                    StageQueueStatus::Completed->value,
                ]),
            ),
            default => null,
        };
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'name' => $query->orderBy('display_name')->orderBy('id'),
            'student_number' => $query->orderBy('student_number')->orderBy('id'),
            default => $query
                ->orderBy('ceremony_id')
                ->orderBy('sequence_number')
                ->orderBy('id'),
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeListItem(Student $student): array
    {
        $preparationStatus = $this->preparationStatus($student);

        return [
            'id' => $student->id,
            'student_number' => $student->student_number,
            'display_name' => $student->display_name,
            'ceremony' => [
                'id' => $student->ceremony->id,
                'name' => $student->ceremony->name,
            ],
            'college' => $student->college,
            'program' => $student->program,
            'sequence_number' => $student->sequence_number,
            'eligibility_status' => [
                'value' => $student->eligibility_status->value,
                'label' => $student->eligibility_status->label(),
            ],
            'media' => [
                'photo' => $student->photo_path === null ? 'Missing' : 'Ready',
                'audio' => $student->audio_readiness_status->label(),
                'audio_status' => [
                    'value' => $student->audio_readiness_status->value,
                    'label' => $student->audio_readiness_status->label(),
                ],
            ],
            'stage_status' => $this->stageStatus($student),
            'preparation_status' => $preparationStatus,
        ];
    }

    /** @return array{value: string, label: string, detail: string} */
    private function preparationStatus(Student $student): array
    {
        if ($student->eligibility_status !== EligibilityStatus::Eligible) {
            return [
                'value' => 'eligibility_review',
                'label' => 'Needs eligibility review',
                'detail' => 'Confirm eligibility before the ceremony.',
            ];
        }

        if ($student->photo_path === null) {
            return [
                'value' => 'photo_missing',
                'label' => 'Photo missing',
                'detail' => 'Add a graduate photo before QR preparation.',
            ];
        }

        if ($student->audio_readiness_status === AudioReadinessStatus::Missing) {
            return [
                'value' => 'announcement_missing',
                'label' => 'Announcement missing',
                'detail' => 'Prepare or record the announcement.',
            ];
        }

        if ($student->audio_readiness_status !== AudioReadinessStatus::Approved) {
            return [
                'value' => 'announcement_review',
                'label' => 'Announcement needs review',
                'detail' => 'Review the announcement before stage use.',
            ];
        }

        return [
            'value' => 'ready',
            'label' => 'Ready',
            'detail' => 'Graduate is ready for ceremony preparation.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(Student $student, string $section): array
    {
        return [
            ...$this->serializeForm($student),
            'qr_token_configured' => $student->qr_token !== '',
            'ceremony' => [
                'id' => $student->ceremony->id,
                'name' => $student->ceremony->name,
                'academic_year' => $student->ceremony->academic_year,
            ],
            'eligibility' => [
                'value' => $student->eligibility_status->value,
                'label' => $student->eligibility_status->label(),
            ],
            'media' => [
                'photo_url' => $this->mediaUrl($student->photo_path),
                'audio_url' => $this->announcementAudio->previewUrl($student),
                'audio_filename' => $this->announcementAudio->safeFilename($student->audio_path),
                'audio_status' => [
                    'value' => $student->audio_readiness_status->value,
                    'label' => $student->audio_readiness_status->label(),
                ],
                'audio_stage_label' => $student->audio_readiness_status->stageLabel(),
                'audio_uploaded_at' => $student->audio_uploaded_at?->toIso8601String(),
                'audio_approved_at' => $student->audio_approved_at?->toIso8601String(),
                'audio_approved_by' => $student->audioApprover?->name,
                'audio_review_notes' => $student->audio_review_notes,
                'audio_duration_seconds' => $student->audio_duration_seconds,
                'audio_mime_type' => $student->audio_mime_type,
                'audio_file_size' => $student->audio_file_size,
            ],
            'stage_status' => $this->stageStatus($student),
            'queue_items_count' => $student->queue_items_count ?? null,
            'scan_logs_count' => $student->scan_logs_count ?? null,
            'created_at' => $section === 'history' ? $student->created_at?->toIso8601String() : null,
            'updated_at' => $section === 'history' ? $student->updated_at?->toIso8601String() : null,
            'awards' => $section === 'academic' ? $student->awards()->get()->map(fn ($award) => [
                'id' => $award->id,
                'award_name' => $award->award_name,
                'spoken_text' => $award->spoken_text,
                'is_announcement_enabled' => $award->is_announcement_enabled,
            ])->all() : [],
            'queue_history' => $section === 'history' ? $student->queueItems->map(fn ($item) => [
                'id' => $item->id,
                'status' => $this->stageStatusForValue($item->status),
                'occurred_at' => ($item->completed_at ?? $item->presented_at ?? $item->scanned_at ?? $item->created_at)?->toIso8601String(),
            ])->all() : [],
            'scan_history' => $section === 'history' ? $student->scanLogs->map(fn ($log) => [
                'id' => $log->id,
                'result' => str($log->result->value)->replace('_', ' ')->title()->toString(),
                'occurred_at' => $log->scanned_at?->toIso8601String(),
            ])->all() : [],
        ];
    }

    private function stageStatusForValue(StageQueueStatus $status): string
    {
        return match ($status) {
            StageQueueStatus::Waiting => 'Waiting',
            StageQueueStatus::Ready => 'Ready',
            StageQueueStatus::Presenting => 'Presenting',
            StageQueueStatus::Announced => 'Announced',
            StageQueueStatus::Completed => 'Completed',
            StageQueueStatus::Skipped => 'Skipped',
            StageQueueStatus::Cancelled => 'Cancelled',
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeForm(Student $student): array
    {
        return [
            'id' => $student->id,
            'ceremony_id' => $student->ceremony_id,
            'student_number' => $student->student_number,
            'first_name' => $student->first_name,
            'middle_name' => $student->middle_name,
            'last_name' => $student->last_name,
            'suffix' => $student->suffix,
            'display_name' => $student->display_name,
            'announcement_name' => $student->announcement_name,
            'pronunciation_guide' => $student->pronunciation_guide,
            'tts_spoken_name' => $student->tts_spoken_name,
            'college' => $student->college,
            'program' => $student->program,
            'major' => $student->major,
            'honors' => $student->honors,
            'announcement_additional_text' => $student->announcement_additional_text,
            'sequence_number' => $student->sequence_number,
            'eligibility_status' => $student->eligibility_status->value,
            'qr_token_configured' => $student->qr_token !== '',
            'photo_url' => $this->mediaUrl($student->photo_path),
            'audio_url' => $this->announcementAudio->previewUrl($student),
            'audio' => [
                'status' => [
                    'value' => $student->audio_readiness_status->value,
                    'label' => $student->audio_readiness_status->label(),
                ],
                'filename' => $this->announcementAudio->safeFilename($student->audio_path),
                'uploaded_at' => $student->audio_uploaded_at?->toIso8601String(),
                'approved_at' => $student->audio_approved_at?->toIso8601String(),
                'approved_by' => $student->audioApprover?->name,
                'review_notes' => $student->audio_review_notes,
                'mime_type' => $student->audio_mime_type,
                'file_size' => $student->audio_file_size,
                'duration_seconds' => $student->audio_duration_seconds,
            ],
        ];
    }

    /**
     * @return array{value: string, label: string}
     */
    private function stageStatus(Student $student): array
    {
        $status = $student->latestQueueItem?->status;

        if ($status === null) {
            return ['value' => 'not_presented', 'label' => 'Not presented'];
        }

        return [
            'value' => $status->value,
            'label' => match ($status) {
                StageQueueStatus::Waiting => 'Waiting',
                StageQueueStatus::Ready => 'Ready',
                StageQueueStatus::Presenting => 'Presenting',
                StageQueueStatus::Announced => 'Announced',
                StageQueueStatus::Completed => 'Completed',
                StageQueueStatus::Skipped => 'Skipped',
                StageQueueStatus::Cancelled => 'Cancelled',
            },
        ];
    }

    private function mediaUrl(?string $path): ?string
    {
        if ($path === null || str_contains($path, '..')) {
            return null;
        }

        return '/'.ltrim($path, '/');
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            ...$this->formOptions(),
            'colleges' => Student::query()
                ->whereNotNull('college')
                ->distinct()
                ->orderBy('college')
                ->pluck('college'),
            'programs' => Student::query()
                ->whereNotNull('program')
                ->distinct()
                ->orderBy('program')
                ->pluck('program'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function formOptions(?Ceremony $lockedCeremony = null): array
    {
        $ceremonies = Ceremony::query()
            ->orderByRaw('event_date DESC NULLS LAST')
            ->orderBy('name')
            ->get(['id', 'name', 'academic_year', 'status']);

        if ($lockedCeremony !== null) {
            $ceremonies = $ceremonies->where('id', $lockedCeremony->id)->values();
        }

        return [
            'ceremonies' => $ceremonies
                ->map(fn (Ceremony $ceremony): array => [
                    'id' => $ceremony->id,
                    'name' => $ceremony->name,
                    'academic_year' => $ceremony->academic_year,
                    'status' => $ceremony->status->label(),
                ]),
            'eligibilityStatuses' => array_map(
                fn (EligibilityStatus $status): array => [
                    'value' => $status->value,
                    'label' => $status->label(),
                ],
                EligibilityStatus::cases(),
            ),
        ];
    }

    private function resolveWorkspaceCeremony(Request $request): ?Ceremony
    {
        $routeCeremonyId = $request->route('ceremony');

        if ($routeCeremonyId !== null) {
            $routeCeremony = $routeCeremonyId instanceof Ceremony
                ? $routeCeremonyId
                : Ceremony::query()->findOrFail($routeCeremonyId);
            Gate::authorize('view', $routeCeremony);
            $workingCeremonies = app(WorkingCeremonyResolver::class);

            if ($workingCeremonies->canSwitch($request->user())) {
                $workingCeremonies->store($request, $routeCeremony);
            }

            return $routeCeremony;
        }

        return app(WorkingCeremonyResolver::class)->resolve($request);
    }

    private function resolvedCeremonyFilter(Request $request, ?Ceremony $workspaceCeremony): string
    {
        $requested = $request->string('ceremony_id')->toString();

        if ($requested !== '') {
            return $requested;
        }

        return $workspaceCeremony === null ? '' : (string) $workspaceCeremony->id;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function workspaceContext(?Ceremony $ceremony, bool $locked = false): ?array
    {
        if ($ceremony === null) {
            return null;
        }

        return [
            'locked' => $locked,
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'status' => $ceremony->status->label(),
            ],
            'urls' => [
                'graduates' => route('admin.ceremonies.students.index', $ceremony),
                'create' => route('admin.ceremonies.students.create', $ceremony),
                'import' => route('admin.ceremonies.students.import.index', $ceremony),
                'qr' => route('admin.ceremonies.students.qr.index', $ceremony),
                'announcements' => route('admin.ceremonies.announcements', $ceremony),
                'overview' => route('admin.ceremonies.show', $ceremony),
                'allGraduates' => route('admin.students.index'),
            ],
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    private function workspaceContextForStudent(Request $request, Student $student): ?array
    {
        $workspaceCeremony = $this->resolveWorkspaceCeremony($request);

        if (
            $workspaceCeremony === null
            || (int) $workspaceCeremony->id !== (int) $student->ceremony_id
        ) {
            return null;
        }

        return $this->workspaceContext(
            $workspaceCeremony,
            $request->route('ceremony') !== null,
        );
    }

    private function ensureStudentBelongsToCeremony(Student $student, Ceremony $ceremony): void
    {
        abort_unless((int) $student->ceremony_id === (int) $ceremony->id, 404);
    }
}
