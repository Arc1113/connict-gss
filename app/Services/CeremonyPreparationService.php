<?php

namespace App\Services;

use App\Enums\CeremonyStatus;
use App\Models\Ceremony;
use App\Support\CeremonyWorkspaceSummary;

final class CeremonyPreparationService
{
    public function __construct(
        private readonly CeremonyWorkspaceSummary $summary,
    ) {}

    /**
     * Build the read-only preparation contract for one ceremony.
     *
     * Readiness is deliberately derived from current ceremony data. This
     * service owns presentation of that data, not a second completion state.
     *
     * @return array{ceremony: array<string, mixed>, display_state: string, summary: array<string, int>, steps: list<array<string, mixed>>, ready: bool}
     */
    public function forCeremony(Ceremony $ceremony): array
    {
        $counts = $this->summary->forCeremony($ceremony)['counts'];
        $steps = [
            $this->step(
                'graduates',
                'Graduates',
                $counts['graduates_total'] > 0 && $counts['eligibility_issues'] === 0,
                $counts['graduates_total'] === 0 ? 0 : $counts['eligibility_issues'],
                $counts['graduates_total'] === 0 ? 'Add or import graduate records.' : ($counts['eligibility_issues'] > 0 ? 'Review eligibility issues.' : 'Graduate records are ready.'),
                $counts['graduates_total'] === 0 ? route('admin.ceremonies.students.import.index', $ceremony) : route('admin.ceremonies.students.index', $ceremony),
            ),
            $this->step(
                'photos',
                'Photos',
                $counts['graduates_total'] > 0 && $counts['photos_missing'] === 0,
                $counts['photos_missing'],
                $counts['photos_missing'] === 0 ? 'Every graduate has a photo.' : 'Review graduates with missing photos.',
                route('admin.ceremonies.students.index', $ceremony).'?media=missing_photo',
            ),
            $this->step(
                'announcements',
                'Announcements',
                $counts['graduates_total'] > 0
                    && $counts['announcements_missing'] === 0
                    && $counts['announcements_needs_review'] === 0
                    && $counts['announcements_rejected'] === 0,
                $counts['announcements_missing'] + $counts['announcements_needs_review'] + $counts['announcements_rejected'],
                $counts['graduates_total'] === 0 ? 'Add graduates before preparing announcements.' : 'Prepare and review each announcement.',
                route('admin.ceremonies.announcements', $ceremony),
            ),
            $this->step(
                'qr',
                'QR cards',
                $counts['graduates_total'] > 0 && $counts['qr_missing'] === 0,
                $counts['qr_missing'],
                $counts['graduates_total'] === 0 ? 'QR preparation starts after graduates are added.' : ($counts['qr_missing'] === 0 ? 'QR cards are ready.' : 'Prepare the remaining QR cards.'),
                route('admin.ceremonies.students.qr.index', $ceremony),
            ),
            $this->step(
                'stage_design',
                'Stage design',
                true,
                0,
                'Review the audience display look and layout.',
                route('admin.ceremonies.section', [$ceremony, 'stage-design']),
            ),
        ];

        $ready = collect($steps)->every(static fn (array $step): bool => $step['status'] === 'ready');

        return [
            'ceremony' => [
                'id' => $ceremony->id,
                'name' => $ceremony->name,
                'academic_year' => $ceremony->academic_year,
                'event_date' => $ceremony->event_date?->format('Y-m-d'),
            ],
            'display_state' => $this->displayState($ceremony, $ready),
            'summary' => $counts,
            'steps' => [
                ...$steps,
                [
                    'key' => 'readiness',
                    'label' => 'Ceremony readiness',
                    'status' => $ready ? 'ready' : 'needs_attention',
                    'issue_count' => $ready ? 0 : count(array_filter($steps, static fn (array $step): bool => $step['status'] !== 'ready')),
                    'detail' => $ready ? 'Preparation is ready for a final check.' : 'Resolve the remaining preparation areas.',
                    'href' => route('admin.ceremonies.section', [$ceremony, 'readiness']),
                ],
            ],
            'ready' => $ready,
        ];
    }

    private function displayState(Ceremony $ceremony, bool $ready): string
    {
        return match ($ceremony->status) {
            CeremonyStatus::Active, CeremonyStatus::Paused => 'Live',
            CeremonyStatus::Completed, CeremonyStatus::Archived => 'Completed',
            CeremonyStatus::Prepared => 'Ready',
            CeremonyStatus::Draft => $ready ? 'Ready' : 'Preparing',
        };
    }

    /** @return array<string, mixed> */
    private function step(string $key, string $label, bool $ready, int $issueCount, string $detail, string $href): array
    {
        return [
            'key' => $key,
            'label' => $label,
            'status' => $ready ? 'ready' : ($issueCount === 0 ? 'not_started' : 'needs_attention'),
            'issue_count' => $issueCount,
            'detail' => $detail,
            'href' => $href,
        ];
    }
}
