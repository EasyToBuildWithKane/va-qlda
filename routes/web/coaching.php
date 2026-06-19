<?php

use App\Http\Controllers\Coaching\CoachingCourseController;
use App\Http\Controllers\Coaching\CoachingDashboardController;
use App\Http\Controllers\Coaching\CoachingSessionController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Coaching / Mentoring.
|--------------------------------------------------------------------------
*/

Route::prefix('coaching')->name('coaching.')->group(function () {
    Route::get('/', CoachingDashboardController::class)->name('dashboard');
    Route::get('/courses', [CoachingCourseController::class, 'index'])->name('courses.index');
    Route::get('/courses/create', [CoachingCourseController::class, 'create'])->name('courses.create');
    Route::post('/courses', [CoachingCourseController::class, 'store'])->name('courses.store');
    Route::get('/courses/{course}', [CoachingCourseController::class, 'show'])->name('courses.show');
    Route::get('/courses/{course}/edit', [CoachingCourseController::class, 'edit'])->name('courses.edit');
    Route::put('/courses/{course}', [CoachingCourseController::class, 'update'])->name('courses.update');
    Route::delete('/courses/{course}', [CoachingCourseController::class, 'destroy'])->name('courses.destroy');
    Route::post('/courses/{course}/sessions', [CoachingCourseController::class, 'storeSession'])->name('courses.sessions.store');
    Route::get('/sessions/schedule', [CoachingSessionController::class, 'schedule'])->name('sessions.schedule');
    Route::get('/sessions/calendar/feed', [CoachingSessionController::class, 'calendarFeed'])->name('sessions.calendar.feed');
    Route::post('/sessions/calendar', [CoachingSessionController::class, 'calendarStore'])->name('sessions.calendar.store');
    Route::get('/sessions', [CoachingSessionController::class, 'index'])->name('sessions.index');
    Route::get('/sessions/export', [CoachingSessionController::class, 'exportIndex'])->name('sessions.export');
    Route::get('/sessions/{session}', [CoachingSessionController::class, 'show'])->name('sessions.show');
    Route::patch('/sessions/{session}/calendar', [CoachingSessionController::class, 'calendarUpdate'])->name('sessions.calendar.update');
    Route::patch('/sessions/{session}', [CoachingSessionController::class, 'update'])->name('sessions.update');
    Route::delete('/sessions/{session}', [CoachingSessionController::class, 'destroy'])->name('sessions.destroy');
    Route::post('/sessions/{session}/materials', [CoachingSessionController::class, 'storeMaterial'])->name('sessions.materials.store');
    Route::post('/sessions/{session}/assignments', [CoachingSessionController::class, 'storeAssignment'])->name('sessions.assignments.store');
    Route::patch('/assignments/{assignment}', [CoachingSessionController::class, 'updateAssignment'])->name('assignments.update');
    Route::delete('/assignments/{assignment}', [CoachingSessionController::class, 'destroyAssignment'])->name('assignments.destroy');
    Route::post('/progress', [CoachingSessionController::class, 'upsertProgress'])->name('progress.upsert');
    Route::get('/materials/{material}/file', [CoachingSessionController::class, 'materialFile'])->name('materials.file');
});
