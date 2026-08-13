<?php

use App\Http\Controllers\RoutineTaskAttachmentController;
use App\Http\Controllers\RoutineTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('routine-tasks')->name('routine-tasks.')->group(function () {
    Route::get('/', [RoutineTaskController::class, 'index'])->name('index');
    Route::post('/', [RoutineTaskController::class, 'store'])->name('store');
    Route::post('/reorder', [RoutineTaskController::class, 'reorder'])->name('reorder');
    Route::put('/{routineTask}', [RoutineTaskController::class, 'update'])->name('update');
    Route::post('/{routineTask}/toggle-status', [RoutineTaskController::class, 'toggleStatus'])->name('toggle-status');
    Route::delete('/{routineTask}', [RoutineTaskController::class, 'destroy'])->name('destroy');

    Route::get('/{routineTask}/attachments/{attachment}/file', [RoutineTaskAttachmentController::class, 'file'])->name('attachments.file');
    Route::post('/{routineTask}/attachments', [RoutineTaskAttachmentController::class, 'store'])->name('attachments.store');
    Route::delete('/{routineTask}/attachments/{attachment}', [RoutineTaskAttachmentController::class, 'destroy'])->name('attachments.destroy');
});
