<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\PrepareStudentQrBatch;
use App\Actions\Admin\RegenerateStudentQrToken;
use App\Enums\StageQueueStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ListStudentQrCodesRequest;
use App\Http\Requests\PrintStudentQrBatchRequest;
use App\Http\Requests\RegenerateStudentQrCodeRequest;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Services\QrCodeRenderer;
use App\Services\StudentQrTokenVersion;
use App\Support\StudentQrReadiness;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class StudentQrCodeController extends Controller
{
    public function index(
        ListStudentQrCodesRequest $request,
        StudentQrReadiness $readiness,
    ): InertiaResponse {
        $validated = $request->validated();
        $ceremonies = Ceremony::query()
            ->orderByRaw("CASE WHEN status = 'active' THEN 0 ELSE 1 END")
            ->orderByRaw('event_date DESC NULLS LAST')
            ->orderBy('name')
            ->get(['id', 'name', 'academic_year', 'status']);
        $selectedCeremonyId = isset($validated['ceremony_id'])
            ? (int) $validated['ceremony_id']
            : $ceremonies->first()?->id;
        $filters = [
            'ceremony_id' => $selectedCeremonyId,
            'college' => $validated['college'] ?? null,
            'program' => $validated['program'] ?? null,
            'eligibility_status' => $validated['eligibility_status'] ?? null,
            'qr_readiness' => $validated['qr_readiness'] ?? null,
            'sequence_from' => $validated['sequence_from'] ?? null,
            'sequence_to' => $validated['sequence_to'] ?? null,
        ];

        $query = Student::query()
            ->with([
                'ceremony:id,name,academic_year',
                'latestQueueItem' => fn ($query) => $query->select([
                    'stage_queue_items.id',
                    'stage_queue_items.student_id',
                    'stage_queue_items.status',
                ]),
            ])
            ->withCount([
                'queueItems as completed_queue_items_count' => fn ($query) => $query
                    ->where('status', StageQueueStatus::Completed->value),
                'queueItems as presenting_queue_items_count' => fn ($query) => $query
                    ->where('status', StageQueueStatus::Presenting->value),
            ]);

        if ($selectedCeremonyId === null) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where('ceremony_id', $selectedCeremonyId);
        }

        $query
            ->when(
                $filters['college'] !== null,
                fn (Builder $query) => $query->where(
                    'college',
                    $filters['college'],
                ),
            )
            ->when(
                $filters['program'] !== null,
                fn (Builder $query) => $query->where(
                    'program',
                    $filters['program'],
                ),
            )
            ->when(
                $filters['eligibility_status'] !== null,
                fn (Builder $query) => $query->where(
                    'eligibility_status',
                    $filters['eligibility_status'],
                ),
            )
            ->when(
                $filters['sequence_from'] !== null,
                fn (Builder $query) => $query->where(
                    'sequence_number',
                    '>=',
                    $filters['sequence_from'],
                ),
            )
            ->when(
                $filters['sequence_to'] !== null,
                fn (Builder $query) => $query->where(
                    'sequence_number',
                    '<=',
                    $filters['sequence_to'],
                ),
            );

        if ($filters['qr_readiness'] !== null) {
            $method = $filters['qr_readiness'] === 'regenerated'
                ? 'whereExists'
                : 'whereNotExists';

            $query->{$method}(fn ($query) => $query
                ->selectRaw('1')
                ->from('audit_logs')
                ->whereColumn('audit_logs.entity_id', 'students.id')
                ->where('audit_logs.entity_type', 'student')
                ->where('audit_logs.action', 'student.qr_token_regenerated'));
        }

        $students = $query
            ->orderBy('sequence_number')
            ->orderBy('display_name')
            ->orderBy('id')
            ->paginate(25)
            ->withQueryString();
        $pageIds = $students->getCollection()->pluck('id');
        $regenerationTimes = $pageIds->isEmpty()
            ? collect()
            : AuditLog::query()
                ->select('entity_id', DB::raw('MAX(created_at) AS regenerated_at'))
                ->where('entity_type', 'student')
                ->where('action', 'student.qr_token_regenerated')
                ->whereIn('entity_id', $pageIds)
                ->groupBy('entity_id')
                ->pluck('regenerated_at', 'entity_id');

        $students->through(function (Student $student) use (
            $readiness,
            $regenerationTimes,
        ): array {
            $student->setAttribute(
                'is_presenting',
                $student->presenting_queue_items_count > 0,
            );
            $regeneratedAt = $regenerationTimes->get($student->id);

            return [
                'id' => $student->id,
                'display_name' => $student->display_name,
                'student_number' => $student->student_number,
                'college' => $student->college,
                'program' => $student->program,
                'sequence_number' => $student->sequence_number,
                'eligibility' => [
                    'value' => $student->eligibility_status->value,
                    'label' => $student->eligibility_status->label(),
                ],
                'qr' => $readiness->summarize(
                    $student,
                    is_string($regeneratedAt) ? $regeneratedAt : null,
                ),
            ];
        });

        $optionQuery = Student::query();

        if ($selectedCeremonyId === null) {
            $optionQuery->whereRaw('1 = 0');
        } else {
            $optionQuery->where('ceremony_id', $selectedCeremonyId);
        }

        return Inertia::render('Admin/Students/QrCodes/Index', [
            'students' => $students,
            'filters' => $filters,
            'filterOptions' => [
                'ceremonies' => $ceremonies->map(fn (Ceremony $ceremony): array => [
                    'id' => $ceremony->id,
                    'name' => $ceremony->name,
                    'academic_year' => $ceremony->academic_year,
                    'status' => $ceremony->status->label(),
                ]),
                'colleges' => (clone $optionQuery)
                    ->whereNotNull('college')
                    ->distinct()
                    ->orderBy('college')
                    ->pluck('college'),
                'programs' => (clone $optionQuery)
                    ->whereNotNull('program')
                    ->distinct()
                    ->orderBy('program')
                    ->pluck('program'),
            ],
            'maximumBatchSize' => PrepareStudentQrBatch::MAXIMUM_SIZE,
            'csrfToken' => csrf_token(),
        ]);
    }

    public function show(
        Student $student,
        StudentQrReadiness $readiness,
        StudentQrTokenVersion $versions,
    ): InertiaResponse {
        Gate::authorize('viewQrCode', $student);
        $student->load('ceremony:id,name,academic_year')->loadCount([
            'queueItems as completed_queue_items_count' => fn ($query) => $query
                ->where('status', StageQueueStatus::Completed->value),
            'queueItems as presenting_queue_items_count' => fn ($query) => $query
                ->where('status', StageQueueStatus::Presenting->value),
        ]);
        $student->setAttribute(
            'is_presenting',
            $student->presenting_queue_items_count > 0,
        );
        $historyQuery = AuditLog::query()
            ->where('entity_type', 'student')
            ->where('entity_id', $student->id)
            ->where('action', 'student.qr_token_regenerated')
            ->latest('created_at');
        $lastRegeneration = (clone $historyQuery)->value('created_at');
        $history = [];

        $requestUser = request()->user();

        if ($requestUser?->isAdministrator()) {
            $history = $historyQuery
                ->with('user:id,name')
                ->limit(10)
                ->get()
                ->map(fn (AuditLog $audit): array => [
                    'id' => $audit->id,
                    'actor' => $audit->user?->name ?? 'Former user',
                    'created_at' => $audit->created_at->toIso8601String(),
                    'ip_address' => $audit->ip_address,
                ])
                ->all();
        }

        return Inertia::render('Admin/Students/QrCodes/Show', [
            'student' => [
                ...$this->cardIdentity($student),
                'eligibility' => [
                    'value' => $student->eligibility_status->value,
                    'label' => $student->eligibility_status->label(),
                ],
                'qr' => $readiness->summarize(
                    $student,
                    $lastRegeneration === null
                        ? null
                        : (string) $lastRegeneration,
                ),
                'qr_image_url' => route(
                    'admin.students.qr-codes.image',
                    $student,
                    false,
                ),
                'regeneration_version' => $versions->fingerprint($student),
            ],
            'regenerationHistory' => $history,
        ]);
    }

    public function image(
        Student $student,
        QrCodeRenderer $renderer,
        StudentQrReadiness $readiness,
    ): Response {
        Gate::authorize('printQrCode', $student);
        $this->assertPrintable($student, $readiness);

        return response($renderer->svg($student->qr_token))
            ->header('Content-Type', 'image/svg+xml; charset=UTF-8')
            ->header(
                'Content-Disposition',
                "inline; filename=\"graduate-{$student->id}-qr.svg\"",
            )
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('X-Content-Type-Options', 'nosniff')
            ->header('X-Robots-Tag', 'noindex, noarchive')
            ->header('Content-Security-Policy', "default-src 'none'");
    }

    public function print(
        Student $student,
        QrCodeRenderer $renderer,
        StudentQrReadiness $readiness,
    ): Response {
        Gate::authorize('printQrCode', $student);
        $this->assertPrintable($student, $readiness);
        $student->loadMissing('ceremony:id,name,academic_year');

        return response()
            ->view('admin.student-qr-print', [
                'mode' => 'individual',
                'title' => 'Individual graduate QR card',
                'ceremony' => $student->ceremony,
                'cards' => [[
                    ...$this->cardIdentity($student),
                    'qr_data_uri' => $renderer->dataUri($student->qr_token),
                ]],
                'generatedAt' => now(),
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, noarchive');
    }

    public function batch(
        PrintStudentQrBatchRequest $request,
        PrepareStudentQrBatch $batch,
    ): Response {
        $validated = $request->validated();
        $cards = $batch->execute(
            (int) $validated['ceremony_id'],
            array_map('intval', $validated['student_ids']),
            $validated,
            $request->user(),
            $request->ip(),
        );
        $ceremony = Ceremony::query()->findOrFail($validated['ceremony_id']);

        return response()
            ->view('admin.student-qr-print', [
                'mode' => 'batch',
                'title' => 'Graduate QR batch',
                'ceremony' => $ceremony,
                'cards' => $cards,
                'generatedAt' => now(),
            ])
            ->header('Cache-Control', 'private, no-store, max-age=0')
            ->header('X-Robots-Tag', 'noindex, noarchive');
    }

    public function regenerate(
        RegenerateStudentQrCodeRequest $request,
        Student $student,
        RegenerateStudentQrToken $regenerate,
    ): RedirectResponse {
        $regenerate->execute(
            $student,
            $request->validated('expected_version'),
            $request->user(),
            $request->ip(),
        );

        return redirect()
            ->route('admin.students.qr-codes.show', $student)
            ->with(
                'warning',
                'QR token replaced. Previously printed cards no longer work; print and test the replacement before use.',
            );
    }

    /**
     * @return array<string, mixed>
     */
    private function cardIdentity(Student $student): array
    {
        return [
            'id' => $student->id,
            'display_name' => $student->display_name,
            'student_number' => $student->student_number,
            'program' => $student->program,
            'sequence_number' => $student->sequence_number,
            'ceremony' => [
                'id' => $student->ceremony->id,
                'name' => $student->ceremony->name,
                'academic_year' => $student->ceremony->academic_year,
            ],
        ];
    }

    private function assertPrintable(
        Student $student,
        StudentQrReadiness $readiness,
    ): void {
        $student->loadCount([
            'queueItems as completed_queue_items_count' => fn ($query) => $query
                ->where('status', StageQueueStatus::Completed->value),
        ]);
        $summary = $readiness->summarize($student);

        abort_unless(
            $summary['printable'],
            409,
            implode(' ', $summary['print_blockers']),
        );
    }
}
