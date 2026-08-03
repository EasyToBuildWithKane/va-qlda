<?php

use App\Http\Controllers\Evaluation\EvaluationCriterionController;
use App\Http\Controllers\Evaluation\EvaluationFormController;
use App\Http\Controllers\Evaluation\EvaluationFormScoringController;
use App\Http\Controllers\Evaluation\EvaluationTemplateController;
use App\Http\Controllers\WorkspaceConfig\DailyReportScoringConfigController;
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

    Route::prefix('daily-report-scoring')->name('daily-report-scoring.')->group(function () {
        Route::get('/', [DailyReportScoringConfigController::class, 'edit'])->name('edit');
        Route::put('/', [DailyReportScoringConfigController::class, 'update'])->name('update');
    });

    Route::prefix('evaluation')->name('evaluation.')->group(function () {
        Route::get('/', [EvaluationCriterionController::class, 'index'])->name('index');
        Route::post('/', [EvaluationCriterionController::class, 'store'])->name('store');
        Route::post('/import', [EvaluationCriterionController::class, 'import'])->name('import');
        Route::get('/{evaluationCriterion}', [EvaluationCriterionController::class, 'show'])->name('show');
        Route::put('/{evaluationCriterion}', [EvaluationCriterionController::class, 'update'])->name('update');
        Route::delete('/{evaluationCriterion}', [EvaluationCriterionController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('evaluation-templates')->name('evaluation-templates.')->group(function () {
        Route::get('/', [EvaluationTemplateController::class, 'index'])->name('index');
        Route::get('/create', [EvaluationTemplateController::class, 'create'])->name('create');
        Route::post('/', [EvaluationTemplateController::class, 'store'])->name('store');
        Route::post('/import', [EvaluationTemplateController::class, 'import'])->name('import');
        Route::get('/export-logs', [EvaluationTemplateController::class, 'exportLogs'])->name('export-logs');
        Route::post('/export-logs', [EvaluationTemplateController::class, 'recordExport'])->name('export-logs.store');
        Route::post('/{evaluationTemplate}/duplicate', [EvaluationTemplateController::class, 'duplicate'])->name('duplicate');
        Route::get('/{evaluationTemplate}', [EvaluationTemplateController::class, 'show'])->name('show');
        Route::put('/{evaluationTemplate}', [EvaluationTemplateController::class, 'update'])->name('update');
        Route::delete('/{evaluationTemplate}', [EvaluationTemplateController::class, 'destroy'])->name('destroy');
    });

    Route::prefix('evaluation-forms')->name('evaluation-forms.')->group(function () {
        Route::get('/', [EvaluationFormController::class, 'index'])->name('index');
        Route::get('/create', [EvaluationFormController::class, 'create'])->name('create');
        Route::post('/', [EvaluationFormController::class, 'store'])->name('store');
        Route::post('/types', [EvaluationFormController::class, 'storeType'])->name('types.store');
        Route::get('/templates/{evaluationTemplate}/criteria', [EvaluationFormController::class, 'templateCriteria'])->name('templates.criteria');

        Route::post('/{evaluationForm}/open', [EvaluationFormScoringController::class, 'open'])->name('open');
        Route::post('/{evaluationForm}/close', [EvaluationFormScoringController::class, 'close'])->name('close');
        Route::post('/{evaluationForm}/reopen', [EvaluationFormScoringController::class, 'reopen'])->name('reopen');
        Route::get('/{evaluationForm}/scoring', [EvaluationFormScoringController::class, 'index'])->name('scoring.index');
        Route::get('/{evaluationForm}/scoring/{assignee}', [EvaluationFormScoringController::class, 'show'])->name('scoring.show');
        Route::put('/{evaluationForm}/scoring/{assignee}', [EvaluationFormScoringController::class, 'save'])->name('scoring.save');
        Route::post('/{evaluationForm}/scoring/{assignee}/submit', [EvaluationFormScoringController::class, 'submit'])->name('scoring.submit');

        Route::get('/{evaluationForm}/edit', [EvaluationFormController::class, 'edit'])->name('edit');
        Route::put('/{evaluationForm}', [EvaluationFormController::class, 'update'])->name('update');
        Route::delete('/{evaluationForm}', [EvaluationFormController::class, 'destroy'])->name('destroy');
    });
});
