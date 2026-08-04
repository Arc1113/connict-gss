<?php

namespace App\Providers;

use App\Contracts\CandidateAudioStore;
use App\Contracts\TextToSpeechProvider;
use App\Enums\UserRole;
use App\Models\AnnouncementTemplate;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentAward;
use App\Models\StudentImport;
use App\Models\User;
use App\Observers\AnnouncementTemplateSourceObserver;
use App\Observers\StudentAnnouncementSourceObserver;
use App\Observers\StudentAwardAnnouncementObserver;
use App\Policies\AuditLogPolicy;
use App\Policies\CeremonyPolicy;
use App\Policies\StudentImportPolicy;
use App\Policies\StudentPolicy;
use App\Services\AtomicPrivateCandidateAudioStore;
use App\Services\AzureSpeechProvider;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(TextToSpeechProvider::class, AzureSpeechProvider::class);
        $this->app->bind(CandidateAudioStore::class, AtomicPrivateCandidateAudioStore::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Student::observe(StudentAnnouncementSourceObserver::class);
        StudentAward::observe(StudentAwardAnnouncementObserver::class);
        AnnouncementTemplate::observe(AnnouncementTemplateSourceObserver::class);

        Gate::policy(Ceremony::class, CeremonyPolicy::class);
        Gate::policy(Student::class, StudentPolicy::class);
        Gate::policy(StudentImport::class, StudentImportPolicy::class);
        Gate::policy(AuditLog::class, AuditLogPolicy::class);

        Gate::before(function (
            User $user,
            string $ability,
            array $arguments,
        ): ?bool {
            if (! $user->is_active) {
                return false;
            }

            if (
                $user->isAdministrator()
                && ($arguments[0] ?? null) instanceof StudentImport
                && in_array(
                    $ability,
                    ['view', 'updateMapping', 'confirm'],
                    true,
                )
            ) {
                return null;
            }

            return $user->isAdministrator() ? true : null;
        });

        Gate::define(
            'manage-users',
            fn (User $user): bool => $user->hasRole(UserRole::Administrator),
        );
        Gate::define(
            'manage-ceremony-data',
            fn (User $user): bool => $user->hasRole(UserRole::DataEncoder),
        );
        Gate::define(
            'operate-scanner',
            fn (User $user): bool => $user->hasRole(UserRole::ScannerOperator),
        );
        Gate::define(
            'operate-stage',
            fn (User $user): bool => $user->hasRole(UserRole::StageOperator),
        );
        Gate::define(
            'manage-technical-operations',
            fn (User $user): bool => $user->hasRole(UserRole::TechnicalOperator),
        );
        Gate::define(
            'manage-device-connections',
            fn (User $user): bool => $user->hasRole(UserRole::TechnicalOperator),
        );
        Gate::define(
            'view-operational-logs',
            fn (User $user): bool => in_array($user->role, [
                UserRole::Administrator,
                UserRole::TechnicalOperator,
                UserRole::StageOperator,
            ], true),
        );
        Gate::define(
            'export-operational-reports',
            fn (User $user): bool => $user->hasRole(UserRole::Administrator),
        );
        Gate::define(
            'view-ceremony-reports',
            fn (User $user): bool => $user->hasRole(UserRole::Administrator),
        );
        Gate::define(
            'view-backups',
            fn (User $user): bool => in_array($user->role, [
                UserRole::Administrator,
                UserRole::TechnicalOperator,
            ], true),
        );
        Gate::define(
            'create-backups',
            fn (User $user): bool => in_array($user->role, [
                UserRole::Administrator,
                UserRole::TechnicalOperator,
            ], true),
        );
        Gate::define(
            'verify-restores',
            fn (User $user): bool => in_array($user->role, [
                UserRole::Administrator,
                UserRole::TechnicalOperator,
            ], true),
        );
        Gate::define(
            'reset-ceremony-operations',
            fn (User $user): bool => $user->hasRole(UserRole::Administrator),
        );
        Gate::define(
            'manage-announcement-settings',
            fn (User $user): bool => $user->hasRole(UserRole::Administrator),
        );
        Gate::define(
            'manage-student-awards',
            fn (User $user): bool => $user->hasRole(UserRole::DataEncoder),
        );
        Gate::define(
            'generate-announcement-candidates',
            fn (User $user): bool => $user->hasRole(UserRole::DataEncoder),
        );
        Gate::define(
            'review-announcement-candidates',
            fn (User $user): bool => $user->hasRole(UserRole::Administrator),
        );
        Gate::define(
            'view-announcement-usage',
            fn (User $user): bool => $user->hasRole(UserRole::Administrator),
        );
        Gate::define(
            'manage-announcement-batches',
            fn (User $user): bool => $user->hasRole(UserRole::Administrator),
        );
    }
}
