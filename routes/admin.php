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

    // Logout — AdminLayout.vue posts here on «выйти». Inertia handling
    // is inside the controller: X-Inertia requests get a 409 + location
    // header so the browser hard-navigates instead of trying to render
    // the public site inside the admin shell.
    Route::post('/logout', [\Meta\AdminCore\Http\Controllers\LogoutController::class, 'destroy'])
        ->name('logout');

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

    // Generic file / image uploads used by the rich text editor and
    // the PageBlock data editor. Consumer-specific uploadImage routes
    // shadow this by default (they register first).
    Route::post('/upload/image', [\Meta\AdminCore\Http\Controllers\UploadController::class, 'uploadImage'])->name('upload.image.package');
    Route::post('/upload/file',  [\Meta\AdminCore\Http\Controllers\UploadController::class, 'uploadFile'])->name('upload.file');

    // Media library
    Route::get(   '/media',          [\Meta\AdminCore\Http\Controllers\MediaController::class, 'index'])->name('media.index');
    Route::post(  '/media',          [\Meta\AdminCore\Http\Controllers\MediaController::class, 'store'])->name('media.store');
    Route::put(   '/media/{medium}', [\Meta\AdminCore\Http\Controllers\MediaController::class, 'update'])->name('media.update');
    Route::delete('/media/{medium}', [\Meta\AdminCore\Http\Controllers\MediaController::class, 'destroy'])->name('media.destroy');

    // Key-value settings (name/email/phone/social-links text-style rows).
    Route::get('/settings',          [\Meta\AdminCore\Http\Controllers\SettingsController::class, 'index'])->name('settings.index');
    Route::put('/settings/{id}',     [\Meta\AdminCore\Http\Controllers\SettingsController::class, 'update'])->name('settings.update');

    // Admin audit log
    Route::get('/activity',          [\Meta\AdminCore\Http\Controllers\ActivityController::class, 'index'])->name('activity.index');

    // Permissions matrix (role × resource × action). Gracefully 404s
    // if spatie/laravel-permission is not installed in the consumer.
    Route::get('/permissions',       [\Meta\AdminCore\Http\Controllers\PermissionsController::class, 'index'])->name('permissions.index');
    Route::put('/permissions',       [\Meta\AdminCore\Http\Controllers\PermissionsController::class, 'update'])->name('permissions.update');

    // Form builder: CRUD + submissions browser + CSV export.
    Route::get(   '/forms',                         [\Meta\AdminCore\Http\Controllers\FormsController::class, 'index'])->name('forms.index');
    Route::get(   '/forms/create',                  [\Meta\AdminCore\Http\Controllers\FormsController::class, 'create'])->name('forms.create');
    Route::post(  '/forms',                         [\Meta\AdminCore\Http\Controllers\FormsController::class, 'store'])->name('forms.store');
    Route::get(   '/forms/{id}/edit',               [\Meta\AdminCore\Http\Controllers\FormsController::class, 'edit'])->name('forms.edit');
    Route::put(   '/forms/{id}',                    [\Meta\AdminCore\Http\Controllers\FormsController::class, 'update'])->name('forms.update');
    Route::delete('/forms/{id}',                    [\Meta\AdminCore\Http\Controllers\FormsController::class, 'destroy'])->name('forms.destroy');
    Route::get(   '/forms/{id}/submissions',        [\Meta\AdminCore\Http\Controllers\FormSubmissionsController::class, 'index'])->name('forms.submissions.index');
    Route::get(   '/forms/{id}/submissions/export', [\Meta\AdminCore\Http\Controllers\FormSubmissionsController::class, 'export'])->name('forms.submissions.export');
    Route::patch( '/forms/{id}/submissions/{subId}', [\Meta\AdminCore\Http\Controllers\FormSubmissionsController::class, 'setStatus'])->name('forms.submissions.status');
    Route::delete('/forms/{id}/submissions/{subId}', [\Meta\AdminCore\Http\Controllers\FormSubmissionsController::class, 'destroy'])->name('forms.submissions.destroy');

    // Taxonomies — vocabularies (tag/category/…) + term CRUD.
    Route::get(   '/taxonomies',          [\Meta\AdminCore\Http\Controllers\TaxonomyController::class, 'index'])->name('taxonomies.index');
    Route::post(  '/taxonomies',          [\Meta\AdminCore\Http\Controllers\TaxonomyController::class, 'store'])->name('taxonomies.store');
    Route::put(   '/taxonomies/{id}',     [\Meta\AdminCore\Http\Controllers\TaxonomyController::class, 'update'])->name('taxonomies.update');
    Route::delete('/taxonomies/{id}',     [\Meta\AdminCore\Http\Controllers\TaxonomyController::class, 'destroy'])->name('taxonomies.destroy');

    // Webhooks CRUD + manual test-fire.
    Route::get(   '/webhooks',            [\Meta\AdminCore\Http\Controllers\WebhooksController::class, 'index'])->name('webhooks.index');
    Route::post(  '/webhooks',            [\Meta\AdminCore\Http\Controllers\WebhooksController::class, 'store'])->name('webhooks.store');
    Route::put(   '/webhooks/{id}',       [\Meta\AdminCore\Http\Controllers\WebhooksController::class, 'update'])->name('webhooks.update');
    Route::delete('/webhooks/{id}',       [\Meta\AdminCore\Http\Controllers\WebhooksController::class, 'destroy'])->name('webhooks.destroy');
    Route::post(  '/webhooks/{id}/test',  [\Meta\AdminCore\Http\Controllers\WebhooksController::class, 'test'])->name('webhooks.test');

    // Revision history for the built-in PageBlock (registered under
    // the dedicated 'blocks' controller, not the generic resource
    // registry).
    Route::get('/blocks/{id}/revisions',
        [\Meta\AdminCore\Http\Controllers\RevisionController::class, 'indexForPageBlock'])
        ->name('blocks.revisions.index');
    Route::post('/blocks/{id}/revisions/{revisionId}/restore',
        [\Meta\AdminCore\Http\Controllers\RevisionController::class, 'restoreForPageBlock'])
        ->name('blocks.revisions.restore');

    // Revision history — per-resource list + restore. Resource name
    // matches AdminCore::resource() registry key. Registered after
    // the built-in /blocks/* routes so those win on exact match.
    Route::get('/{resource}/{id}/revisions',
        [\Meta\AdminCore\Http\Controllers\RevisionController::class, 'index'])
        ->name('revisions.index');
    Route::post('/{resource}/{id}/revisions/{revisionId}/restore',
        [\Meta\AdminCore\Http\Controllers\RevisionController::class, 'restore'])
        ->name('revisions.restore');

    // Cache management
    Route::get( '/cache',            [\Meta\AdminCore\Http\Controllers\CacheController::class, 'index'])->name('cache.index');
    Route::post('/cache/flush',      [\Meta\AdminCore\Http\Controllers\CacheController::class, 'flush'])->name('cache.flush');

    // SQLite + storage backups
    Route::get(   '/backup',          [\Meta\AdminCore\Http\Controllers\BackupController::class, 'index'])->name('backup.index');
    Route::post(  '/backup',          [\Meta\AdminCore\Http\Controllers\BackupController::class, 'create'])->name('backup.create');
    Route::get(   '/backup/download', [\Meta\AdminCore\Http\Controllers\BackupController::class, 'download'])->name('backup.download');
    Route::delete('/backup',          [\Meta\AdminCore\Http\Controllers\BackupController::class, 'destroy'])->name('backup.destroy');

    // Inbound leads (admission + consultation requests)
    Route::get(   '/leads',             [\Meta\AdminCore\Http\Controllers\LeadController::class, 'index'])->name('leads.index');
    Route::get(   '/leads/{id}',        [\Meta\AdminCore\Http\Controllers\LeadController::class, 'show'])->name('leads.show');
    Route::patch( '/leads/{id}/status', [\Meta\AdminCore\Http\Controllers\LeadController::class, 'updateStatus'])->name('leads.update-status');
    Route::delete('/leads/{id}',        [\Meta\AdminCore\Http\Controllers\LeadController::class, 'destroy'])->name('leads.destroy');

    // Rector Q&A workflow — publicly-submitted questions answered and
    // (optionally) published back to the public site.
    Route::get(   '/rector-questions',                  [\Meta\AdminCore\Http\Controllers\RectorQuestionController::class, 'index'])->name('rector-questions.index');
    Route::get(   '/rector-questions/{rectorQuestion}', [\Meta\AdminCore\Http\Controllers\RectorQuestionController::class, 'show'])->name('rector-questions.show');
    Route::put(   '/rector-questions/{rectorQuestion}', [\Meta\AdminCore\Http\Controllers\RectorQuestionController::class, 'update'])->name('rector-questions.update');
    Route::delete('/rector-questions/{rectorQuestion}', [\Meta\AdminCore\Http\Controllers\RectorQuestionController::class, 'destroy'])->name('rector-questions.destroy');

    // Admin user management (uses the consumer's User model via
    // config('auth.providers.users.model'); spatie/laravel-permission required).
    Route::get(   '/users',              [\Meta\AdminCore\Http\Controllers\UserController::class, 'index'])->name('users.index');
    Route::post(  '/users',              [\Meta\AdminCore\Http\Controllers\UserController::class, 'store'])->name('users.store');
    Route::put(   '/users/{user}',       [\Meta\AdminCore\Http\Controllers\UserController::class, 'update'])->name('users.update');
    Route::patch( '/users/{user}/role',  [\Meta\AdminCore\Http\Controllers\UserController::class, 'updateRole'])->name('users.update-role');
    Route::delete('/users/{user}',       [\Meta\AdminCore\Http\Controllers\UserController::class, 'destroy'])->name('users.destroy');

    // Site-wide specialised settings (social links, rector contact, secondary logo, menu toggles).
    Route::get( '/site-settings',                 [\Meta\AdminCore\Http\Controllers\SiteSettingsController::class, 'index'])->name('site-settings.index');
    Route::put( '/site-settings/social-media',    [\Meta\AdminCore\Http\Controllers\SiteSettingsController::class, 'updateSocialMedia'])->name('site-settings.social-media');
    Route::put( '/site-settings/rector',          [\Meta\AdminCore\Http\Controllers\SiteSettingsController::class, 'updateRector'])->name('site-settings.rector');
    Route::post('/site-settings/secondary-logo',  [\Meta\AdminCore\Http\Controllers\SiteSettingsController::class, 'updateSecondaryLogo'])->name('site-settings.secondary-logo');
    Route::put( '/site-settings/menu',            [\Meta\AdminCore\Http\Controllers\SiteSettingsController::class, 'updateMenu'])->name('site-settings.menu');

    // Hierarchical navigation tree (per-locale titles + URLs).
    Route::get(   '/menu',           [\Meta\AdminCore\Http\Controllers\MenuController::class, 'index'])->name('menu.index');
    Route::post(  '/menu',           [\Meta\AdminCore\Http\Controllers\MenuController::class, 'store'])->name('menu.store');
    Route::put(   '/menu/{id}',      [\Meta\AdminCore\Http\Controllers\MenuController::class, 'update'])->name('menu.update');
    Route::delete('/menu/{id}',      [\Meta\AdminCore\Http\Controllers\MenuController::class, 'destroy'])->name('menu.destroy');
    Route::post(  '/menu/reorder',   [\Meta\AdminCore\Http\Controllers\MenuController::class, 'reorder'])->name('menu.reorder');

    // Page builder blocks (hero, gallery, stats, …).
    Route::get(   '/blocks',                      [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'index'])->name('blocks.index');
    Route::get(   '/blocks/create',               [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'create'])->name('blocks.create');
    Route::post(  '/blocks',                      [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'store'])->name('blocks.store');
    Route::get(   '/blocks/{id}/edit',            [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'edit'])->name('blocks.edit');
    Route::put(   '/blocks/{id}',                 [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'update'])->name('blocks.update');
    Route::delete('/blocks/{id}',                 [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'destroy'])->name('blocks.destroy');
    Route::patch( '/blocks/{id}/toggle-active',   [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'toggleActive'])->name('blocks.toggle-active');
    Route::patch( '/blocks/{id}/publish',         [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'publish'])->name('blocks.publish');
    Route::patch( '/blocks/{id}/unpublish',       [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'unpublish'])->name('blocks.unpublish');
    Route::post(  '/blocks/reorder',              [\Meta\AdminCore\Http\Controllers\PageBlockController::class, 'reorder'])->name('blocks.reorder');

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
