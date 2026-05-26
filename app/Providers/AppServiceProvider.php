<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Pagination\Paginator; // Using Bootstrap Paginate
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;

// Models and Observers
use App\Models\SystemModule;
use App\Observers\SystemModuleObserver;


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
        // Call Boostrap on paginate
        Paginator::useBootstrap();

        // Register Observers
        SystemModule::observe(SystemModuleObserver::class);

        // Super admin bypass: any user with role "super" passes ALL gates.
        Gate::before(function ($user, $ability) {
            return method_exists($user, 'hasRole') && $user->hasRole('super') ? true : null;
        });

        // ── API rate limiter ──────────────────────────────────────────────
        // Throttles each authenticated token (or IP for unauth requests) to
        // 60 requests/minute. Tune in production based on real usage.
        RateLimiter::for('api', function (Request $request) {
            return Limit::perMinute(60)->by(
                $request->user()?->id ?: $request->ip()
            );
        });

        // Share tenant_name in all views
        View::composer('*', function ($view) {
            $tenantName = null;

            if (Auth::check()) {
                // Load and rescue tenant relationship
                $user = Auth::user()->loadMissing('tenant');
                $tenantName = $user->tenant ? $user->tenant->name : null;
            }

            $view->with('tenant_name', $tenantName);
        });
    }

}