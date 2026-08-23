<?php

namespace App\Http\Controllers;

use App\Models\Ceremony;
use App\Models\Student;
use App\Support\ActiveCeremonyResolver;
use App\Support\CeremonyWorkspaceSummary;
use App\Support\WorkingCeremonyResolver;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function __invoke(
        Request $request,
        ActiveCeremonyResolver $liveCeremonies,
        CeremonyWorkspaceSummary $workspaceSummary,
        WorkingCeremonyResolver $workingCeremonies,
    ): Response {
        $user = $request->user();
        $canPrepare = $user->can('viewAny', Ceremony::class)
            || $user->can('viewAny', Student::class);
        $live = $liveCeremonies->resolve();
        $overview = [
            'live_ceremony' => [
                'state' => match ($live->status) {
                    'ready' => 'ready',
                    'no_active_ceremony' => 'none',
                    'multiple_active_ceremonies' => 'conflict',
                    default => 'unavailable',
                },
                'ceremony' => $live->ceremony === null ? null : $this->ceremony($live->ceremony),
            ],
        ];

        if ($canPrepare) {
            $workingCeremony = $workingCeremonies->resolve($request);
            $counts = $workingCeremony === null
                ? null
                : $workspaceSummary->forCeremony($workingCeremony)['counts'];
            $checklist = $workingCeremony === null || $counts === null
                ? []
                : $workspaceSummary->checklist($workingCeremony, $counts);
            $attention = $workingCeremony === null || $counts === null
                ? []
                : $this->attention($workingCeremony, $workspaceSummary->blockers($counts));

            $overview += [
                'working_ceremony' => $workingCeremony === null
                    ? null
                    : $this->ceremony($workingCeremony),
                'attention' => $attention,
                'preparation_checklist' => $checklist,
                'next_action' => $attention[0] ?? null,
                'primary_action' => $attention[0] ?? ($workingCeremony === null
                    ? [
                        'href' => '/admin/ceremonies',
                        'label' => 'Choose ceremony',
                        'title' => 'No working ceremony selected',
                        'detail' => 'Choose a ceremony before beginning preparation.',
                    ]
                    : [
                        'href' => route('admin.ceremonies.show', $workingCeremony),
                        'label' => 'Continue preparation',
                        'title' => 'Continue working ceremony preparation',
                        'detail' => 'Open the current ceremony workspace.',
                    ]),
                'relevant_actions' => $this->preparationActions($user),
            ];
        } else {
            $overview += [
                'primary_action' => $this->operatorPrimaryAction($user),
                'relevant_actions' => $this->operatorActions($user),
            ];
        }

        return Inertia::render('Dashboard', ['overview' => $overview]);
    }

    /** @return array<string, string|int|null> */
    private function ceremony(Ceremony $ceremony): array
    {
        return [
            'id' => $ceremony->id,
            'name' => $ceremony->name,
            'academic_year' => $ceremony->academic_year,
            'event_date' => $ceremony->event_date?->format('Y-m-d'),
            'status' => $ceremony->status->label(),
        ];
    }

    /**
     * @param  list<array{key: string, count: int, label: string, detail: string}>  $blockers
     * @return list<array{count: int, label: string, detail: string, href: string}>
     */
    private function attention(Ceremony $ceremony, array $blockers): array
    {
        return array_map(
            fn (array $blocker): array => [
                'count' => $blocker['count'],
                'label' => $blocker['label'],
                'detail' => $blocker['detail'],
                'href' => match ($blocker['key']) {
                    'graduates_empty' => route('admin.ceremonies.students.import.index', $ceremony),
                    'eligibility_issues' => route('admin.ceremonies.students.index', $ceremony).'?eligibility_status=pending',
                    'photos_missing' => route('admin.ceremonies.students.index', $ceremony).'?media=missing_photo',
                    'announcements_needs_review', 'announcements_missing' => route('admin.ceremonies.announcements', $ceremony),
                },
            ],
            $blockers,
        );
    }

    /** @return list<array{href: string, label: string, external?: bool}> */
    private function preparationActions($user): array
    {
        return array_values(array_filter([
            $user->can('operate-scanner') ? ['href' => '/scanner', 'label' => 'Open Scanner'] : null,
            $user->can('operate-stage') ? ['href' => '/stage-control', 'label' => 'Open Stage Control'] : null,
            $user->can('operate-stage') ? ['href' => '/stage', 'label' => 'Open Stage Display', 'external' => true] : null,
        ]));
    }

    /** @return array{href: string, label: string, title: string, detail: string}|null */
    private function operatorPrimaryAction($user): ?array
    {
        if ($user->can('operate-scanner')) {
            return [
                'href' => '/scanner',
                'label' => 'Open Scanner',
                'title' => 'Arrival scanning',
                'detail' => 'Process graduate QR codes and controlled manual lookups.',
            ];
        }

        if ($user->can('operate-stage')) {
            return [
                'href' => '/stage-control',
                'label' => 'Open Stage Control',
                'title' => 'Stage control',
                'detail' => 'Advance the live queue and recover presentation state.',
            ];
        }

        if ($user->can('manage-device-connections')) {
            return [
                'href' => '/admin/device-connections',
                'label' => 'Connect devices',
                'title' => 'Technical operations',
                'detail' => 'Check local device enrollment and workstation connections.',
            ];
        }

        return null;
    }

    /** @return list<array{href: string, label: string, external?: bool}> */
    private function operatorActions($user): array
    {
        return array_values(array_filter([
            $user->can('operate-stage') ? ['href' => '/stage', 'label' => 'Open Stage Display', 'external' => true] : null,
            $user->can('view-operational-logs') ? ['href' => '/admin/scan-logs', 'label' => 'Review scan logs'] : null,
            $user->can('view-backups') ? ['href' => '/admin/backups', 'label' => 'Open backups'] : null,
        ]));
    }
}
