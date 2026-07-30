<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ScanResult;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\Ceremony;
use App\Models\Device;
use App\Models\ScanLog;
use App\Models\User;
use App\Support\ActiveCeremonyResolver;
use App\Support\OperationalLogSerializers;
use App\Support\SafeCsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ScanLogController extends Controller
{
    public function __construct(
        private readonly OperationalLogSerializers $serializers,
        private readonly SafeCsvExporter $csv,
        private readonly ActiveCeremonyResolver $ceremonies,
    ) {}

    public function index(Request $request): Response
    {
        abort_unless($request->user()->can('view-operational-logs'), 403);

        [$query, $filters] = $this->query($request);

        return Inertia::render('Admin/Logs/ScanLogs', [
            'logs' => $query
                ->latest('scanned_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (ScanLog $log): array => $this->serializers->scanLog($log)),
            'filters' => $filters,
            'filterOptions' => $this->filterOptions(),
            'canExport' => $request->user()->can('export-operational-reports'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        abort_unless($request->user()->can('export-operational-reports'), 403);

        [$query] = $this->query($request);

        $rows = $query
            ->latest('scanned_at')
            ->latest('id')
            ->limit(5000)
            ->get()
            ->map(fn (ScanLog $log): array => $this->serializers->scanLog($log));

        return $this->csv->response('gss-scan-logs.csv', [
            'time' => 'Timestamp',
            'ceremony' => 'Ceremony',
            'result' => 'Result',
            'graduate' => 'Graduate',
            'operator' => 'Scanner operator',
            'device' => 'Device',
            'message' => 'Safe message',
            'queue_outcome' => 'Queue outcome',
        ], $rows->map(fn (array $row): array => [
            ...$row,
            'result' => $row['result']['label'],
        ]));
    }

    /**
     * @return array{0: Builder<ScanLog>, 1: array<string, string>}
     */
    private function query(Request $request): array
    {
        $filters = [
            'ceremony_id' => $request->string('ceremony_id')->toString(),
            'result' => $request->string('result')->toString(),
            'graduate' => trim($request->string('graduate')->toString()),
            'operator_id' => $request->string('operator_id')->toString(),
            'device_id' => $request->string('device_id')->toString(),
            'outcome' => $request->string('outcome')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];
        $user = $request->user();

        $query = ScanLog::query()
            ->with([
                'ceremony:id,name',
                'student:id,display_name',
                'operator:id,name',
                'device:id,name',
            ]);

        if ($user->hasRole(UserRole::StageOperator)) {
            $resolution = $this->ceremonies->resolve();
            $query->where('ceremony_id', $resolution->ceremony?->id ?? 0);
            $filters['ceremony_id'] = (string) ($resolution->ceremony?->id ?? '');
        }

        $query
            ->when($filters['ceremony_id'] !== '', fn (Builder $query) => $query->where('ceremony_id', $filters['ceremony_id']))
            ->when($filters['result'] !== '', fn (Builder $query) => $query->where('result', $filters['result']))
            ->when($filters['operator_id'] !== '', fn (Builder $query) => $query->where('scanned_by', $filters['operator_id']))
            ->when($filters['device_id'] !== '', fn (Builder $query) => $query->where('device_id', $filters['device_id']))
            ->when($filters['from'] !== '', fn (Builder $query) => $query->where('scanned_at', '>=', $filters['from']))
            ->when($filters['to'] !== '', fn (Builder $query) => $query->where('scanned_at', '<=', $filters['to']))
            ->when($filters['outcome'] === 'valid', fn (Builder $query) => $query->where('result', ScanResult::Accepted))
            ->when($filters['outcome'] === 'rejected', fn (Builder $query) => $query->where('result', '!=', ScanResult::Accepted))
            ->when($filters['graduate'] !== '', function (Builder $query) use ($filters): void {
                $search = '%'.$filters['graduate'].'%';
                $query->whereHas('student', fn (Builder $query) => $query->where('display_name', 'ilike', $search));
            });

        return [$query, $filters];
    }

    /**
     * @return array<string, mixed>
     */
    private function filterOptions(): array
    {
        return [
            'ceremonies' => Ceremony::query()->orderByDesc('event_date')->orderByDesc('id')->get(['id', 'name']),
            'operators' => User::query()->whereNotNull('role')->orderBy('name')->get(['id', 'name']),
            'devices' => Device::query()->orderBy('name')->get(['id', 'name']),
            'results' => collect(ScanResult::cases())->map(fn (ScanResult $result): array => [
                'value' => $result->value,
                'label' => str($result->value)->replace('_', ' ')->title()->toString(),
            ])->values(),
        ];
    }
}
