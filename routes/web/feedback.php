<?php

use App\Http\Controllers\Feedback\FeedbackController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Feedback tracker.
|--------------------------------------------------------------------------
*/

Route::prefix('feedback')->name('feedback.')->group(function () {
    Route::get('/', [FeedbackController::class, 'index'])->name('index');
    Route::post('/', [FeedbackController::class, 'store'])->name('store');
    Route::get('/{feedback}', [FeedbackController::class, 'show'])->name('show');
    Route::put('/{feedback}', [FeedbackController::class, 'update'])->name('update');
    Route::delete('/{feedback}', [FeedbackController::class, 'destroy'])->name('destroy');
});
