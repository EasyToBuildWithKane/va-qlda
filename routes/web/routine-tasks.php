<?php

use App\Http\Controllers\RoutineTaskController;
use Illuminate\Support\Facades\Route;

Route::prefix('routine-tasks')->name('routine-tasks.')->group(function () {
    Route::get('/', [RoutineTaskController::class, 'index'])->name('index');
    Route::post('/', [RoutineTaskController::class, 'store'])->name('store');
    Route::post('/reorder', [RoutineTaskController::class, 'reorder'])->name('reorder');
    Route::put('/{routineTask}', [RoutineTaskController::class, 'update'])->name('update');
    Route::post('/{routineTask}/toggle-status', [RoutineTaskController::class, 'toggleStatus'])->name('toggle-status');
    Route::delete('/{routineTask}', [RoutineTaskController::class, 'destroy'])->name('destroy');
});
