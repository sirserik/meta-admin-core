<?php

use Illuminate\Support\Facades\Route;
use Meta\AdminCore\Http\Controllers\Auth\AdminRecoveryController;

/**
 * Off-band admin password recovery.
 *
 * Mounted under {admin-core.prefix}/recover (default: /admin/recover).
 * All routes live inside the `web` middleware group (sessions + CSRF),
 * but NOT behind `auth` — the whole point is the user is locked out.
 *
 * The controller 404s if ADMIN_RESET_PIN isn't configured, so this file
 * is always loaded and simply no-ops on un-configured sites.
 */
$prefix = trim((string) config('admin-core.prefix', 'admin'), '/');

Route::middleware('web')
    ->prefix($prefix . '/recover')
    ->name('admin-core.recover.')
    ->group(function () {
        Route::get('/', [AdminRecoveryController::class, 'showPinForm'])
            ->name('pin.form');

        Route::post('/', [AdminRecoveryController::class, 'verifyPin'])
            ->middleware('throttle:admin-recovery')
            ->name('pin.verify');

        Route::middleware(\Meta\AdminCore\Http\Middleware\EnsureRecoveryPinVerified::class)
            ->group(function () {
                Route::get('password', [AdminRecoveryController::class, 'showPasswordForm'])
                    ->name('password.form');

                Route::post('password', [AdminRecoveryController::class, 'resetPassword'])
                    ->middleware('throttle:admin-recovery')
                    ->name('password.update');
            });
    });
