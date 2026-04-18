<?php

use Illuminate\Support\Facades\Route;
use Meta\AdminCore\Http\Controllers\DashboardController;
use Meta\AdminCore\Http\Controllers\ResourceController;

/**
 * Admin Core routes — auto-loaded by AdminCoreServiceProvider.
 *
 * Mounted under /admin by default (configurable via config('admin-core.prefix')).
 * All concrete resources are dispatched through ResourceController which reads
 * from AdminCore::getResource($name).
 */
$prefix = config('admin-core.prefix', 'admin');
$middleware = config('admin-core.middleware', ['auth', 'verified']);

Route::middleware($middleware)->prefix($prefix)->name('admin.')->group(function () use ($prefix) {
    // Dashboard
    Route::get('/', [DashboardController::class, 'index'])->name('spa.dashboard');

    // Legacy alias: /admin/dashboard → /admin
    // Some consumer apps (and old tests) still point to /admin/dashboard from
    // pre-headless days. Without this redirect the catch-all below treats
    // "dashboard" as a resource name and 404s. Keep it defensive.
    Route::redirect('/dashboard', '/' . $prefix, 302);

    // Generic resource CRUD — {resource} is the name registered via AdminCore::resource()
    Route::get(   '/{resource}',                 [ResourceController::class, 'index'])  ->name('resource.index');
    Route::get(   '/{resource}/create',          [ResourceController::class, 'create']) ->name('resource.create');
    Route::post(  '/{resource}',                 [ResourceController::class, 'store'])  ->name('resource.store');
    Route::get(   '/{resource}/{id}/edit',       [ResourceController::class, 'edit'])   ->name('resource.edit');
    Route::put(   '/{resource}/{id}',            [ResourceController::class, 'update']) ->name('resource.update');
    Route::patch( '/{resource}/{id}',            [ResourceController::class, 'update']);
    Route::delete('/{resource}/{id}',            [ResourceController::class, 'destroy'])->name('resource.destroy');
    Route::patch( '/{resource}/{id}/toggle-publish', [ResourceController::class, 'togglePublish'])->name('resource.toggle-publish');
});
