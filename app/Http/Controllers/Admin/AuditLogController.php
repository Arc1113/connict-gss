<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        Gate::authorize('viewAny', AuditLog::class);

        $filters = [
            'action' => trim($request->string('action')->toString()),
            'entity_type' => $request->string('entity_type')->toString(),
        ];

        $logs = AuditLog::query()
            ->with('user:id,name')
            ->when(
                $filters['action'] !== '',
                fn ($query) => $query->where('action', 'ilike', '%'.$filters['action'].'%'),
            )
            ->when(
                $filters['entity_type'] !== '',
                fn ($query) => $query->where('entity_type', $filters['entity_type']),
            )
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (AuditLog $log): array => [
                'id' => $log->id,
                'action' => $log->action,
                'entity_type' => $log->entity_type,
                'entity_id' => $log->entity_id,
                'actor' => $log->user?->name ?? 'Deleted user',
                'old_values' => $log->old_values,
                'new_values' => $log->new_values,
                'created_at' => $log->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/AuditLogs/Index', [
            'logs' => $logs,
            'filters' => $filters,
        ]);
    }
}
