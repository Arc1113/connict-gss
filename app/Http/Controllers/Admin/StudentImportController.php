<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ConfirmStudentImport;
use App\Actions\Admin\InspectStudentImport;
use App\Enums\CeremonyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmStudentImportRequest;
use App\Http\Requests\InspectStudentImportRequest;
use App\Http\Requests\SaveStudentImportMappingRequest;
use App\Models\Ceremony;
use App\Models\StudentImport;
use App\Services\StudentImportPreparation;
use App\Support\StudentImportLimits;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('create', StudentImport::class);

        StudentImport::query()
            ->where('expires_at', '<=', now()->utc())
            ->delete();

        return Inertia::render('Admin/Students/Import/Index', [
            'ceremonies' => $this->ceremonyOptions(),
            'limits' => StudentImportLimits::forBrowser(),
            'templateUrl' => route('admin.students.import.template'),
        ]);
    }

    public function inspect(
        InspectStudentImportRequest $request,
        InspectStudentImport $inspect,
    ): RedirectResponse {
        $studentImport = $inspect->execute(
            $request->file('csv_file'),
            $request->integer('ceremony_id'),
            $request->user(),
        );

        return redirect()->route(
            'admin.students.import.mapping',
            $studentImport,
        );
    }

    public function mapping(
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): Response|RedirectResponse {
        Gate::authorize('view', $studentImport);

        if ($redirect = $this->unavailableRedirect($studentImport)) {
            return $redirect;
        }

        return Inertia::render('Admin/Students/Import/Mapping', [
            'studentImport' => $this->serializeImport($studentImport),
            'mappingRows' => $preparation->mappingRows($studentImport),
            'targetOptions' => $preparation->targetOptions(),
        ]);
    }

    public function storeMapping(
        SaveStudentImportMappingRequest $request,
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): RedirectResponse {
        if ($redirect = $this->unavailableRedirect($studentImport)) {
            return $redirect;
        }

        $mapping = $preparation->validateMapping(
            $request->validated('mapping'),
            $studentImport->headers,
        );

        $studentImport->update(['mapping' => $mapping]);

        return redirect()->route(
            'admin.students.import.preview',
            $studentImport,
        );
    }

    public function preview(
        Request $request,
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): Response|RedirectResponse {
        Gate::authorize('view', $studentImport);

        if ($redirect = $this->unavailableRedirect($studentImport)) {
            return $redirect;
        }

        if ($studentImport->mapping === null) {
            return redirect()
                ->route('admin.students.import.mapping', $studentImport)
                ->with('warning', 'Review the column mapping before previewing rows.');
        }

        $preview = $preparation->buildPreview($studentImport);
        $page = max(1, $request->integer('page', 1));
        $rows = new LengthAwarePaginator(
            items: array_slice(
                $preview['rows'],
                ($page - 1) * StudentImportLimits::PREVIEW_PER_PAGE,
                StudentImportLimits::PREVIEW_PER_PAGE,
            ),
            total: $preview['total_count'],
            perPage: StudentImportLimits::PREVIEW_PER_PAGE,
            currentPage: $page,
            options: [
                'path' => route(
                    'admin.students.import.preview',
                    $studentImport,
                ),
                'pageName' => 'page',
            ],
        );
        unset($preview['rows']);

        return Inertia::render('Admin/Students/Import/Preview', [
            'studentImport' => $this->serializeImport($studentImport),
            'preview' => [
                ...$preview,
                'rows' => $rows,
            ],
        ]);
    }

    public function confirm(
        ConfirmStudentImportRequest $request,
        StudentImport $studentImport,
        ConfirmStudentImport $confirm,
    ): RedirectResponse {
        $confirmedImport = $confirm->execute(
            $studentImport,
            $request->user(),
            $request->ip(),
        );

        return redirect()->route(
            'admin.students.import.summary',
            $confirmedImport,
        );
    }

    public function summary(
        StudentImport $studentImport,
    ): Response|RedirectResponse {
        Gate::authorize('view', $studentImport);

        if ($studentImport->isExpired()) {
            $studentImport->delete();

            return redirect()
                ->route('admin.students.import.index')
                ->with('warning', 'The import summary expired. Start a new import when needed.');
        }

        if (! $studentImport->isConsumed() || $studentImport->summary === null) {
            return redirect()
                ->route('admin.students.import.preview', $studentImport)
                ->with('warning', 'Confirm the reviewed import before opening its summary.');
        }

        return Inertia::render('Admin/Students/Import/Summary', [
            'studentImport' => $this->serializeImport($studentImport),
            'summary' => $studentImport->summary,
            'graduateListUrl' => route('admin.students.index', [
                'ceremony_id' => $studentImport->ceremony_id,
            ]),
        ]);
    }

    public function template(): StreamedResponse
    {
        Gate::authorize('create', StudentImport::class);

        $headers = [
            'student_number',
            'first_name',
            'middle_name',
            'last_name',
            'suffix',
            'display_name',
            'announcement_name',
            'pronunciation_guide',
            'college',
            'program',
            'major',
            'honors',
            'sequence_number',
            'eligibility_status',
        ];
        $examples = [
            [
                'SAMPLE-2027-001',
                'Amina Elise',
                '',
                'Navarro',
                '',
                'Amina Elise Navarro',
                'Amina Elise Navarro',
                '',
                'College of Computing',
                'Bachelor of Science in Computer Science',
                '',
                'Magna Cum Laude',
                '101',
                'eligible',
            ],
            [
                'SAMPLE-2027-002',
                'Theodore Miguel',
                'P.',
                'Santos-Reyes',
                '',
                'Theodore Miguel P. Santos-Reyes',
                'Theodore Miguel P. Santos-Reyes',
                '',
                'College of Engineering',
                'Bachelor of Science in Electronics Engineering',
                'Communications Engineering',
                '',
                '102',
                'pending',
            ],
        ];

        return response()->streamDownload(function () use ($headers, $examples): void {
            $stream = fopen('php://output', 'wb');

            if ($stream === false) {
                return;
            }

            fwrite($stream, "\xEF\xBB\xBF");
            fputcsv($stream, $headers, ',', '"', '');

            foreach ($examples as $row) {
                fputcsv($stream, $row, ',', '"', '');
            }

            fclose($stream);
        }, 'graduation-stage-system-graduate-import-template.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Cache-Control' => 'no-store, private',
        ]);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function ceremonyOptions(): array
    {
        return Ceremony::query()
            ->whereNotIn('status', [
                CeremonyStatus::Completed,
                CeremonyStatus::Archived,
            ])
            ->orderByDesc('event_date')
            ->orderBy('name')
            ->get(['id', 'name', 'academic_year', 'status'])
            ->map(fn (Ceremony $ceremony): array => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'status' => [
                    'value' => $ceremony->status->value,
                    'label' => $ceremony->status->label(),
                ],
            ])
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeImport(StudentImport $studentImport): array
    {
        $studentImport->loadMissing('ceremony:id,name,academic_year,status');

        return [
            'token' => $studentImport->token,
            'original_filename' => $studentImport->original_filename,
            'mode' => [
                'value' => 'create_only',
                'label' => 'Create new graduates only',
            ],
            'row_count' => $studentImport->row_count,
            'expires_at' => $studentImport->expires_at->toIso8601String(),
            'consumed_at' => $studentImport->consumed_at?->toIso8601String(),
            'ceremony' => $studentImport->ceremony === null ? null : [
                'id' => $studentImport->ceremony->id,
                'name' => $studentImport->ceremony->name,
                'academic_year' => $studentImport->ceremony->academic_year,
                'status' => [
                    'value' => $studentImport->ceremony->status->value,
                    'label' => $studentImport->ceremony->status->label(),
                ],
            ],
        ];
    }

    private function unavailableRedirect(
        StudentImport $studentImport,
    ): ?RedirectResponse {
        if ($studentImport->isExpired()) {
            $studentImport->delete();

            return redirect()
                ->route('admin.students.import.index')
                ->with('warning', 'The import preview expired. Upload the CSV again.');
        }

        return $studentImport->isConsumed()
            ? redirect()->route(
                'admin.students.import.summary',
                $studentImport,
            )
            : null;
    }
}
