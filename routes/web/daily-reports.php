<?php

use App\Http\Controllers\DailyReport\DailyReportController;
use App\Http\Controllers\DailyReport\DailyReportReviewController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Daily Report — static segments before /{report} to avoid capture.
|--------------------------------------------------------------------------
*/

Route::prefix('daily-reports')->name('daily-reports.')->group(function () {
    Route::get('/', [DailyReportController::class, 'index'])->name('index');
    Route::get('/export-data', [DailyReportController::class, 'exportData'])->name('export-data');
    Route::get('/today', [DailyReportController::class, 'today'])->name('today');
    Route::get('/review', [DailyReportReviewController::class, 'index'])->name('review');
    Route::post('/', [DailyReportController::class, 'store'])->name('store');
    Route::get('/{report}', [DailyReportController::class, 'show'])->name('show');
    Route::put('/{report}', [DailyReportController::class, 'update'])->name('update');
    Route::delete('/{report}', [DailyReportController::class, 'destroy'])->name('destroy');
    Route::post('/{report}/submit', [DailyReportController::class, 'submit'])->name('submit');
    Route::post('/{report}/recall', [DailyReportController::class, 'recall'])->name('recall');
    Route::post('/{report}/score', [DailyReportReviewController::class, 'score'])->name('score');
    Route::post('/{report}/reject', [DailyReportReviewController::class, 'reject'])->name('reject');
});
