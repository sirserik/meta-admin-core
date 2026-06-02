<?php

use Illuminate\Support\Facades\Route;
use Meta\AdminCore\Http\Controllers\DocumentController;

/**
 * Polymorphic document attachments (HasDocuments).
 *
 * Admin CRUD under {prefix}/documents (admin auth). Public download/view at
 * /documents/{document}/{download,view} — served as attachments only, with
 * an anonymous-access gate via the PubliclyVisible contract.
 */
$prefix = trim((string) config('admin-core.prefix', 'admin'), '/');
$admin = array_merge(['web'], (array) config('admin-core.middleware', ['auth', 'verified']));

Route::middleware($admin)->prefix($prefix . '/documents')->name('admin-core.documents.')->group(function () {
    Route::post('/', [DocumentController::class, 'store'])->name('store');
    Route::post('/reorder', [DocumentController::class, 'reorder'])->name('reorder');
    Route::put('/{document}', [DocumentController::class, 'update'])->name('update');
    Route::delete('/{document}', [DocumentController::class, 'destroy'])->name('destroy');
});

Route::middleware('web')->prefix('documents')->name('admin-core.documents.public.')->group(function () {
    Route::get('/{document}/download', [DocumentController::class, 'download'])->name('download');
    Route::get('/{document}/view', [DocumentController::class, 'view'])->name('view');
});
