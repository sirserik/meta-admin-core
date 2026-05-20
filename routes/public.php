<?php

use Illuminate\Support\Facades\Route;

/**
 * Public routes shipped by meta/admin-core. Loaded without any prefix
 * or auth middleware — these are endpoints that live alongside the
 * consumer's public site.
 *
 * Each registration is opt-out:
 *   - `config('admin-core.routes.{name}', true)` — toggle per-route
 *   - safety net: if the consumer already registered a route at the
 *     same URI, admin-core skips silently (booted() runs after
 *     web.php, so consumer routes are already in the routes table).
 *
 * Defaults are all `true` — packages should be additive out of the box.
 * Override in config/admin-core.php after `vendor:publish`.
 */

// Helper: does any GET/POST route already claim this URI? Used to avoid
// stomping on consumer-defined endpoints with the same path.
$uriTaken = function (string $method, string $uri): bool {
    $uri = ltrim($uri, '/');
    foreach (Route::getRoutes()->get($method) as $r) {
        if ($r->uri() === $uri) {
            return true;
        }
    }
    return false;
};

// /media/{path} — Plesk/Nginx-safe alternative to /storage/{path}.
if (config('admin-core.routes.media', true) && ! $uriTaken('GET', '/media/{path}')) {
    Route::get('/media/{path}', function (string $path) {
        $path = str_replace('..', '', $path);

        $candidates = [
            public_path('media/' . $path),
            storage_path('app/public/' . $path),
        ];

        foreach ($candidates as $full) {
            if (is_file($full)) {
                return response()->file($full, [
                    'Cache-Control' => 'public, max-age=31536000',
                ]);
            }
        }

        abort(404);
    })->where('path', '.*')->name('media.serve');

    // 301 any legacy /storage/... URL to /media/..., so old HTML stays live.
    Route::get('/storage/{path}', function (string $path) {
        return redirect('/media/' . $path, 301);
    })->where('path', '.*');
}

// /sitemap.xml — admin-core's built-in renders URLs contributed via
// AdminCore::sitemapUrl(). Skipped if consumer has its own /sitemap.xml.
if (config('admin-core.routes.sitemap', true) && ! $uriTaken('GET', '/sitemap.xml')) {
    Route::get('/sitemap.xml',
        [\Meta\AdminCore\Http\Controllers\SitemapController::class, 'index'],
    )->name('sitemap');
}

// /api/forms/{slug} — public form submission endpoint.
if (config('admin-core.routes.forms', true) && ! $uriTaken('POST', '/api/forms/{slug}')) {
    Route::post('/api/forms/{slug}',
        [\Meta\AdminCore\Http\Controllers\FormSubmissionsController::class, 'submit'],
    )->where('slug', '[\w\-]+')->name('forms.submit');
}

// /api/content/* — read-only Content API.
if (config('admin-core.routes.content_api', true)) {
    Route::prefix('api/content')->group(function () {
        Route::get('pages/{slug}',           [\Meta\AdminCore\Http\Controllers\ContentApiController::class, 'pageBySlug'])->where('slug', '[\w\-]+')->name('content-api.page');
        Route::get('{resource}',             [\Meta\AdminCore\Http\Controllers\ContentApiController::class, 'resourceList'])->where('resource', '[\w\-]+')->name('content-api.list');
        Route::get('{resource}/{idOrSlug}',  [\Meta\AdminCore\Http\Controllers\ContentApiController::class, 'resourceShow'])->where('resource', '[\w\-]+')->name('content-api.show');
    });
}
