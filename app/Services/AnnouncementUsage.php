<?php

namespace App\Services;

use App\Data\AnnouncementUsageEstimate;
use App\Enums\AnnouncementGenerationStatus;
use App\Enums\AnnouncementProvider;
use App\Models\AnnouncementGeneration;
use Carbon\CarbonImmutable;

final class AnnouncementUsage
{
    public function forMonth(?CarbonImmutable $month = null): AnnouncementUsageEstimate
    {
        $month ??= CarbonImmutable::now();
        $successfulCharacters = (int) AnnouncementGeneration::query()
            ->where('provider', AnnouncementProvider::Azure->value)
            ->whereIn('status', [
                AnnouncementGenerationStatus::NeedsReview->value,
                AnnouncementGenerationStatus::Approved->value,
                AnnouncementGenerationStatus::Rejected->value,
                AnnouncementGenerationStatus::Outdated->value,
            ])
            ->whereBetween('generated_at', [
                $month->startOfMonth(),
                $month->endOfMonth(),
            ])
            ->sum('character_count');
        $budget = max(0, (int) config('announcements.azure.monthly_character_budget', 500000));
        $warningPercent = min(100, max(0, (int) config(
            'announcements.azure.budget_warning_percent',
            80,
        )));
        $warningThreshold = intdiv(($budget * $warningPercent) + 99, 100);

        return new AnnouncementUsageEstimate(
            successfulCharacters: $successfulCharacters,
            monthlyBudget: $budget,
            warningThreshold: $warningThreshold,
            estimatedRemaining: max(0, $budget - $successfulCharacters),
            warning: $budget > 0 && $successfulCharacters >= $warningThreshold,
        );
    }
}
