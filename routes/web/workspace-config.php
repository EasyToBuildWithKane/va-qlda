<?php

use App\Http\Controllers\Evaluation\EvaluationCriterionController;
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
        Route::get('/', [EvaluationCriterionController::class, 'index'])->name('index');
        Route::post('/', [EvaluationCriterionController::class, 'store'])->name('store');
        Route::get('/{evaluationCriterion}', [EvaluationCriterionController::class, 'show'])->name('show');
        Route::put('/{evaluationCriterion}', [EvaluationCriterionController::class, 'update'])->name('update');
        Route::delete('/{evaluationCriterion}', [EvaluationCriterionController::class, 'destroy'])->name('destroy');
    });
});
