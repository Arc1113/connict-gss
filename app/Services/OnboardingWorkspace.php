<?php

namespace App\Services;

use App\Enums\UserRole;
use App\Models\AnnouncementGeneration;
use App\Models\BackupRecord;
use App\Models\Ceremony;
use App\Models\Device;
use App\Models\PronunciationRule;
use App\Models\Student;
use App\Models\User;
use App\Models\UserOnboardingProgress;

final class OnboardingWorkspace
{
    /** @return array<string, mixed>|null */
    public function forUser(User $user, bool $ceremonyActive): ?array
    {
        $definition = config("onboarding.tours.{$user->role->value}");

        if (! is_array($definition)) {
            return null;
        }

        $progress = UserOnboardingProgress::query()->where([
            'user_id' => $user->id,
            'tour_key' => $definition['key'],
            'tour_version' => $definition['version'],
        ])->first();
        $operational = in_array($user->role, [
            UserRole::ScannerOperator,
            UserRole::StageOperator,
            UserRole::TechnicalOperator,
        ], true);

        return [
            ...$definition,
            'role' => $user->role->value,
            'status' => $progress?->status,
            'current_step' => $progress?->current_step ?? 0,
            'auto_offer' => $progress === null && ! ($operational && $ceremonyActive),
            'suppressed_for_live_ceremony' => $progress === null && $operational && $ceremonyActive,
            'checklist' => $this->checklist($user),
        ];
    }

    /** @return list<array<string, mixed>> */
    private function checklist(User $user): array
    {
        return match ($user->role) {
            UserRole::Administrator => [
                $this->item('ceremony', 'Create and activate a ceremony', '/admin/ceremonies', Ceremony::query()->exists()),
                $this->item('accounts', 'Create operator accounts', '/admin/users', User::query()->where('id', '!=', $user->id)->exists()),
                $this->item('pronunciation', 'Review the pronunciation dictionary', '/admin/pronunciation-rules', PronunciationRule::query()->where('is_enabled', true)->exists()),
                $this->item('readiness', 'Review ceremony readiness', '/admin', false, true),
            ],
            UserRole::DataEncoder => [
                $this->item('graduates', 'Add or import graduate data', '/admin/students', Student::query()->exists()),
                $this->item('pronunciation', 'Review spoken names and awards', '/admin/pronunciation-rules', PronunciationRule::query()->whereNotNull('ceremony_id')->exists()),
                $this->item('audio', 'Generate a private audio candidate', '/admin/voice-generation', AnnouncementGeneration::query()->exists()),
            ],
            UserRole::ScannerOperator => [
                $this->item('connection', 'Confirm scanner connection state', '/scanner', false, true),
                $this->item('focus', 'Practice scanner focus and fallback lookup', '/scanner', false, true),
                $this->item('feedback', 'Review accepted and rejected scan feedback', '/scanner', false, true),
            ],
            UserRole::StageOperator => [
                $this->item('audio', 'Activate and verify stage audio', '/stage-control', false, true),
                $this->item('queue', 'Rehearse queue and replay controls', '/stage-control', false, true),
                $this->item('recovery', 'Review holding-screen recovery', '/stage-control', false, true),
            ],
            UserRole::TechnicalOperator => [
                $this->item('connections', 'Verify device connections', '/admin/device-connections', Device::query()->exists()),
                $this->item('logs', 'Review operational logs', '/admin/scan-logs', false, true),
                $this->item('backup', 'Create and verify a backup', '/admin/backups', BackupRecord::query()->where('verification_status', 'passed')->exists()),
            ],
        };
    }

    /** @return array<string, mixed> */
    private function item(string $key, string $label, string $href, bool $complete, bool $training = false): array
    {
        return compact('key', 'label', 'href', 'complete', 'training');
    }
}
