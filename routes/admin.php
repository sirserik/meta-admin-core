<?php

use Illuminate\Support\Facades\Route;
use Meta\AdminCore\Facades\AdminCore;
use Meta\AdminCore\Http\Controllers\DashboardController;
use Meta\AdminCore\Http\Controllers\ResourceController;

/**
 * Admin Core routes — auto-loaded by AdminCoreServiceProvider::booted().
 *
 * Mounted under /admin by default (configurable via config('admin-core.prefix')).
 * All concrete resources are dispatched through ResourceController which reads
 * from AdminCore::getResource($name).
 *
 * Instead of a catch-all /admin/{resource} — which would eat any specific
 * consumer route like /admin/activity, /admin/leads — we enumerate every
 * registered resource and register narrow routes per-resource. This way
 * consumer-specific routes (registered from routes/web.php after the
 * package boot fires) always take priority when the path matches them
 * exactly, and the package still handles every AdminCore::resource()-ed
 * name without the consumer lifting a finger.
 */
$prefix = config('admin-core.prefix', 'admin');
$middleware = config('admin-core.middleware', ['auth', 'verified']);

// Always prepend the `web` middleware group — it ships sessions, CSRF,
// cookies and route-model binding. Without it auth() can't pull the user
// from the session and every protected route 302s to /login in a loop.
$middleware = array_values(array_unique(array_merge(['web'], (array) $middleware)));

Route::middleware($middleware)->prefix($prefix)->name('admin.')->group(function () use ($prefix) {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('spa.dashboard');

    // Legacy alias: /admin/dashboard → /admin (302). Old Blade-admin code
    // pointed here before the headless migration; keep backwards compat.
    Route::redirect('/dashboard', '/' . $prefix, 302);

    // System updates (package-level updater)
    Route::get(   '/updates',       [\Meta\AdminCore\Http\Controllers\UpdateController::class, 'index'])->name('updates.index');
    Route::post(  '/updates/check', [\Meta\AdminCore\Http\Controllers\UpdateController::class, 'check'])->name('updates.check');
    Route::post(  '/updates/run',   [\Meta\AdminCore\Http\Controllers\UpdateController::class, 'run'])->name('updates.run');

    // Feature toggles
    Route::get(  '/features',            [\Meta\AdminCore\Http\Controllers\FeaturesController::class, 'index'])->name('features.index');
    Route::patch('/features/{feature}',  [\Meta\AdminCore\Http\Controllers\FeaturesController::class, 'toggle'])->name('features.toggle');

    // Design-token theme
    Route::get( '/theme',       [\Meta\AdminCore\Http\Controllers\ThemeController::class, 'index'])->name('theme.index');
    Route::put( '/theme',       [\Meta\AdminCore\Http\Controllers\ThemeController::class, 'update'])->name('theme.update');
    Route::post('/theme/reset', [\Meta\AdminCore\Http\Controllers\ThemeController::class, 'reset'])->name('theme.reset');

    // Media library
    Route::get(   '/media',          [\Meta\AdminCore\Http\Controllers\MediaController::class, 'index'])->name('media.index');
    Route::post(  '/media',          [\Meta\AdminCore\Http\Controllers\MediaController::class, 'store'])->name('media.store');
    Route::put(   '/media/{medium}', [\Meta\AdminCore\Http\Controllers\MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{medium}', [\Meta\AdminCore\Http\Controllers\MediaController::class, 'destroy'])->name('media.destroy');

    // Key-value settings (name/email/phone/social-links text-style rows).
    Route::get('/settings',          [\Meta\AdminCore\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/{id}',     [\Meta\AdminCore\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');

    // Register narrow routes per registered resource. This iterates
    // AdminCore's registry at boot time.
    foreach (AdminCore::getResources()->keys() as $name) {
        // Use `where('resource', $name)` so the route only matches this exact name.
        Route::get(   "/{$name}",                 [ResourceController::class, 'index'])
            ->defaults('resource', $name)->name("{$name}.index");
        Route::get(   "/{$name}/create",          [ResourceController::class, 'create'])
            ->defaults('resource', $name)->name("{$name}.create");
        Route::post(  "/{$name}",                 [ResourceController::class, 'store'])
            ->defaults('resource', $name)->name("{$name}.store");
        Route::get(   "/{$name}/{id}/edit",       [ResourceController::class, 'edit'])
            ->defaults('resource', $name)->name("{$name}.edit");
        Route::put(   "/{$name}/{id}",            [ResourceController::class, 'update'])
            ->defaults('resource', $name)->name("{$name}.update");
        Route::patch( "/{$name}/{id}",            [ResourceController::class, 'update'])
            ->defaults('resource', $name);
        Route::delete("/{$name}/{id}",            [ResourceController::class, 'destroy'])
            ->defaults('resource', $name)->name("{$name}.destroy");
        Route::patch( "/{$name}/{id}/toggle-publish", [ResourceController::class, 'togglePublish'])
            ->defaults('resource', $name)->name("{$name}.toggle-publish");
    }
});
