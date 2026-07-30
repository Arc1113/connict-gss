<?php

namespace App\Http\Middleware;

use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentImport;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'role' => [
                        'value' => $user->role->value,
                        'label' => $user->role->label(),
                    ],
                    'is_active' => $user->is_active,
                ],
                'abilities' => $user === null ? [] : [
                    'operate_scanner' => $user->can('operate-scanner'),
                    'operate_stage' => $user->can('operate-stage'),
                    'view_ceremonies' => $user->can('viewAny', Ceremony::class),
                    'manage_ceremonies' => $user->can('create', Ceremony::class),
                    'view_students' => $user->can('viewAny', Student::class),
                    'manage_students' => $user->can('create', Student::class),
                    'prepare_qr_codes' => $user->can('viewQrCodes', Student::class),
                    'regenerate_qr_codes' => $user->isAdministrator(),
                    'approve_audio' => $user->isAdministrator(),
                    'import_students' => $user->can('create', StudentImport::class),
                    'delete_students' => $user->isAdministrator(),
                    'view_audit_logs' => $user->can('viewAny', AuditLog::class),
                    'view_operational_logs' => $user->can('view-operational-logs'),
                    'export_operational_reports' => $user->can('export-operational-reports'),
                    'view_ceremony_reports' => $user->can('view-ceremony-reports'),
                    'view_backups' => $user->can('view-backups'),
                    'create_backups' => $user->can('create-backups'),
                    'reset_ceremony_operations' => $user->can('reset-ceremony-operations'),
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
        ];
    }
}
