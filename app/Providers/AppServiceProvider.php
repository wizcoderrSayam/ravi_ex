<?php

namespace App\Providers;

use App\Models\User;
use Fruitcake\LaravelDebugbar\LaravelDebugbar;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Facades\Event;
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
        Gate::define('accessCms', fn (User $user) => $user->is_active && $user->isAdmin());

        $this->linkDebugbarToCmsDebugMode();
    }

    /**
     * Shows the debugbar while the CMS's Debug mode (Settings → General → App) is on. The CMS
     * applies that setting to app.debug in its own RouteMatched listener, registered before
     * this one. The debugbar is a dev dependency, and only resolved when it booted.
     */
    private function linkDebugbarToCmsDebugMode(): void
    {
        if (! class_exists(LaravelDebugbar::class)) {
            return;
        }

        Event::listen(RouteMatched::class, function (): void {
            if (! $this->app->resolved(LaravelDebugbar::class)) {
                return;
            }

            $debugbar = $this->app->make(LaravelDebugbar::class);

            if (config('app.debug') && LaravelDebugbar::canBeEnabled()) {
                $debugbar->enable();
            } else {
                $debugbar->disable();
            }
        });
    }
}
