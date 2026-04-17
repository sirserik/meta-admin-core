<?php

namespace Meta\AdminCore;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminCoreServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(AdminCore::class, fn () => new AdminCore());
        $this->app->alias(AdminCore::class, 'admin-core');

        $this->mergeConfigFrom(__DIR__ . '/../config/admin-core.php', 'admin-core');
    }

    public function boot(): void
    {
        // Inertia middleware — consumer apps must include it themselves.
        // Published so they can customise it.

        // Routes
        $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');

        // Blade root view namespace
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'admin-core');

        // Publishable config
        $this->publishes([
            __DIR__ . '/../config/admin-core.php' => config_path('admin-core.php'),
        ], 'admin-core-config');

        // Publishable Vue/CSS assets — consumers either publish to their resources/js
        // or point Vite alias directly at vendor (see install docs).
        $this->publishes([
            __DIR__ . '/../resources/js'  => resource_path('js/admin-core'),
            __DIR__ . '/../resources/css' => resource_path('css/admin-core'),
        ], 'admin-core-assets');

        // Publishable root blade view
        $this->publishes([
            __DIR__ . '/../resources/views/app.blade.php' => resource_path('views/admin-core/app.blade.php'),
        ], 'admin-core-views');
    }
}
