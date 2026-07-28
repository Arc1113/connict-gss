<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
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
        Gate::before(function (User $user): ?bool {
            if (! $user->is_active) {
                return false;
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
