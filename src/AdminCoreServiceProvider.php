<?php

namespace Meta\AdminCore;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Foundation\Application;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
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
            \Meta\AdminCore\Features\ProcurementsFeature::class,
            \Meta\AdminCore\Features\FirewallFeature::class,
            \Meta\AdminCore\Features\BackupFeature::class,
        ];
    }

    public function register(): void
    {
        $this->app->singleton(AdminCore::class, fn () => new AdminCore());
        $this->app->alias(AdminCore::class, 'admin-core');

        // BlockRegistry — singleton so consumer's AppServiceProvider can
        // call AdminCore::useBlocks() and AdminCore::registerBlock() and
        // every render path sees the same registry.
        $this->app->singleton(\Meta\AdminCore\Blocks\BlockRegistry::class);

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
        // Stable morph alias defaults — keep `translations.translatable_type`
        // (and any other morph column) free of FQCN drift even when the
        // consumer ships its own classes for the same row (e.g. App\Models\
        // PageBlock alongside Meta\AdminCore\Models\PageBlock).
        //
        // Laravel's morphMap() merges with whatever already exists, so
        // consumer apps that register their own aliases first (in their
        // AppServiceProvider::register()) win because admin-core boots
        // after them; consumers that register *later* (in boot()) will
        // overwrite these defaults by virtue of array_merge order.
        // Either way the consumer is in control — these are just safe
        // fallbacks so out-of-the-box translations are alias-typed.
        \Illuminate\Database\Eloquent\Relations\Relation::morphMap([
            'page_block' => \Meta\AdminCore\Models\PageBlock::class,
            'menu_item'  => \Meta\AdminCore\Models\MenuItem::class,
            'setting'    => \Meta\AdminCore\Models\Setting::class,
            'lead'       => \Meta\AdminCore\Models\Lead::class,
        ]);

        // Admin recovery: rate limiter + middleware alias + routes. Limiter
        // and alias registered unconditionally so the `throttle:admin-recovery`
        // middleware reference in routes/recovery.php always resolves —
        // the controller itself 404s when ADMIN_RESET_PIN is blank, so the
        // feature stays invisible on un-configured sites.
        RateLimiter::for('admin-recovery', function (Request $r) {
            $attempts = (int) config('admin-core.recovery.pin_attempts', 5);
            $decay    = max(60, (int) config('admin-core.recovery.pin_decay', 3600));
            return Limit::perMinutes((int) ceil($decay / 60), $attempts)->by($r->ip());
        });

        if (method_exists($this->app['router'], 'aliasMiddleware')) {
            $this->app['router']->aliasMiddleware(
                'admin-core.recovery.pin',
                \Meta\AdminCore\Http\Middleware\EnsureRecoveryPinVerified::class
            );
            // Step-up ops-PIN gate for server-ops pages (firewall, backups).
            // No-op when no PIN configured, so always-on in routes is safe.
            $this->app['router']->aliasMiddleware(
                'admin-core.ops-pin',
                \Meta\AdminCore\Http\Middleware\EnsureOpsPinVerified::class
            );
        }

        $this->loadRoutesFrom(__DIR__ . '/../routes/recovery.php');

        // Step-up ops-PIN unlock/lock page (used by firewall + backups).
        $this->loadRoutesFrom(__DIR__ . '/../routes/ops.php');

        // SSH firewall allow-list (opt-in FirewallFeature). Always loaded;
        // the controller 404s when the feature is disabled, so it no-ops on
        // sites that don't use it.
        $this->loadRoutesFrom(__DIR__ . '/../routes/firewall.php');

        // Server backups (opt-in BackupFeature). Always loaded; controller
        // 404s when the feature is disabled.
        $this->loadRoutesFrom(__DIR__ . '/../routes/backups.php');

        // Console commands
        if ($this->app->runningInConsole()) {
            $this->commands([
                \Meta\AdminCore\Console\Commands\InstallCommand::class,
                \Meta\AdminCore\Console\Commands\ApplyScheduleCommand::class,
                \Meta\AdminCore\Console\Commands\ExportContentCommand::class,
                \Meta\AdminCore\Console\Commands\ImportContentCommand::class,
                \Meta\AdminCore\Console\Commands\MigrateToDocumentListCommand::class,
                \Meta\AdminCore\Console\Commands\MakeBlockCommand::class,
                \Meta\AdminCore\Console\Commands\MigrateMorphTypesCommand::class,
                \Meta\AdminCore\Console\Commands\FirewallSyncScriptCommand::class,
                \Meta\AdminCore\Console\Commands\BackupAgentScriptCommand::class,
                \Meta\AdminCore\Console\Commands\StorageCheckCommand::class,
                \Meta\AdminCore\Console\Commands\StorageFixPermissionsCommand::class,
                \Meta\AdminCore\Console\Commands\StorageRelinkCommand::class,
                \Meta\AdminCore\Console\Commands\StorageCleanupBackupCommand::class,
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
                // «Блоки по страницам» — sidebar shortcut to the block
                // editor pre-filtered to each page declared in BlockCatalog.
                //
                // Distinct from the «Страницы» resource (which stores
                // per-page metadata: SEO, template, status, cover) — this
                // section edits the VISUAL BLOCK content composed into
                // each page (hero, cards, FAQ, timeline, …).
                //
                // One flat section, items ordered by catalog groups so
                // related pages cluster together. Section header shows
                // the count; collapsed by default unless the current URL
                // matches one of the items.
                if ($this->app->bound(\Meta\AdminCore\Contracts\BlockCatalog::class)) {
                    $catalog = $this->app->make(\Meta\AdminCore\Contracts\BlockCatalog::class);
                    $order = 0;
                    foreach ($catalog->pagesGrouped() as $pages) {
                        foreach ($pages as $slug => $label) {
                            $core->menuItem(
                                $label,
                                "/{$prefix}/blocks?page={$slug}",
                                'fa-cube',
                                'Блоки по страницам',
                                80 + $order,
                            );
                            $order++;
                        }
                    }
                }

                $core->menuItem('Вопросы ректору', "/{$prefix}/rector-questions", 'fa-circle-question',  'Контент', 70);
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
                $core->menuItem('Webhooks',   "/{$prefix}/webhooks", 'fa-bolt',             'Система', 97);
                $core->menuItem('Словари',    "/{$prefix}/taxonomies", 'fa-tags',          'Контент', 60);
                $core->menuItem('Формы',      "/{$prefix}/forms",      'fa-square-check', 'Контент', 65);

                // Permissions matrix + Users — surfaced only if the
                // consumer has spatie/laravel-permission, since both
                // controllers hard-require it.
                if (class_exists(\Spatie\Permission\Models\Role::class)) {
                    $core->menuItem('Пользователи', "/{$prefix}/users",       'fa-users',        'Система', 96);
                    $core->menuItem('Доступы',      "/{$prefix}/permissions", 'fa-user-shield',  'Система', 97);
                }
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

        // Block templates: add core's resources/views to the global view
        // finder as a *low-priority* path. Consumer apps can override any
        // block (e.g. resources/views/blocks/v2/hero.blade.php) and Laravel
        // will pick the local copy first — no `vendor:publish` needed.
        $this->callAfterResolving('view', function ($view) {
            $view->addLocation(__DIR__ . '/../resources/views');
        });

        // Anonymous Blade components under the `admin-core::` namespace.
        // Consumer templates use e.g. <x-admin-core::documents :items="…"/>.
        \Illuminate\Support\Facades\Blade::anonymousComponentNamespace(
            __DIR__ . '/../resources/views/components',
            'admin-core'
        );

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
