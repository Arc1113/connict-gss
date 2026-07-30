<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Ceremony;
use App\Models\Student;
use App\Models\StudentImport;
use App\Models\User;
use App\Policies\AuditLogPolicy;
use App\Policies\CeremonyPolicy;
use App\Policies\StudentImportPolicy;
use App\Policies\StudentPolicy;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
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
    }
}
