<?php

namespace App\Http\Middleware;

use App\Enums\CeremonyStatus;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\User;
use App\Services\OnboardingWorkspace;
use App\Support\ActiveCeremonyResolver;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Http\Request;
use Inertia\Middleware;
use Throwable;

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
        $liveCeremonyContext = $this->liveCeremonyContext($request);

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
                    'manage_users' => $user->can('manage-users'),
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
                    'manage_device_connections' => $user->can('manage-device-connections'),
                    'reset_ceremony_operations' => $user->can('reset-ceremony-operations'),
                    'manage_announcement_settings' => $user->can('manage-announcement-settings'),
                    'manage_stage_designs' => $user->can('manage-stage-designs'),
                    'manage_announcement_batches' => $user->can('manage-announcement-batches'),
                    'generate_announcement_candidates' => $user->can('generate-announcement-candidates'),
                    'review_announcement_candidates' => $user->can('review-announcement-candidates'),
                    'view_pronunciation_rules' => $user->can('view-pronunciation-rules'),
                    'manage_common_pronunciation' => $user->can('manage-common-pronunciation'),
                    'manage_ceremony_pronunciation' => $user->can('manage-ceremony-pronunciation'),
                ],
            ],
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'warning' => fn () => $request->session()->get('warning'),
            ],
            'application' => [
                'version' => config('connict.version'),
                'commit' => config('connict.commit'),
            ],
            'liveCeremonyContext' => $liveCeremonyContext,
            // A ceremony workspace request can deliberately change the working
            // ceremony in its controller. Resolve this lazily so the shell and
            // the page receive that final context in the same response.
            'workingCeremonyContext' => fn (): array => $this->workingCeremonyContext($request),
            'ceremonyContext' => [
                'state' => $liveCeremonyContext['state'],
                'active' => $liveCeremonyContext['ceremony'],
            ],
            'onboarding' => fn (): ?array => $this->onboarding($request, $user),
        ];
    }

    /**
     * @return array{state: string, ceremony: array<string, mixed>|null}
     */
    private function liveCeremonyContext(Request $request): array
    {
        if ($request->user() === null) {
            return [
                'state' => 'none',
                'ceremony' => null,
            ];
        }

        try {
            $resolution = app(ActiveCeremonyResolver::class)->resolve();
            $activeCeremony = $resolution->ceremony;

            return [
                'state' => match ($resolution->status) {
                    'ready' => 'ready',
                    'no_active_ceremony' => 'none',
                    'multiple_active_ceremonies' => 'conflict',
                    default => 'unavailable',
                },
                'ceremony' => $activeCeremony === null ? null : [
                    'id' => $activeCeremony->id,
                    'name' => $activeCeremony->name,
                    'academic_year' => $activeCeremony->academic_year,
                    'event_date' => $activeCeremony->event_date?->format('Y-m-d'),
                    'status' => [
                        'value' => CeremonyStatus::Active->value,
                        'label' => CeremonyStatus::Active->label(),
                    ],
                ],
            ];
        } catch (Throwable) {
            return [
                'state' => 'unavailable',
                'ceremony' => null,
            ];
        }
    }

    /** @return array<string, mixed>|null */
    private function onboarding(Request $request, ?User $user): ?array
    {
        if ($user === null || $request->routeIs('stage')) {
            return null;
        }

        $active = Ceremony::query()->where('status', CeremonyStatus::Active)->exists();

        return app(OnboardingWorkspace::class)->forUser($user, $active);
    }

    /**
     * @return array{
     *     can_switch: bool,
     *     ceremony: array<string, mixed>|null,
     *     available: list<array<string, mixed>>
     * }
     */
    private function workingCeremonyContext(Request $request): array
    {
        $resolver = app(WorkingCeremonyResolver::class);
        $ceremony = $resolver->resolve($request);
        $available = $resolver->availableFor($request->user());

        return [
            'can_switch' => $resolver->canSwitch($request->user()),
            'ceremony' => $ceremony === null ? null : [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'event_date' => $ceremony->event_date?->format('Y-m-d'),
                'status' => [
                    'value' => $ceremony->status->value,
                    'label' => $ceremony->status->label(),
                ],
            ],
            'available' => $available->map(static fn (Ceremony $availableCeremony): array => [
                'id' => $availableCeremony->id,
                'name' => $availableCeremony->name,
                'academic_year' => $availableCeremony->academic_year,
                'event_date' => $availableCeremony->event_date?->format('Y-m-d'),
                'status' => [
                    'value' => $availableCeremony->status->value,
                    'label' => $availableCeremony->status->label(),
                ],
            ])->values()->all(),
        ];
    }
}
