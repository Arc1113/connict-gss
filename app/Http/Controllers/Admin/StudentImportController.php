<?php

namespace App\Http\Controllers\Admin;

use App\Actions\Admin\ConfirmStudentImport;
use App\Actions\Admin\InspectStudentImport;
use App\Actions\Admin\ReplaceStudentImportCsv;
use App\Enums\CeremonyStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmStudentImportRequest;
use App\Http\Requests\InspectStudentImportRequest;
use App\Http\Requests\ReplaceStudentImportCsvRequest;
use App\Http\Requests\ReplaceStudentImportMediaPackageRequest;
use App\Http\Requests\SaveStudentImportMappingRequest;
use App\Models\Ceremony;
use App\Models\StudentImport;
use App\Services\StudentImportPreparation;
use App\Support\StudentImportLimits;
use App\Support\StudentImportMediaPackage;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class StudentImportController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('create', StudentImport::class);

        $workspaceCeremony = $this->resolveWorkspaceCeremony($request);

        StudentImport::query()
            ->where('expires_at', '<=', now()->utc())
            ->get()
            ->each->delete();

        return Inertia::render('Admin/Students/Import/Index', [
            'ceremonies' => $this->ceremonyOptions($workspaceCeremony),
            'limits' => StudentImportLimits::forBrowser(),
            'templateUrl' => route('admin.students.import.template'),
            'workspace' => $this->workspaceContext(
                $request,
                $workspaceCeremony,
            ),
        ]);
    }

    public function inspect(
        InspectStudentImportRequest $request,
        InspectStudentImport $inspect,
    ): RedirectResponse {
        $workspaceCeremony = $this->resolveWorkspaceCeremony($request);
        $ceremonyId = $request->integer('ceremony_id')
            ?: $workspaceCeremony?->id;

        if ($ceremonyId === null) {
            throw ValidationException::withMessages([
                'ceremony_id' => 'Select an available destination ceremony.',
            ]);
        }

        $studentImport = $inspect->execute(
            $request->file('csv_file'),
            $request->file('media_package'),
            $ceremonyId,
            $request->user(),
        );

        return redirect()->to(
            $this->importRoute(
                $request,
                $studentImport->ceremony,
                'mapping',
                $studentImport,
            ),
        );
    }

    public function mapping(
        Request $request,
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): Response|RedirectResponse {
        Gate::authorize('view', $studentImport);

        if ($redirect = $this->unavailableRedirect($request, $studentImport)) {
            return $redirect;
        }

        $workspace = $this->workspaceContext($request, $studentImport->ceremony);

        return Inertia::render('Admin/Students/Import/Mapping', [
            'studentImport' => $this->serializeImport($studentImport),
            'mappingRows' => $preparation->mappingRows($studentImport),
            'targetOptions' => $preparation->targetOptions(),
            'workspace' => [
                ...($workspace ?? []),
                'urls' => [
                    ...(isset($workspace['urls']) ? $workspace['urls'] : []),
                    'previewStore' => $this->importRoute(
                        $request,
                        $studentImport->ceremony,
                        'preview.store',
                        $studentImport,
                    ),
                    'csvReplace' => $this->importRoute(
                        $request,
                        $studentImport->ceremony,
                        'csv.replace',
                        $studentImport,
                    ),
                    'mediaPackageReplace' => $this->importRoute(
                        $request,
                        $studentImport->ceremony,
                        'media-package.replace',
                        $studentImport,
                    ),
                ],
            ],
        ]);
    }

    public function mappingInCeremony(
        Request $request,
        $ceremony,
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): Response|RedirectResponse {
        return $this->mapping($request, $studentImport, $preparation);
    }

    public function storeMapping(
        SaveStudentImportMappingRequest $request,
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): RedirectResponse {
        if ($redirect = $this->unavailableRedirect($request, $studentImport)) {
            return $redirect;
        }

        $mapping = $preparation->validateMapping(
            $request->validated('mapping'),
            $studentImport->headers,
        );

        $studentImport->update(['mapping' => $mapping]);

        return redirect()->to(
            $this->importRoute(
                $request,
                $studentImport->ceremony,
                'preview',
                $studentImport,
            ),
        );
    }

    public function storeMappingInCeremony(
        SaveStudentImportMappingRequest $request,
        $ceremony,
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): RedirectResponse {
        return $this->storeMapping($request, $studentImport, $preparation);
    }

    public function replaceMediaPackage(
        ReplaceStudentImportMediaPackageRequest $request,
        StudentImport $studentImport,
        StudentImportMediaPackage $mediaPackage,
    ): RedirectResponse {
        if ($redirect = $this->unavailableRedirect($request, $studentImport)) {
            return $redirect;
        }

        $oldPath = $studentImport->media_package_path;
        $newPath = $mediaPackage->store(
            $request->file('media_package'),
            $studentImport->token.'-replacement-'.Str::uuid(),
        );

        try {
            $studentImport->update(['media_package_path' => $newPath]);
        } catch (\Throwable $exception) {
            $mediaPackage->delete($newPath);
            throw $exception;
        }

        $mediaPackage->delete($oldPath);

        return back()->with(
            'success',
            'The media ZIP was replaced. Review the rows again before importing.',
        );
    }

    public function replaceMediaPackageInCeremony(
        ReplaceStudentImportMediaPackageRequest $request,
        $ceremony,
        StudentImport $studentImport,
        StudentImportMediaPackage $mediaPackage,
    ): RedirectResponse {
        return $this->replaceMediaPackage($request, $studentImport, $mediaPackage);
    }

    public function replaceCsv(
        ReplaceStudentImportCsvRequest $request,
        StudentImport $studentImport,
        ReplaceStudentImportCsv $replaceCsv,
    ): RedirectResponse {
        if ($redirect = $this->unavailableRedirect($request, $studentImport)) {
            return $redirect;
        }

        $studentImport = $replaceCsv->execute(
            $studentImport,
            $request->file('csv_file'),
        );

        $message = $studentImport->mapping === null
            ? 'The CSV was replaced. Review the column mapping before continuing.'
            : 'The CSV was replaced. The media ZIP and column mapping were kept.';

        return redirect()
            ->to(
                $this->importRoute(
                    $request,
                    $studentImport->ceremony,
                    $studentImport->mapping === null ? 'mapping' : 'preview',
                    $studentImport,
                ),
            )
            ->with('success', $message);
    }

    public function replaceCsvInCeremony(
        ReplaceStudentImportCsvRequest $request,
        $ceremony,
        StudentImport $studentImport,
        ReplaceStudentImportCsv $replaceCsv,
    ): RedirectResponse {
        return $this->replaceCsv($request, $studentImport, $replaceCsv);
    }

    public function preview(
        Request $request,
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): Response|RedirectResponse {
        Gate::authorize('view', $studentImport);

        if ($redirect = $this->unavailableRedirect($request, $studentImport)) {
            return $redirect;
        }

        if ($studentImport->mapping === null) {
            return redirect()
                ->to($this->importRoute($request, $studentImport->ceremony, 'mapping', $studentImport))
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
                    $request->route('ceremony') instanceof Ceremony
                        ? 'admin.ceremonies.students.import.preview'
                        : 'admin.students.import.preview',
                    array_filter([
                        'ceremony' => $request->route('ceremony'),
                        'studentImport' => $studentImport,
                    ]),
                ),
                'pageName' => 'page',
            ],
        );
        unset($preview['rows']);

        $workspace = $this->workspaceContext($request, $studentImport->ceremony);

        return Inertia::render('Admin/Students/Import/Preview', [
            'studentImport' => $this->serializeImport($studentImport),
            'preview' => [
                ...$preview,
                'rows' => $rows,
            ],
            'workspace' => [
                ...($workspace ?? []),
                'urls' => [
                    ...(isset($workspace['urls']) ? $workspace['urls'] : []),
                    'mapping' => $this->importRoute(
                        $request,
                        $studentImport->ceremony,
                        'mapping',
                        $studentImport,
                    ),
                    'confirm' => $this->importRoute(
                        $request,
                        $studentImport->ceremony,
                        'confirm',
                        $studentImport,
                    ),
                    'csvReplace' => $this->importRoute(
                        $request,
                        $studentImport->ceremony,
                        'csv.replace',
                        $studentImport,
                    ),
                    'mediaPackageReplace' => $this->importRoute(
                        $request,
                        $studentImport->ceremony,
                        'media-package.replace',
                        $studentImport,
                    ),
                ],
            ],
        ]);
    }

    public function previewInCeremony(
        Request $request,
        $ceremony,
        StudentImport $studentImport,
        StudentImportPreparation $preparation,
    ): Response|RedirectResponse {
        return $this->preview($request, $studentImport, $preparation);
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

        return redirect()->to(
            $this->importRoute(
                $request,
                $confirmedImport->ceremony,
                'summary',
                $confirmedImport,
            ),
        );
    }

    public function confirmInCeremony(
        ConfirmStudentImportRequest $request,
        $ceremony,
        StudentImport $studentImport,
        ConfirmStudentImport $confirm,
    ): RedirectResponse {
        return $this->confirm($request, $studentImport, $confirm);
    }

    public function summary(
        Request $request,
        StudentImport $studentImport,
    ): Response|RedirectResponse {
        Gate::authorize('view', $studentImport);

        if ($studentImport->isExpired()) {
            $studentImport->delete();

            return redirect()
                ->to($this->importIndexUrl($request, $studentImport->ceremony))
                ->with('warning', 'The import summary expired. Start a new import when needed.');
        }

        if (! $studentImport->isConsumed() || $studentImport->summary === null) {
            return redirect()
                ->to($this->importRoute($request, $studentImport->ceremony, 'preview', $studentImport))
                ->with('warning', 'Confirm the reviewed import before opening its summary.');
        }

        return Inertia::render('Admin/Students/Import/Summary', [
            'studentImport' => $this->serializeImport($studentImport),
            'summary' => $studentImport->summary,
            'graduateListUrl' => route('admin.ceremonies.students.index', $studentImport->ceremony),
            'workspace' => $this->workspaceContext($request, $studentImport->ceremony),
        ]);
    }

    public function summaryInCeremony(
        Request $request,
        $ceremony,
        StudentImport $studentImport,
    ): Response|RedirectResponse {
        return $this->summary($request, $studentImport);
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
            'photo_filename',
            'audio_filename',
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
                'photos/SAMPLE-2027-001.jpg',
                'audio/SAMPLE-2027-001.webm',
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
                '',
                '',
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
    private function ceremonyOptions(?Ceremony $lockedCeremony = null): array
    {
        $ceremonies = Ceremony::query()
            ->whereNotIn('status', [
                CeremonyStatus::Completed,
                CeremonyStatus::Archived,
            ])
            ->orderByDesc('event_date')
            ->orderBy('name')
            ->get(['id', 'name', 'academic_year', 'status']);

        if ($lockedCeremony !== null) {
            $ceremonies = $ceremonies->where('id', $lockedCeremony->id)->values();
        }

        return $ceremonies
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
            'has_media_package' => $studentImport->media_package_path !== null,
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
        Request $request,
        StudentImport $studentImport,
    ): ?RedirectResponse {
        if ($studentImport->isExpired()) {
            $studentImport->delete();

            return redirect()
                ->to($this->importIndexUrl($request, $studentImport->ceremony))
                ->with('warning', 'The import preview expired. Upload the CSV again.');
        }

        return $studentImport->isConsumed()
            ? redirect()->to($this->importRoute(
                $request,
                $studentImport->ceremony,
                'summary',
                $studentImport,
            ))
            : null;
    }

    private function resolveWorkspaceCeremony(Request $request): ?Ceremony
    {
        $routeCeremonyId = $request->route('ceremony');

        if ($routeCeremonyId !== null) {
            $routeCeremony = Ceremony::query()->findOrFail($routeCeremonyId);
            Gate::authorize('view', $routeCeremony);
            $workingCeremonies = app(WorkingCeremonyResolver::class);

            if ($workingCeremonies->canSwitch($request->user())) {
                $workingCeremonies->store($request, $routeCeremony);
            }

            return $routeCeremony;
        }

        return app(WorkingCeremonyResolver::class)->resolve($request);
    }

    private function importIndexUrl(Request $request, ?Ceremony $ceremony): string
    {
        if ($request->route('ceremony') !== null && $ceremony !== null) {
            return route('admin.ceremonies.students.import.index', $ceremony);
        }

        return route('admin.students.import.index');
    }

    private function importRoute(
        Request $request,
        ?Ceremony $ceremony,
        string $route,
        StudentImport $studentImport,
    ): string {
        if ($request->route('ceremony') !== null && $ceremony !== null) {
            return route("admin.ceremonies.students.import.{$route}", [
                'ceremony' => $ceremony,
                'studentImport' => $studentImport,
            ]);
        }

        return route("admin.students.import.{$route}", $studentImport);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function workspaceContext(Request $request, ?Ceremony $ceremony): ?array
    {
        if ($ceremony === null) {
            return null;
        }

        return [
            'locked' => $this->resolveWorkspaceCeremony($request)?->is($ceremony) ?? false,
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'status' => $ceremony->status->label(),
            ],
            'urls' => [
                'graduates' => route('admin.ceremonies.students.index', $ceremony),
                'import' => route('admin.ceremonies.students.import.index', $ceremony),
                'inspect' => route('admin.ceremonies.students.import.inspect', $ceremony),
                'overview' => route('admin.ceremonies.show', $ceremony),
            ],
        ];
    }
}
