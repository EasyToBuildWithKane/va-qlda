<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Blocker\BlockerController;
use App\Http\Controllers\Bug\BugController;
use App\Http\Controllers\Comment\CommentController;
use App\Http\Controllers\DailyReport\DailyReportController;
use App\Http\Controllers\Department\DepartmentController;
use App\Http\Controllers\DailyReport\DailyReportReviewController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Feedback\FeedbackController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectMemberController;
use App\Http\Controllers\Project\SprintController;
use App\Http\Controllers\Project\TaskController;
use App\Http\Controllers\Project\WorklogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Inertia)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Daily Report — static segments before /{report} to avoid capture.
    Route::prefix('daily-reports')->name('daily-reports.')->group(function () {
        Route::get('/', [DailyReportController::class, 'index'])->name('index');
        Route::get('/today', [DailyReportController::class, 'today'])->name('today');
        Route::get('/review', [DailyReportReviewController::class, 'index'])->name('review');
        Route::post('/', [DailyReportController::class, 'store'])->name('store');
        Route::get('/{report}', [DailyReportController::class, 'show'])->name('show');
        Route::put('/{report}', [DailyReportController::class, 'update'])->name('update');
        Route::post('/{report}/submit', [DailyReportController::class, 'submit'])->name('submit');
        Route::post('/{report}/score', [DailyReportReviewController::class, 'score'])->name('score');
        Route::post('/{report}/reject', [DailyReportReviewController::class, 'reject'])->name('reject');
    });

    // Projects + timeline / sprints / tasks / worklogs / members (static before /{project}).
    Route::prefix('projects')->name('projects.')->group(function () {
        Route::get('/', [ProjectController::class, 'index'])->name('index');
        Route::get('/create', [ProjectController::class, 'create'])->name('create');
        Route::post('/', [ProjectController::class, 'store'])->name('store');
        Route::get('/{project}', [ProjectController::class, 'show'])->name('show');
        Route::get('/{project}/edit', [ProjectController::class, 'edit'])->name('edit');
        Route::put('/{project}', [ProjectController::class, 'update'])->name('update');
        Route::patch('/{project}/type', [ProjectController::class, 'updateType'])->name('type');
        Route::patch('/{project}/department', [ProjectController::class, 'updateDepartment'])->name('department');
        Route::post('/{project}/duplicate', [ProjectController::class, 'duplicate'])->name('duplicate');
        Route::delete('/{project}', [ProjectController::class, 'destroy'])->name('destroy');

        // Sprints
        Route::post('/{project}/sprints', [SprintController::class, 'store'])->name('sprints.store');
        Route::put('/{project}/sprints/{sprint}', [SprintController::class, 'update'])->name('sprints.update');
        Route::delete('/{project}/sprints/{sprint}', [SprintController::class, 'destroy'])->name('sprints.destroy');

        // Tasks
        Route::post('/{project}/tasks', [TaskController::class, 'store'])->name('tasks.store');
        Route::put('/{project}/tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::patch('/{project}/tasks/{task}', [TaskController::class, 'updateStatus'])->name('tasks.status');
        Route::delete('/{project}/tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

        // Worklogs (time + cost)
        Route::post('/{project}/tasks/{task}/worklogs', [WorklogController::class, 'store'])->name('worklogs.store');
        Route::delete('/{project}/tasks/{task}/worklogs/{worklog}', [WorklogController::class, 'destroy'])->name('worklogs.destroy');

        // Members + rates
        Route::post('/{project}/members', [ProjectMemberController::class, 'store'])->name('members.store');
        Route::put('/{project}/members/{employee}', [ProjectMemberController::class, 'update'])->name('members.update');
        Route::delete('/{project}/members/{employee}', [ProjectMemberController::class, 'destroy'])->name('members.destroy');
    });

    // Blockers (Vướng mắc) — cross-project tracker.
    Route::prefix('blockers')->name('blockers.')->group(function () {
        Route::get('/', [BlockerController::class, 'index'])->name('index');
        Route::post('/', [BlockerController::class, 'store'])->name('store');
        Route::put('/{blocker}', [BlockerController::class, 'update'])->name('update');
        Route::delete('/{blocker}', [BlockerController::class, 'destroy'])->name('destroy');
    });

    // Bug tracker
    Route::prefix('bugs')->name('bugs.')->group(function () {
        Route::get('/', [BugController::class, 'index'])->name('index');
        Route::post('/', [BugController::class, 'store'])->name('store');
        Route::get('/{bug}', [BugController::class, 'show'])->name('show');
        Route::put('/{bug}', [BugController::class, 'update'])->name('update');
        Route::delete('/{bug}', [BugController::class, 'destroy'])->name('destroy');
    });

    // Feedback tracker
    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [FeedbackController::class, 'index'])->name('index');
        Route::post('/', [FeedbackController::class, 'store'])->name('store');
        Route::get('/{feedback}', [FeedbackController::class, 'show'])->name('show');
        Route::put('/{feedback}', [FeedbackController::class, 'update'])->name('update');
        Route::delete('/{feedback}', [FeedbackController::class, 'destroy'])->name('destroy');
    });

    // Departments (phòng ban) — owns projects.
    Route::prefix('departments')->name('departments.')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::put('/{department}', [DepartmentController::class, 'update'])->name('update');
        Route::patch('/{department}/toggle', [DepartmentController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('destroy');
    });

    // Polymorphic comments (bug/feedback/blocker/task threads)
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
});
