<?php

use Illuminate\Support\Facades\Route;
use Meta\AdminCore\Http\Controllers\ServerBackupController;

/**
 * Privilege-isolated server backups (opt-in BackupFeature).
 *
 * Mounted at {admin-core.prefix}/backups (plural — distinct from the legacy
 * in-process /{prefix}/backup zip backup). Behind admin auth + the step-up
 * ops-PIN gate (the gate no-ops when no PIN is set). Always loaded; the
 * controller 404s when the feature is disabled.
 */
$prefix = trim((string) config('admin-core.prefix', 'admin'), '/');

$middleware = array_merge(
    ['web'],
    (array) config('admin-core.middleware', ['auth', 'verified']),
    ['admin-core.ops-pin'],
);

Route::middleware($middleware)
    ->prefix($prefix . '/backups')
    ->name('admin-core.backups.')
    ->group(function () {
        Route::get('/', [ServerBackupController::class, 'index'])->name('index');
        Route::post('/backup', [ServerBackupController::class, 'backup'])->name('backup');
        Route::post('/restore', [ServerBackupController::class, 'restore'])->name('restore');
        Route::get('/download', [ServerBackupController::class, 'download'])->name('download');
        Route::delete('/delete', [ServerBackupController::class, 'destroy'])->name('delete');
    });
