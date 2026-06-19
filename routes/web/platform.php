<?php

use App\Http\Controllers\Audit\AuditLogController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationManagementController;
use App\Http\Controllers\Onboarding\OnboardingController;
use App\Http\Controllers\Performance\PerformanceAuditController;
use App\Http\Controllers\Performance\PerformanceDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Platform-wide features — performance, onboarding, notifications, audit.
|--------------------------------------------------------------------------
*/

// Performance Analytics & Work Audit — management view (gated by Gate::performance.view).
Route::prefix('performance')->name('performance.')->group(function () {
    Route::get('/', PerformanceDashboardController::class)->name('index');
    Route::get('/audit', [PerformanceAuditController::class, 'index'])->name('audit');
    Route::get('/audit/{employee}', [PerformanceAuditController::class, 'show'])->name('audit.show');
});

// Onboarding & Interactive Tour — progress tracking (content is client-side).
Route::prefix('onboarding')->name('onboarding.')->group(function () {
    Route::get('/', [OnboardingController::class, 'index'])->name('index');
    Route::post('/progress', [OnboardingController::class, 'progress'])->name('progress');
    Route::post('/complete', [OnboardingController::class, 'complete'])->name('complete');
    Route::post('/skip', [OnboardingController::class, 'skip'])->name('skip');
    Route::post('/reset', [OnboardingController::class, 'reset'])->name('reset');
    Route::post('/dismiss-welcome', [OnboardingController::class, 'dismissWelcome'])->name('dismiss-welcome');
});

// Notifications — `/` is the full Inertia inbox page; `/list` is the JSON
// feed consumed by the bell drawer + inbox page composable.
Route::prefix('notifications')->name('notifications.')->group(function () {
    Route::get('/', [NotificationController::class, 'page'])->name('index');
    Route::get('/list', [NotificationController::class, 'index'])->name('list');
    Route::get('/unread-count', [NotificationController::class, 'unreadCount'])->name('unread-count');
    Route::get('/preferences', [NotificationController::class, 'preferences'])->name('preferences');
    Route::put('/preferences', [NotificationController::class, 'updatePreferences'])->name('preferences.update');
    Route::get('/actors', [NotificationController::class, 'actors'])->name('actors');
    Route::get('/manage', NotificationManagementController::class)->name('manage');
    Route::post('/read-all', [NotificationController::class, 'markAllRead'])->name('read-all');
    Route::post('/bulk', [NotificationController::class, 'bulk'])->name('bulk');
    Route::post('/{notification}/read', [NotificationController::class, 'markRead'])->name('read');
    Route::post('/{notification}/acknowledge', [NotificationController::class, 'acknowledge'])->name('acknowledge');
    Route::post('/{notification}/assign', [NotificationController::class, 'assign'])->name('assign');
});

// Unified audit trail viewer (admin/super — gated by audit.view).
Route::get('/audit', AuditLogController::class)->name('audit.index');
