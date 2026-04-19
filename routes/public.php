<?php

use Illuminate\Support\Facades\Route;

/**
 * Public routes shipped by meta/admin-core. Loaded without any prefix
 * or auth middleware — these are endpoints that live alongside the
 * consumer's public site.
 */

// Serve storage files through PHP. Plesk/Nginx sometimes intercepts
// `/storage/...` as a static location that bypasses Laravel; routing
// through `/media/...` avoids that and gracefully falls back if the
// file isn't yet symlinked into `public/`.
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

// Public sitemap — renders URLs contributed by consumers via
// AdminCore::sitemapUrl(). Cached via admin-core.sitemap.* config.
Route::get('/sitemap.xml',
    [\Meta\AdminCore\Http\Controllers\SitemapController::class, 'index'],
)->name('sitemap');
