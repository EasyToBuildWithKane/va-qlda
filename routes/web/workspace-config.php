<?php

use App\Http\Controllers\Evaluation\EvaluationConfigController;
use App\Http\Controllers\WorkspaceConfig\WorkspaceConfigController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace configuration (super-admin via policy / reserved permissions)
|--------------------------------------------------------------------------
|
| Umbrella module: hub at /workspace-config, child domains under
| /workspace-config/{domain}. Add new domains in this file + WorkspaceConfigCatalog.
|
*/

Route::prefix('workspace-config')->name('workspace.')->group(function () {
    Route::get('/', [WorkspaceConfigController::class, 'index'])->name('config.index');

    Route::prefix('evaluation')->name('evaluation.')->group(function () {
        Route::get('/', [EvaluationConfigController::class, 'index'])->name('index');
        Route::get('/create', [EvaluationConfigController::class, 'create'])->name('create');
        Route::post('/', [EvaluationConfigController::class, 'store'])->name('store');

        Route::get('/{evaluationConfig}', [EvaluationConfigController::class, 'show'])->name('show');
        Route::get('/{evaluationConfig}/edit', [EvaluationConfigController::class, 'edit'])->name('edit');
        Route::put('/{evaluationConfig}', [EvaluationConfigController::class, 'update'])->name('update');
        Route::delete('/{evaluationConfig}', [EvaluationConfigController::class, 'destroy'])->name('destroy');

        Route::post('/{evaluationConfig}/criteria', [EvaluationConfigController::class, 'storeCriterion'])
            ->name('criteria.store');
        Route::put('/{evaluationConfig}/criteria/{criterion}', [EvaluationConfigController::class, 'updateCriterion'])
            ->name('criteria.update');
        Route::delete('/{evaluationConfig}/criteria/{criterion}', [EvaluationConfigController::class, 'destroyCriterion'])
            ->name('criteria.destroy');
        Route::post('/{evaluationConfig}/criteria/reorder', [EvaluationConfigController::class, 'reorderCriteria'])
            ->name('criteria.reorder');
    });
});
