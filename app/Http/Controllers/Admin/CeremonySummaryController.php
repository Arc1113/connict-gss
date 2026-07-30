<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Ceremony;
use App\Support\CeremonyCompletionSummary;
use App\Support\SafeCsvExporter;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CeremonySummaryController extends Controller
{
    public function __construct(
        private readonly CeremonyCompletionSummary $summaries,
        private readonly SafeCsvExporter $csv,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view-ceremony-reports'), 403);

        $ceremony = $this->selectedCeremony($request);

        return Inertia::render('Admin/Reports/CeremonySummary', [
            'summary' => $ceremony === null ? null : $this->summaries->forCeremony($ceremony),
            'ceremonies' => Ceremony::query()
                ->orderByDesc('event_date')
                ->orderByDesc('id')
                ->get(['id', 'name', 'academic_year', 'status']),
            'filters' => [
                'ceremony_id' => $ceremony?->id === null ? '' : (string) $ceremony->id,
            ],
            'canExport' => $request->user()->can('export-operational-reports') && $ceremony !== null,
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('export-operational-reports'), 403);

        $ceremony = $this->selectedCeremony($request);
        abort_if($ceremony === null, 422, 'Select a ceremony before exporting.');

        $summary = $this->summaries->forCeremony($ceremony);
        $rows = collect($summary['counts'])
            ->map(fn (int $value, string $metric): array => [
                'ceremony' => $summary['ceremony']['name'],
                'metric' => str($metric)->replace('_', ' ')->title()->toString(),
                'value' => $value,
                'definition' => $summary['definitions'][$metric] ?? '',
            ]);

        return $this->csv->response('gss-ceremony-summary.csv', [
            'ceremony' => 'Ceremony',
            'metric' => 'Metric',
            'value' => 'Value',
            'definition' => 'Definition',
        ], $rows);
    }

    private function selectedCeremony(Request $request): ?Ceremony
    {
        $id = $request->integer('ceremony_id');

        if ($id > 0) {
            return Ceremony::query()->findOrFail($id);
        }

        return Ceremony::query()
            ->orderByRaw('event_date DESC NULLS LAST')
            ->orderByDesc('id')
            ->first();
    }
}
