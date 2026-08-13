<?php

use App\Http\Controllers\Project\EmailNotificationController;
use App\Http\Controllers\Project\EpicController;
use App\Http\Controllers\Project\ProjectAttachmentController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectMemberController;
use App\Http\Controllers\Project\SprintController;
use App\Http\Controllers\Project\TaskAttachmentController;
use App\Http\Controllers\Project\TaskController;
use App\Http\Controllers\Project\TaskWatcherController;
use App\Http\Controllers\Project\WeeklyReportController;
use App\Http\Controllers\Project\WorklogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Projects + timeline / sprints / tasks / worklogs / members.
|--------------------------------------------------------------------------
| Static segments registered before /{project} to avoid capture.
*/

Route::prefix('projects')->name('projects.')->group(function () {
    Route::get('/', [ProjectController::class, 'index'])->name('index');
    Route::get('/create', [ProjectController::class, 'create'])->name('create');
    Route::post('/', [ProjectController::class, 'store'])->name('store');
    Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
    Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
    Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
    Route::patch('/{project}/type', [ProjectController::class, 'updateType'])->name('type');
    Route::post('/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('duplicate');
    Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');

    // Sprints
    Route::post('/{project}/sprints', [SprintController::class, 'store'])->name('sprints.store');
    Route::patch('/{project}/sprints/reorder', [SprintController::class, 'reorder'])->name('sprints.reorder');
    Route::put('/{project}/sprints/{sprint}', [SprintController::class, 'update'])->name('sprints.update');
    Route::delete('/{project}/sprints/{sprint}', [SprintController::class, 'destroy'])->name('sprints.destroy');
    Route::post('/{project}/email/daily-summary', [EmailNotificationController::class, 'dailySummary'])->name('email.daily-summary');
    Route::post('/{project}/sprints/{sprint}/email/summary', [EmailNotificationController::class, 'sprintSummary'])->name('email.sprint-summary');

    // Tasks
    Route::get('/{project}/tasks/{task}', [TaskController::class, 'show'])->name('tasks.show');
    Route::post('/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
    Route::post('/{project}/tasks/bulk', [TaskController::class, 'bulkStore'])->name('tasks.bulk');
    Route::post('/{project}/tasks/import', [TaskController::class, 'import'])->name('tasks.import');
    Route::post('/{project}/tasks/{task}/subtasks', [TaskController::class, 'storeSubtask'])->name('tasks.subtasks.store');
    Route::put('/{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
    Route::patch('/{project}/tasks/{task}', [TaskController::class, 'updateStatus'])->name('tasks.status');
    Route::delete('/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');
    Route::post('/{project}/tasks/{task}/watchers/toggle', [TaskWatcherController::class, 'toggle'])->name('tasks.watchers.toggle');
    Route::post('/{project}/tasks/{task}/attachments', [TaskAttachmentController::class, 'store'])->name('tasks.attachments.store');
    Route::delete('/{project}/tasks/{task}/attachments/{attachment}', [TaskAttachmentController::class, 'destroy'])->name('tasks.attachments.destroy');

    // Weekly Reports (Báo cáo tuần — Executive Dashboard)
    Route::post('/{project}/weekly-reports', [WeeklyReportController::class, 'store'])->name('weekly-reports.store');
    Route::put('/{project}/weekly-reports/{weeklyReport}', [WeeklyReportController::class, 'update'])->name('weekly-reports.update');
    Route::post('/{project}/weekly-reports/{weeklyReport}/generate', [WeeklyReportController::class, 'generate'])->name('weekly-reports.generate');
    Route::post('/{project}/weekly-reports/{weeklyReport}/regenerate', [WeeklyReportController::class, 'regenerate'])->name('weekly-reports.regenerate');
    Route::post('/{project}/weekly-reports/{weeklyReport}/submit', [WeeklyReportController::class, 'submit'])->name('weekly-reports.submit');
    Route::post('/{project}/weekly-reports/{weeklyReport}/approve', [WeeklyReportController::class, 'approve'])->name('weekly-reports.approve');
    Route::post('/{project}/weekly-reports/{weeklyReport}/reject', [WeeklyReportController::class, 'reject'])->name('weekly-reports.reject');
    Route::get('/{project}/weekly-reports/{weeklyReport}/export/pdf', [WeeklyReportController::class, 'exportPdf'])->name('weekly-reports.export.pdf');
    Route::get('/{project}/weekly-reports/{weeklyReport}/export/docx', [WeeklyReportController::class, 'exportDocx'])->name('weekly-reports.export.docx');

    // Epics
    Route::post('/{project}/epics', [EpicController::class, 'store'])->name('epics.store');

    // Worklogs (time + cost)
    Route::post('/{project}/tasks/{task}/worklogs', [WorklogController::class, 'store'])->name('worklogs.store');
    Route::delete('/{project}/tasks/{task}/worklogs/{worklog}', [WorklogController::class, 'destroy'])->name('worklogs.destroy');

    // Project documents / attachments
    Route::get('/{project}/attachments/{attachment}/file', [ProjectAttachmentController::class, 'file'])->name('attachments.file');
    Route::post('/{project}/attachments', [ProjectAttachmentController::class, 'store'])->name('attachments.store');
    Route::put('/{project}/attachments/{attachment}', [ProjectAttachmentController::class, 'update'])->name('attachments.update');
    Route::delete('/{project}/attachments/{attachment}', [ProjectAttachmentController::class, 'destroy'])->name('attachments.destroy');

    // Members + rates
    Route::post('/{project}/members', [ProjectMemberController::class, 'store'])->name('members.store');
    Route::put('/{project}/members/{employee}', [ProjectMemberController::class, 'update'])->name('members.update');
    Route::delete('/{project}/members/{employee}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');
});
