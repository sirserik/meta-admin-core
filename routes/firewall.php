<?php

use Illuminate\Support\Facades\Route;
use Meta\AdminCore\Http\Controllers\FirewallController;

/**
 * SSH firewall allow-list (opt-in FirewallFeature).
 *
 * Mounted under {admin-core.prefix}/firewall (default: /admin/firewall).
 * Always loaded; the controller 404s when `admin-core.features.firewall`
 * is off, so this file no-ops on sites that don't use the feature.
 *
 * Self-contained Blade page (not the admin SPA) — a break-glass tool that
 * must work even if the SPA build is broken. Behind the normal admin
 * middleware (auth + verified), plus an OPTIONAL step-up gate configured
 * via `admin-core.firewall.gate` (e.g. an ops-PIN middleware alias).
 */
$prefix = trim((string) config('admin-core.prefix', 'admin'), '/');

$middleware = array_merge(
    ['web'],
    (array) config('admin-core.middleware', ['auth', 'verified']),
    ['admin-core.ops-pin'],                          // step-up PIN (no-op when unset)
    array_filter([config('admin-core.firewall.gate')]), // extra custom gate, optional
);

Route::middleware($middleware)
    ->prefix($prefix . '/firewall')
    ->name('admin-core.firewall.')
    ->group(function () {
        Route::get('/', [FirewallController::class, 'index'])->name('index');
        Route::post('/', [FirewallController::class, 'store'])->name('store');
        Route::delete('/{rule}', [FirewallController::class, 'destroy'])->name('destroy');
    });
