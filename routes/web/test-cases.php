<?php

use App\Http\Controllers\TestCase\TestCaseAttachmentController;
use App\Http\Controllers\TestCase\TestCaseController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Test Cases (QA) — cross-project tracker.
|--------------------------------------------------------------------------
*/

Route::prefix('test-cases')->name('test-cases.')->group(function () {
    Route::get('/', [TestCaseController::class, 'index'])->name('index');
    Route::post('/', [TestCaseController::class, 'store'])->name('store');
    Route::post('/import', [TestCaseController::class, 'import'])->name('import');
    Route::put('/{testCase}', [TestCaseController::class, 'update'])->name('update');
    Route::delete('/{testCase}', [TestCaseController::class, 'destroy'])->name('destroy');
    Route::post('/{testCase}/execute', [TestCaseController::class, 'execute'])->name('execute');

    Route::get('/{testCase}/attachments/{attachment}/file', [TestCaseAttachmentController::class, 'file'])->name('attachments.file');
    Route::post('/{testCase}/attachments', [TestCaseAttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/{testCase}/attachments/{attachment}', [TestCaseAttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Test suites (nhóm kiểm thử)
    Route::post('/suites', [TestCaseController::class, 'suiteStore'])->name('suites.store');
    Route::put('/suites/{suite}', [TestCaseController::class, 'suiteUpdate'])->name('suites.update');
    Route::delete('/suites/{suite}', [TestCaseController::class, 'suiteDestroy'])->name('suites.destroy');
});
