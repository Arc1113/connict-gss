<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BackupRecord;
use App\Support\BackupManager;
use App\Support\RestoreVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

class BackupController extends Controller
{
    public function index(Request $request, BackupManager $backups): Response
    {
        abort_unless($request->user()->can('view-backups'), 403);

        return Inertia::render('Admin/Backups/Index', [
            'records' => BackupRecord::query()
                ->with('creator:id,name')
                ->latest('started_at')
                ->latest('id')
                ->paginate(20)
                ->withQueryString()
                ->through(fn (BackupRecord $record): array => [
                    'id' => $record->id,
                    'identifier' => $record->identifier,
                    'path_label' => $record->path_label,
                    'status' => $record->status,
                    'verification_status' => $record->verification_status,
                    'started_at' => $record->started_at?->toIso8601String(),
                    'completed_at' => $record->completed_at?->toIso8601String(),
                    'created_by' => $record->creator?->name ?? 'CLI or deleted user',
                    'file_size' => $record->file_size,
                    'checksum' => $record->checksum,
                    'failure_summary' => $record->failure_summary,
                ]),
            'destinationLabel' => basename($backups->destination()),
            'canCreate' => $request->user()->can('create-backups'),
        ]);
    }

    public function store(Request $request, BackupManager $backups): RedirectResponse
    {
        abort_unless($request->user()->can('create-backups'), 403);

        $record = $backups->create($request->user());

        return redirect()
            ->route('admin.backups.index')
            ->with(
                $record->status === 'completed' ? 'success' : 'warning',
                $record->status === 'completed'
                    ? 'Local backup completed and verified.'
                    : 'Backup failed safely. Review the failure summary and recovery runbook.',
            );
    }

    public function verify(Request $request, BackupRecord $backup, BackupManager $backups, RestoreVerifier $verifier): RedirectResponse
    {
        abort_unless($request->user()->can('verify-restores'), 403);

        try {
            $result = $verifier->verify($backups->fullPath($backup));
            $backup->fill(['verification_status' => 'passed'])->save();

            return redirect()
                ->route('admin.backups.index')
                ->with(
                    'success',
                    "Disposable restore verified: {$result['ceremony_count']} ceremonies and {$result['student_count']} graduates restored.",
                );
        } catch (RuntimeException $exception) {
            $backup->fill([
                'verification_status' => 'failed',
                'failure_summary' => str($exception->getMessage())->limit(500)->toString(),
            ])->save();

            return redirect()
                ->route('admin.backups.index')
                ->with('warning', 'Restore verification failed safely. No primary database changes were made.');
        }
    }
}
