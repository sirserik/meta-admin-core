<?php

use Illuminate\Support\Facades\Route;
use Meta\AdminCore\Http\Controllers\OpsPinController;

/**
 * Step-up ops-PIN unlock/lock (used by server-ops pages: firewall, backups).
 *
 * Mounted under {admin-core.prefix}. Behind the normal admin middleware
 * (auth + verified) so only logged-in admins see it; the PIN is the extra
 * factor. Always loaded — the controller redirects out when no PIN is set.
 */
$prefix = trim((string) config('admin-core.prefix', 'admin'), '/');

$middleware = array_merge(['web'], (array) config('admin-core.middleware', ['auth', 'verified']));

Route::middleware($middleware)
    ->prefix($prefix)
    ->name('admin-core.ops.')
    ->group(function () {
        Route::get('unlock', [OpsPinController::class, 'showUnlock'])->name('unlock');
        Route::post('unlock', [OpsPinController::class, 'verify'])->middleware('throttle:10,1')->name('unlock.verify');
        Route::post('lock', [OpsPinController::class, 'lock'])->name('lock');
    });
