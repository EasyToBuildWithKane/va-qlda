<?php

use App\Http\Controllers\Blocker\BlockerAttachmentController;
use App\Http\Controllers\Blocker\BlockerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Blockers (Vướng mắc) — cross-project tracker.
|--------------------------------------------------------------------------
*/

Route::prefix('blockers')->name('blockers.')->group(function () {
    Route::get('/', [BlockerController::class, 'index'])->name('index');
    Route::get('/evidence-link-preview', [BlockerController::class, 'evidenceLinkPreview'])->name('evidence-link-preview');
    Route::post('/', [BlockerController::class, 'store'])->name('store');
    Route::post('/bulk-create', [BlockerController::class, 'bulkStore'])->name('bulk-create');
    Route::post('/import', [BlockerController::class, 'import'])->name('import');
    Route::post('/bulk', [BlockerController::class, 'bulk'])->name('bulk');
    Route::post('/{blocker}/recheck', [BlockerController::class, 'recheck'])->name('recheck');
    Route::put('/{blocker}', [BlockerController::class, 'update'])->name('update');
    Route::delete('/{blocker}', [BlockerController::class, 'destroy'])->name('destroy');
    Route::get('/{blocker}/attachments/{attachment}/file', [BlockerAttachmentController::class, 'file'])->name('attachments.file');
    Route::post('/{blocker}/attachments', [BlockerAttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/{blocker}/attachments/{attachment}', [BlockerAttachmentController::class, 'destroy'])->name('attachments.destroy');
});
