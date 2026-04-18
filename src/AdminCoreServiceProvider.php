<?php

namespace Meta\AdminCore;

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

class AdminCoreServiceProvider extends ServiceProvider
{
    /**
     * Built-in feature modules shipped with the package. Each is a class
     * extending Meta\AdminCore\Features\FeatureModule.
     */
    protected function builtInFeatures(): array
    {
        return [
            \Meta\AdminCore\Features\GreenDealFeature::class,
            \Meta\AdminCore\Features\SdgFeature::class,
        ];
    }

    public function register(): void
    {
        $this->app->singleton(AdminCore::class, fn () => new AdminCore());
        $this->app->alias(AdminCore::class, 'admin-core');

        $this->mergeConfigFrom(__DIR__ . '/../config/admin-core.php', 'admin-core');
        $this->mergeConfigFrom(__DIR__ . '/../config/theme.php', 'theme');

        // Default BlockCatalog — consumers override by rebinding the
        // contract in their own service provider.
        $this->app->bind(
            \Meta\AdminCore\Contracts\BlockCatalog::class,
            \Meta\AdminCore\Support\DefaultBlockCatalog::class,
        );
    }

    public function boot(): void
    {
        // Console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Meta\AdminCore\Console\Commands\InstallCommand::class,
            ]);
        }

        // Inertia middleware — consumer apps must include it themselves.
        // Published so they can customise it.

        // Routes — register AFTER all providers have booted so:
        //  1) AdminCore::resource() calls from the consumer's AppServiceProvider
        //     are already in the registry when we enumerate them;
        //  2) consumer-specific admin routes from routes/web.php (like
        //     /admin/activity, /admin/leads) are already registered, so
        //     when we add our per-resource routes last, they don't shadow
        //     specific consumer routes.
        $this->app->booted(function () {
            // Public fallback routes (media serving, etc.) — loaded inside
            // the `web` middleware group so sessions/cookies are available.
            Route::middleware('web')->group(__DIR__ . '/../routes/public.php');

            // Register built-in feature modules BEFORE admin.php loads, so
            // their AdminCore::resource() calls land in the registry before
            // the route-enumeration loop reads from it. Otherwise feature-
            // registered resources never get per-resource routes emitted.
            if ($this->app->bound(AdminCore::class)) {
                $core = $this->app->make(AdminCore::class);

                foreach ($this->builtInFeatures() as $class) {
                    $module = new $class;
                    $core->registerFeature($module);
                    if ($module->available() && $core->enabled($module->name())) {
                        // Tag every resource / menuItem added inside with
                        // the feature name so the sidebar can paint them
                        // distinctly.
                        $core->withFeature($module->name(), fn ($c) => $module->register($c));
                    }
                }
            }

            $this->loadRoutesFrom(__DIR__ . '/../routes/admin.php');

            if ($this->app->bound(AdminCore::class)) {
                $core = $this->app->make(AdminCore::class);

                // Ship "Обновления" + "Фичи" menu items for every consumer.
                $prefix = config('admin-core.prefix', 'admin');
                // «Страницы сайта» — quick links to /admin/blocks?page=<slug>
                // for every page declared in the BlockCatalog. One flat
                // section, items ordered by catalog groups so related
                // pages cluster together.
                if ($this->app->bound(\Meta\AdminCore\Contracts\BlockCatalog::class)) {
                    $catalog = $this->app->make(\Meta\AdminCore\Contracts\BlockCatalog::class);
                    $order = 0;
                    foreach ($catalog->pagesGrouped() as $groupLabel => $pages) {
                        foreach ($pages as $slug => $label) {
                            $core->menuItem(
                                $label,
                                "/{$prefix}/blocks?page={$slug}",
                                'fa-file-lines',
                                'Страницы сайта',
                                80 + $order,
                            );
                            $order++;
                        }
                    }
                }

                $core->menuItem('Заявки',     "/{$prefix}/leads",         'fa-inbox',            'Система', 90);
                $core->menuItem('Активность', "/{$prefix}/activity",      'fa-clock-rotate-left','Система', 91);
                $core->menuItem('Бэкапы',     "/{$prefix}/backup",        'fa-box-archive',      'Система', 92);
                $core->menuItem('Настройки',  "/{$prefix}/settings",      'fa-sliders',          'Система', 93);
                $core->menuItem('Общие',      "/{$prefix}/site-settings", 'fa-gears',            'Система', 93);
                $core->menuItem('Меню',       "/{$prefix}/menu",          'fa-bars',             'Система', 94);
                $core->menuItem('Блоки',      "/{$prefix}/blocks",        'fa-cubes',            'Система', 94);
                $core->menuItem('Медиа',      "/{$prefix}/media",    'fa-photo-film',      'Система', 94);
                $core->menuItem('Тема сайта', "/{$prefix}/theme",    'fa-palette',         'Система', 95);
                $core->menuItem('Кэш',        "/{$prefix}/cache",    'fa-broom',           'Система', 96);
                $core->menuItem('Фичи',       "/{$prefix}/features", 'fa-toggle-on',       'Система', 98);
                $core->menuItem('Обновления', "/{$prefix}/updates",  'fa-cloud-arrow-down','Система', 99);
            }
        });

        // Package migrations — auto-loaded, run on `php artisan migrate`.
        // Each migration has a `Schema::hasTable()` guard so consumers that
        // already have these tables (e.g. from their own legacy schema)
        // are skipped cleanly.
        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');

        // Also publishable for consumers who want to edit/extend the schema.
        $this->publishes([
            __DIR__ . '/../database/migrations' => database_path('migrations'),
        ], 'admin-core-migrations');

        // Blade root view namespace
        $this->loadViewsFrom(__DIR__ . '/../resources/views', 'admin-core');

        // Publishable config
        $this->publishes([
            __DIR__ . '/../config/admin-core.php' => config_path('admin-core.php'),
            __DIR__ . '/../config/theme.php'      => config_path('theme.php'),
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
