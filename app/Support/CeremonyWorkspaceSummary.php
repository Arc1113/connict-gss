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
}
