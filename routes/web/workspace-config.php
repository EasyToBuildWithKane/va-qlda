<?php

use App\Http\Controllers\Evaluation\EvaluationCriterionController;
use App\Http\Controllers\WorkspaceConfig\WorkspaceConfigController;
use App\Http\Controllers\WorkspaceConfig\WorkspaceProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Workspace configuration
|--------------------------------------------------------------------------
|
| Hub lists department workspaces. Child domains under /workspace-config/{domain}.
| Per-department shell: /workspace-config/w/{departmentCode}.
|
*/

Route::prefix('workspace-config')->name('workspace.')->group(function () {
    Route::get('/', [WorkspaceConfigController::class, 'index'])->name('config.index');
    Route::post('/ensure-bulk', [WorkspaceProfileController::class, 'ensureBulk'])->name('profiles.ensure-bulk');

    Route::prefix('w/{departmentCode}')->name('profiles.')->group(function () {
        Route::get('/', [WorkspaceProfileController::class, 'show'])->name('show');
        Route::patch('/', [WorkspaceProfileController::class, 'update'])->name('update');
        Route::post('/ensure', [WorkspaceProfileController::class, 'ensure'])->name('ensure');
    });

    Route::prefix('evaluation')->name('evaluation.')->group(function () {
        Route::get('/', [EvaluationCriterionController::class, 'index'])->name('index');
        Route::post('/', [EvaluationCriterionController::class, 'store'])->name('store');
        Route::post('/import', [EvaluationCriterionController::class, 'import'])->name('import');
        Route::get('/{evaluationCriterion}', [EvaluationCriterionController::class, 'show'])->name('show');
        Route::put('/{evaluationCriterion}', [EvaluationCriterionController::class, 'update'])->name('update');
        Route::delete('/{evaluationCriterion}', [EvaluationCriterionController::class, 'destroy'])->name('destroy');
    });
});
