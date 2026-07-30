<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\StudentAdministration;
use App\Enums\EligibilityStatus;
use App\Enums\StageQueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\SaveStudentRequest;
use App\Models\Ceremony;
use App\Models\Student;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class StudentController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Student::class);

        $filters = [
            'search' => trim($request->string('search')->toString()),
            'ceremony_id' => $request->string('ceremony_id')->toString(),
            'college' => $request->string('college')->toString(),
            'program' => $request->string('program')->toString(),
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
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Student::class);

        return Inertia::render('Admin/Students/Create', [
            'formOptions' => $this->formOptions(),
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

    public function show(Student $student): Response
    {
        Gate::authorize('view', $student);

        $student->load([
            'ceremony:id,name,academic_year',
            'latestQueueItem' => fn ($query) => $query->select([
                'stage_queue_items.id',
                'stage_queue_items.student_id',
                'stage_queue_items.status',
                'stage_queue_items.scanned_at',
                'stage_queue_items.presented_at',
                'stage_queue_items.completed_at',
            ]),
        ])->loadCount(['queueItems', 'scanLogs']);

        return Inertia::render('Admin/Students/Show', [
            'student' => $this->serializeDetail($student),
        ]);
    }

    public function edit(Student $student): Response
    {
        Gate::authorize('update', $student);

        $student->load('ceremony:id,name');

        return Inertia::render('Admin/Students/Edit', [
            'student' => $this->serializeForm($student),
            'formOptions' => $this->formOptions(),
        ]);
    }

    public function update(
        SaveStudentRequest $request,
        Student $student,
        StudentAdministration $administration,
    ): RedirectResponse {
        $student = $administration->update(
            $student,
            $request->validated(),
            $request->file('photo'),
            $request->file('audio'),
            $request->user(),
            $request->ip(),
        );

        return redirect()
            ->route('admin.students.show', $student)
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

    private function applyMediaFilter(Builder $query, string $filter): void
    {
        match ($filter) {
            'ready' => $query->whereNotNull('photo_path')->whereNotNull('audio_path'),
            'missing_photo' => $query->whereNull('photo_path'),
            'missing_audio' => $query->whereNull('audio_path'),
            'missing_any' => $query->where(
                fn (Builder $query) => $query
                    ->whereNull('photo_path')
                    ->orWhereNull('audio_path'),
            ),
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
                'audio' => $student->audio_path === null ? 'Missing' : 'Ready',
            ],
            'stage_status' => $this->stageStatus($student),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeDetail(Student $student): array
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
                'audio_url' => $this->mediaUrl($student->audio_path),
            ],
            'stage_status' => $this->stageStatus($student),
            'queue_items_count' => $student->queue_items_count,
            'scan_logs_count' => $student->scan_logs_count,
            'created_at' => $student->created_at?->toIso8601String(),
            'updated_at' => $student->updated_at?->toIso8601String(),
        ];
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
            'college' => $student->college,
            'program' => $student->program,
            'major' => $student->major,
            'honors' => $student->honors,
            'sequence_number' => $student->sequence_number,
            'eligibility_status' => $student->eligibility_status->value,
            'qr_token_configured' => $student->qr_token !== '',
            'photo_url' => $this->mediaUrl($student->photo_path),
            'audio_url' => $this->mediaUrl($student->audio_path),
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
    private function formOptions(): array
    {
        return [
            'ceremonies' => Ceremony::query()
                ->orderByRaw('event_date DESC NULLS LAST')
                ->orderBy('name')
                ->get(['id', 'name', 'academic_year', 'status'])
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
}
