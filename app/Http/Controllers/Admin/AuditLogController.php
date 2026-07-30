<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Support\OperationalLogSerializers;
use App\Support\SafeCsvExporter;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function __construct(
        private readonly OperationalLogSerializers $serializers,
        private readonly SafeCsvExporter $csv,
    ) {}

    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        [$query, $filters] = $this->query($request);

        $includeIp = $request->user()->isAdministrator();

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $query
                ->latest('created_at')
                ->latest('id')
                ->paginate(25)
                ->withQueryString()
                ->through(fn (AuditLog $log): array => $this->serializers->auditLog($log, $includeIp)),
            'filters' => $filters,
            'filterOptions' => [
                'actors' => User::query()->orderBy('name')->get(['id', 'name']),
                'entityTypes' => AuditLog::query()
                    ->select('entity_type')
                    ->distinct()
                    ->orderBy('entity_type')
                    ->pluck('entity_type'),
            ],
            'canExport' => $request->user()->can('export-operational-reports'),
        ]);
    }

    public function export(Request $request): StreamedResponse
    {
        Gate::authorize('viewAny', AuditLog::class);
        abort_unless($request->user()->can('export-operational-reports'), 403);

        [$query] = $this->query($request);

        $rows = $query
            ->latest('created_at')
            ->latest('id')
            ->limit(5000)
            ->get()
            ->map(fn (AuditLog $log): array => $this->serializers->auditLog($log, true));

        return $this->csv->response('gss-audit-logs.csv', [
            'created_at' => 'Timestamp',
            'actor' => 'Actor',
            'action' => 'Action',
            'entity_type' => 'Entity type',
            'entity_id' => 'Entity ID',
            'old_values' => 'Safe old-value summary',
            'new_values' => 'Safe new-value summary',
            'ip_address' => 'Request IP',
        ], $rows);
    }

    /**
     * @return array{0: Builder<AuditLog>, 1: array<string, string>}
     */
    private function query(Request $request): array
    {
        $filters = [
            'action' => trim($request->string('action')->toString()),
            'entity_type' => $request->string('entity_type')->toString(),
            'entity_id' => $request->string('entity_id')->toString(),
            'actor_id' => $request->string('actor_id')->toString(),
            'from' => $request->string('from')->toString(),
            'to' => $request->string('to')->toString(),
        ];

        $query = AuditLog::query()
            ->with('user:id,name')
            ->when(
                $filters['action'] !== '',
                fn ($query) => $query->where('action', 'ilike', '%'.$filters['action'].'%'),
            )
            ->when(
                $filters['entity_type'] !== '',
                fn ($query) => $query->where('entity_type', $filters['entity_type']),
            )
            ->when(
                $filters['entity_id'] !== '',
                fn ($query) => $query->where('entity_id', $filters['entity_id']),
            )
            ->when(
                $filters['actor_id'] !== '',
                fn ($query) => $query->where('user_id', $filters['actor_id']),
            )
            ->when(
                $filters['from'] !== '',
                fn ($query) => $query->where('created_at', '>=', $filters['from']),
            )
            ->when(
                $filters['to'] !== '',
                fn ($query) => $query->where('created_at', '<=', $filters['to']),
            );

        return [$query, $filters];
    }
}
