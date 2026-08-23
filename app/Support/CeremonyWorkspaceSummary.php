<?php

namespace App\Support;

use App\Enums\AudioReadinessStatus;
use App\Enums\EligibilityStatus;
use App\Models\Ceremony;
use App\Models\Student;

final class CeremonyWorkspaceSummary
{
    /**
     * @return array<string, mixed>
     */
    public function forCeremony(Ceremony $ceremony): array
    {
        $students = Student::query()->whereBelongsTo($ceremony);

        $totalGraduates = (clone $students)->count();
        $eligibleGraduates = (clone $students)
            ->where('eligibility_status', EligibilityStatus::Eligible)
            ->count();
        $eligibilityIssues = (clone $students)
            ->where('eligibility_status', '!=', EligibilityStatus::Eligible)
            ->count();
        $missingPhotos = (clone $students)->whereNull('photo_path')->count();
        $qrReady = (clone $students)
            ->whereNotNull('qr_token')
            ->where('qr_token', '!=', '')
            ->count();
        $announcementsReady = (clone $students)
            ->where('audio_readiness_status', AudioReadinessStatus::Approved)
            ->count();
        $announcementsMissing = (clone $students)
            ->where('audio_readiness_status', AudioReadinessStatus::Missing)
            ->count();
        $announcementsNeedReview = (clone $students)
            ->where('audio_readiness_status', AudioReadinessStatus::NeedsReview)
            ->count();
        $announcementsRejected = (clone $students)
            ->where('audio_readiness_status', AudioReadinessStatus::Rejected)
            ->count();

        return [
            'counts' => [
                'graduates_total' => $totalGraduates,
                'eligibility_ready' => $eligibleGraduates,
                'eligibility_issues' => $eligibilityIssues,
                'photos_ready' => max($totalGraduates - $missingPhotos, 0),
                'photos_missing' => $missingPhotos,
                'qr_ready' => $qrReady,
                'qr_missing' => max($totalGraduates - $qrReady, 0),
                'announcements_ready' => $announcementsReady,
                'announcements_missing' => $announcementsMissing,
                'announcements_needs_review' => $announcementsNeedReview,
                'announcements_rejected' => $announcementsRejected,
            ],
        ];
    }

    /**
     * @param  array<string, int>  $counts
     * @return list<array{key: string, count: int, label: string, detail: string}>
     */
    public function blockers(array $counts): array
    {
        return array_values(array_filter([
            $counts['graduates_total'] === 0 ? [
                'key' => 'graduates_empty',
                'count' => 0,
                'label' => 'No graduate records have been prepared yet',
                'detail' => 'Import or add graduate records before continuing other preparation tasks.',
            ] : null,
            $counts['eligibility_issues'] > 0 ? [
                'key' => 'eligibility_issues',
                'count' => $counts['eligibility_issues'],
                'label' => "{$counts['eligibility_issues']} graduate records need eligibility review",
                'detail' => 'Resolve eligibility issues before arrival scanning begins.',
            ] : null,
            $counts['photos_missing'] > 0 ? [
                'key' => 'photos_missing',
                'count' => $counts['photos_missing'],
                'label' => "{$counts['photos_missing']} graduates are missing photos",
                'detail' => 'Review media readiness before printing QR cards or opening stage presentation.',
            ] : null,
            $counts['announcements_needs_review'] > 0 ? [
                'key' => 'announcements_needs_review',
                'count' => $counts['announcements_needs_review'],
                'label' => "{$counts['announcements_needs_review']} announcements need review",
                'detail' => 'Approve or reject announcement audio before live stage operation.',
            ] : null,
            $counts['announcements_missing'] > 0 ? [
                'key' => 'announcements_missing',
                'count' => $counts['announcements_missing'],
                'label' => "{$counts['announcements_missing']} graduates have no announcement audio",
                'detail' => 'Continue to the existing announcement workflow to prepare or review audio.',
            ] : null,
        ]));
    }

    /**
     * Return the small, user-facing preparation checklist used by Home and the
     * ceremony workspace. Counts and blocker priority remain server-owned.
     *
     * @param  array<string, int>  $counts
     * @return list<array{key: string, status: string, count: int, issue_count: int, label: string, detail: string, href: string}>
     */
    public function checklist(Ceremony $ceremony, array $counts): array
    {
        $blockerCount = count($this->blockers($counts));

        return [
            [
                'key' => 'graduate_data',
                'status' => $counts['graduates_total'] > 0 && $counts['eligibility_issues'] === 0 ? 'ready' : 'warning',
                'count' => $counts['graduates_total'],
                'issue_count' => $counts['graduates_total'] === 0 ? 0 : $counts['eligibility_issues'],
                'label' => 'Graduate information',
                'detail' => $counts['graduates_total'] === 0
                    ? 'Add or import graduate records.'
                    : ($counts['eligibility_issues'] > 0 ? 'Review eligibility before scanning.' : 'Graduate records are ready.'),
                'href' => $counts['graduates_total'] === 0
                    ? route('admin.ceremonies.students.import.index', $ceremony)
                    : route('admin.ceremonies.students.index', $ceremony),
            ],
            [
                'key' => 'photos',
                'status' => $counts['photos_missing'] === 0 ? 'ready' : 'warning',
                'count' => $counts['photos_ready'],
                'issue_count' => $counts['photos_missing'],
                'label' => 'Photos',
                'detail' => $counts['photos_missing'] === 0 ? 'Every graduate has a photo.' : 'Review graduates with missing photos.',
                'href' => route('admin.ceremonies.students.index', $ceremony).'?media=missing_photo',
            ],
            [
                'key' => 'announcements',
                'status' => $counts['announcements_needs_review'] === 0
                    && $counts['announcements_missing'] === 0
                    && $counts['announcements_rejected'] === 0 ? 'ready' : 'warning',
                'count' => $counts['announcements_ready'],
                'issue_count' => $counts['announcements_needs_review'] + $counts['announcements_missing'] + $counts['announcements_rejected'],
                'label' => 'Announcements',
                'detail' => $counts['announcements_ready'].' ready · '.($counts['announcements_needs_review'] + $counts['announcements_missing'] + $counts['announcements_rejected']).' need attention.',
                'href' => route('admin.ceremonies.announcements', $ceremony),
            ],
            [
                'key' => 'qr',
                'status' => $counts['qr_missing'] === 0 ? 'ready' : 'warning',
                'count' => $counts['qr_ready'],
                'issue_count' => $counts['qr_missing'],
                'label' => 'QR preparation',
                'detail' => $counts['qr_missing'] === 0 ? 'QR cards are ready.' : 'Prepare the remaining QR cards.',
                'href' => route('admin.ceremonies.students.qr.index', $ceremony),
            ],
            [
                'key' => 'stage_design',
                'status' => 'ready',
                'count' => 1,
                'issue_count' => 0,
                'label' => 'Stage design',
                'detail' => 'Review the audience display look and layout.',
                'href' => route('admin.ceremonies.section', [$ceremony, 'stage-design']),
            ],
            [
                'key' => 'readiness',
                'status' => $blockerCount === 0 ? 'ready' : 'warning',
                'count' => $blockerCount === 0 ? 1 : 0,
                'issue_count' => $blockerCount,
                'label' => 'Ceremony readiness',
                'detail' => $blockerCount === 0
                    ? 'No preparation blockers are currently flagged.'
                    : "{$blockerCount} preparation area(s) need attention.",
                'href' => route('admin.ceremonies.section', [$ceremony, 'readiness']),
            ],
        ];
    }
}
