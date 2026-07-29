<?php

use App\Http\Controllers\Evaluation\EvaluationConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace evaluation configuration (super-admin via policy)
|--------------------------------------------------------------------------
*/

Route::prefix('workspace-config/evaluation')->name('workspace.evaluation.')->group(function () {
    Route::get('/', [EvaluationConfigController::class, 'index'])->name('index');
    Route::get('/create', [EvaluationConfigController::class, 'create'])->name('create');
    Route::post('/', [EvaluationConfigController::class, 'store'])->name('store');

    Route::get('/{evaluationConfig}', [EvaluationConfigController::class, 'show'])->name('show');
    Route::get('/{evaluationConfig}/edit', [EvaluationConfigController::class, 'edit'])->name('edit');
    Route::put('/{evaluationConfig}', [EvaluationConfigController::class, 'update'])->name('update');
    Route::delete('/{evaluationConfig}', [EvaluationConfigController::class, 'destroy'])->name('destroy');

    Route::post('/{evaluationConfig}/apply-template', [EvaluationConfigController::class, 'applyTemplate'])
        ->name('apply-template');

    Route::post('/{evaluationConfig}/criteria', [EvaluationConfigController::class, 'storeCriterion'])
        ->name('criteria.store');
    Route::put('/{evaluationConfig}/criteria/{criterion}', [EvaluationConfigController::class, 'updateCriterion'])
        ->name('criteria.update');
    Route::delete('/{evaluationConfig}/criteria/{criterion}', [EvaluationConfigController::class, 'destroyCriterion'])
        ->name('criteria.destroy');
    Route::post('/{evaluationConfig}/criteria/reorder', [EvaluationConfigController::class, 'reorderCriteria'])
        ->name('criteria.reorder');
});
