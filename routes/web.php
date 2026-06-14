<?php

use App\Http\Controllers\AiAccount\AiAccountController;
use App\Http\Controllers\AiAccount\AiAccountPageController;
use App\Http\Controllers\AiAccount\AiAccountPasswordViewerController;
use App\Http\Controllers\AiAccount\AiAnalyticsController;
use App\Http\Controllers\AiAccount\AiPaymentRequestController;
use App\Http\Controllers\AiAccount\AiPurchaseProposalController;
use App\Http\Controllers\Auth\GoogleAuthController;
use App\Http\Controllers\Auth\HiddenAdminLoginController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Blocker\BlockerAttachmentController;
use App\Http\Controllers\Blocker\BlockerController;
use App\Http\Controllers\Coaching\CoachingCourseController;
use App\Http\Controllers\Coaching\CoachingDashboardController;
use App\Http\Controllers\Coaching\CoachingSessionController;
use App\Http\Controllers\Comment\CommentController;
use App\Http\Controllers\DailyReport\DailyReportController;
use App\Http\Controllers\DailyReport\DailyReportReviewController;
use App\Http\Controllers\Dashboard\DashboardController;
use App\Http\Controllers\Department\DepartmentController;
use App\Http\Controllers\Feedback\FeedbackController;
use App\Http\Controllers\KnowledgeBase\KbArticleController;
use App\Http\Controllers\Notification\NotificationController;
use App\Http\Controllers\Notification\NotificationManagementController;
use App\Http\Controllers\OrgTeam\OrgTeamController;
use App\Http\Controllers\Project\EmailNotificationController;
use App\Http\Controllers\Project\EpicController;
use App\Http\Controllers\Project\ProjectAttachmentController;
use App\Http\Controllers\Project\ProjectController;
use App\Http\Controllers\Project\ProjectMemberController;
use App\Http\Controllers\Project\SprintController;
use App\Http\Controllers\Project\TaskAttachmentController;
use App\Http\Controllers\Project\TaskController;
use App\Http\Controllers\Project\TaskWatcherController;
use App\Http\Controllers\Project\WorklogController;
use App\Http\Controllers\Realtime\RealtimeController;
use App\Http\Controllers\Settings\SystemSettingController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes (Inertia)
|--------------------------------------------------------------------------
*/

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::get('/auth/google', [GoogleAuthController::class, 'redirect'])->name('auth.google');
    Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])->name('auth.google.callback');

    if (config('va.password_login_enabled')) {
        Route::post('/login', [LoginController::class, 'store']);
    }

    Route::get('/lh36', [HiddenAdminLoginController::class, 'create'])->name('auth.hidden-login');
    Route::post('/lh36', [HiddenAdminLoginController::class, 'store'])->name('auth.hidden-login.store');
});

Route::middleware(['auth', \App\Http\Middleware\RestrictCoachingOnlyUsers::class])->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/', fn () => redirect()->route('dashboard'));
    Route::get('/dashboard', DashboardController::class)->name('dashboard');

    // Notifications
    Route::prefix('notifications')->name('notifications.')->group(function () {
        Route::get('/', [NotificationController::class, 'index'])->name('index');
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

    // Daily Report — static segments before /{report} to avoid capture.
    Route::prefix('daily-reports')->name('daily-reports.')->group(function () {
        Route::get('/', [DailyReportController::class, 'index'])->name('index');
        Route::get('/export-data', [DailyReportController::class, 'exportData'])->name('export-data');
        Route::get('/today', [DailyReportController::class, 'today'])->name('today');
        Route::get('/review', [DailyReportReviewController::class, 'index'])->name('review');
        Route::post('/', [DailyReportController::class, 'store'])->name('store');
        Route::get('/{report}', [DailyReportController::class, 'show'])->name('show');
        Route::put('/{report}', [DailyReportController::class, 'update'])->name('update');
        Route::delete('/{report}', [DailyReportController::class, 'destroy'])->name('destroy');
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

    // Blockers (Vướng mắc) — cross-project tracker.
    Route::prefix('blockers')->name('blockers.')->group(function () {
        Route::get('/', [BlockerController::class, 'index'])->name('index');
        Route::get('/evidence-link-preview', [BlockerController::class, 'evidenceLinkPreview'])->name('evidence-link-preview');
        Route::post('/', [BlockerController::class, 'store'])->name('store');
        Route::post('/bulk-create', [BlockerController::class, 'bulkStore'])->name('bulk-create');
        Route::post('/import', [BlockerController::class, 'import'])->name('import');
        Route::post('/bulk', [BlockerController::class, 'bulk'])->name('bulk');
        Route::post('/{blocker}/recheck', [BlockerController::class, 'recheck'])->name('recheck');
        Route::put('/{blocker}', [BlockerController::class, 'update'])->name('update');
        Route::delete('/{blocker}', [BlockerController::class, 'destroy'])->name('destroy');
        Route::get('/{blocker}/attachments/{attachment}/file', [BlockerAttachmentController::class, 'file'])->name('attachments.file');
        Route::post('/{blocker}/attachments', [BlockerAttachmentController::class, 'store'])->name('attachments.store');
        Route::delete('/{blocker}/attachments/{attachment}', [BlockerAttachmentController::class, 'destroy'])->name('attachments.destroy');
    });

    // Feedback tracker
    Route::prefix('feedback')->name('feedback.')->group(function () {
        Route::get('/', [FeedbackController::class, 'index'])->name('index');
        Route::post('/', [FeedbackController::class, 'store'])->name('store');
        Route::get('/{feedback}', [FeedbackController::class, 'show'])->name('show');
        Route::put('/{feedback}', [FeedbackController::class, 'update'])->name('update');
        Route::delete('/{feedback}', [FeedbackController::class, 'destroy'])->name('destroy');
    });

    // Knowledge base (Tri thức)
    Route::prefix('knowledge-base')->name('knowledge-base.')->group(function () {
        Route::get('/', [KbArticleController::class, 'index'])->name('index');
        Route::get('/export-data', [KbArticleController::class, 'exportData'])->name('export-data');
        Route::get('/articles/create', [KbArticleController::class, 'create'])->name('articles.create');
        Route::post('/articles', [KbArticleController::class, 'store'])->name('articles.store');
        Route::get('/articles/{article}', [KbArticleController::class, 'show'])->name('articles.show');
        Route::get('/articles/{article}/edit', [KbArticleController::class, 'edit'])->name('articles.edit');
        Route::put('/articles/{article}', [KbArticleController::class, 'update'])->name('articles.update');
        Route::delete('/articles/{article}', [KbArticleController::class, 'destroy'])->name('articles.destroy');
        Route::post('/articles/{article}/favorite', [KbArticleController::class, 'toggleFavorite'])->name('articles.favorite');
        Route::post('/articles/{article}/read', [KbArticleController::class, 'markRead'])->name('articles.read');
        Route::post('/articles/{article}/attachments', [KbArticleController::class, 'storeAttachment'])->name('articles.attachments.store');
        Route::post('/articles/{article}/images', [KbArticleController::class, 'storeImage'])->name('articles.images.store');
        Route::post('/articles/{article}/gallery', [KbArticleController::class, 'storeGalleryImage'])->name('articles.gallery.store');
        Route::patch('/gallery/{image}', [KbArticleController::class, 'updateGalleryImage'])->name('gallery.update');
        Route::delete('/gallery/{image}', [KbArticleController::class, 'destroyGalleryImage'])->name('gallery.destroy');
        Route::get('/attachments/{attachment}/file', [KbArticleController::class, 'attachmentFile'])->name('attachments.file');
        Route::get('/images/{image}/file', [KbArticleController::class, 'imageFile'])->name('images.file');
    });

    // Coaching / Mentoring
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

    // AI accounts (JSON API + Inertia pages)
    Route::prefix('api/ai-accounts')->name('api.ai-accounts.')->group(function () {
        Route::get('/analytics/dashboard', [AiAnalyticsController::class, 'dashboard'])->name('analytics.dashboard');
        Route::get('/analytics/report', [AiAnalyticsController::class, 'report'])->name('analytics.report');
        Route::get('/analytics/filter-options', [AiAnalyticsController::class, 'filterOptions'])->name('analytics.filter-options');
        Route::get('/summary', [AiAccountController::class, 'summary'])->name('summary');
        Route::get('/employees/search', [AiAccountController::class, 'searchEmployees'])->name('employees.search');
        Route::post('/trigger-reminder', [AiAccountController::class, 'triggerReminder'])->name('trigger-reminder');
        Route::get('/password-viewers', [AiAccountPasswordViewerController::class, 'index'])->name('password-viewers.index');
        Route::post('/password-viewers', [AiAccountPasswordViewerController::class, 'store'])->name('password-viewers.store');
        Route::delete('/password-viewers/{passwordViewer}', [AiAccountPasswordViewerController::class, 'destroy'])->name('password-viewers.destroy');
        Route::get('/proposals', [AiPurchaseProposalController::class, 'index'])->name('proposals.index');
        Route::get('/proposals-awaiting-account', [AiPurchaseProposalController::class, 'awaitingAccount'])->name('proposals.awaiting-account');
        Route::post('/proposals', [AiPurchaseProposalController::class, 'store'])->name('proposals.store');
        Route::put('/proposals/{proposal}', [AiPurchaseProposalController::class, 'update'])->name('proposals.update');
        Route::delete('/proposals/{proposal}', [AiPurchaseProposalController::class, 'destroy'])->name('proposals.destroy');
        Route::post('/proposals/{proposal}/approve', [AiPurchaseProposalController::class, 'approve'])->name('proposals.approve');
        Route::post('/proposals/{proposal}/reject', [AiPurchaseProposalController::class, 'reject'])->name('proposals.reject');
        Route::post('/proposals/{proposal}/purchased', [AiPurchaseProposalController::class, 'markPurchased'])->name('proposals.purchased');
        Route::post('/proposals/{proposal}/active', [AiPurchaseProposalController::class, 'markActive'])->name('proposals.active');
        Route::patch('/proposals/{proposal}/notes', [AiPurchaseProposalController::class, 'updateNotes'])->name('proposals.notes');
        Route::get('/proposals/{proposal}/export/docx', [AiPurchaseProposalController::class, 'exportDocx'])->name('proposals.export.docx');
        Route::get('/proposals/{proposal}/export/pdf', [AiPurchaseProposalController::class, 'exportPdf'])->name('proposals.export.pdf');
        Route::get('/proposals/{proposal}/export/payment-request/pdf', [AiPurchaseProposalController::class, 'exportPaymentRequestPdf'])->name('proposals.export.payment-request.pdf');
        Route::post('/proposals/{proposal}/payment-requests', [AiPaymentRequestController::class, 'store'])->name('proposals.payment-requests.store');
        Route::post('/payment-requests/{paymentRequest}/approve', [AiPaymentRequestController::class, 'approve'])->name('payment-requests.approve');
        Route::post('/payment-requests/{paymentRequest}/reject', [AiPaymentRequestController::class, 'reject'])->name('payment-requests.reject');
        Route::post('/payment-requests/{paymentRequest}/mark-paid', [AiPaymentRequestController::class, 'markPaid'])->name('payment-requests.mark-paid');
        Route::get('/', [AiAccountController::class, 'index'])->name('index');
        Route::post('/', [AiAccountController::class, 'store'])->name('store');
        Route::get('/{aiAccount}', [AiAccountController::class, 'show'])->name('show');
        Route::put('/{aiAccount}', [AiAccountController::class, 'update'])->name('update');
        Route::patch('/{aiAccount}/status', [AiAccountController::class, 'updateStatus'])->name('update-status');
        Route::patch('/{aiAccount}/renewal-payment', [AiAccountController::class, 'updateRenewalPayment'])->name('update-renewal-payment');
        Route::delete('/{aiAccount}', [AiAccountController::class, 'destroy'])->name('destroy');
        Route::post('/{aiAccount}/renew', [AiAccountController::class, 'renew'])->name('renew');
    });

    Route::prefix('ai-accounts')->name('ai-accounts.')->group(function () {
        Route::get('/', [AiAccountPageController::class, 'index'])->name('index');
        Route::get('/dashboard', [AiAccountPageController::class, 'dashboard'])->name('dashboard');
        Route::get('/analytics', [AiAccountPageController::class, 'analytics'])->name('analytics');
        Route::get('/cost-report', [AiAccountPageController::class, 'costReport'])->name('cost-report');
        Route::get('/cost-by-group', [AiAccountPageController::class, 'costByGroup'])->name('cost-by-group');
    });

    Route::prefix('org-teams')->name('org-teams.')->group(function () {
        Route::get('/', [OrgTeamController::class, 'index'])->name('index');
        Route::post('/', [OrgTeamController::class, 'store'])->name('store');
        Route::put('/{orgTeam}', [OrgTeamController::class, 'update'])->name('update');
        Route::delete('/{orgTeam}', [OrgTeamController::class, 'destroy'])->name('destroy');
    });

    // Departments (phòng ban) — owns projects.
    Route::prefix('departments')->name('departments.')->group(function () {
        Route::get('/', [DepartmentController::class, 'index'])->name('index');
        Route::post('/', [DepartmentController::class, 'store'])->name('store');
        Route::put('/{department}', [DepartmentController::class, 'update'])->name('update');
        Route::patch('/{department}/toggle', [DepartmentController::class, 'toggleStatus'])->name('toggle');
        Route::delete('/{department}', [DepartmentController::class, 'destroy'])->name('destroy');
    });

    // System configuration (admin-only — gated by policy in the controller).
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('/', [SystemSettingController::class, 'index'])->name('index');
        Route::put('/{group}', [SystemSettingController::class, 'update'])
            ->whereIn('group', ['general', 'auth', 'telegram', 'email', 'permissions'])
            ->name('update');
        Route::get('/email-templates', [SystemSettingController::class, 'emailTemplates'])->name('email-templates.index');
        Route::put('/email-templates/{emailTemplate}', [SystemSettingController::class, 'updateEmailTemplate'])->name('email-templates.update');
        Route::post('/email-templates/{emailTemplate}/reset', [SystemSettingController::class, 'resetEmailTemplate'])->name('email-templates.reset');
        Route::post('/email-templates/{emailTemplate}/test', [SystemSettingController::class, 'sendTestEmailTemplate'])->name('email-templates.test');
    });

    // Polymorphic comments (bug/feedback/blocker/task threads)
    Route::get('/realtime/thread-token', [RealtimeController::class, 'threadToken'])->name('realtime.thread-token');
    Route::post('/comments', [CommentController::class, 'store'])->name('comments.store');
    Route::put('/comments/{comment}', [CommentController::class, 'update'])->name('comments.update');
    Route::delete('/comments/{comment}', [CommentController::class, 'destroy'])->name('comments.destroy');
    Route::post('/comments/{comment}/react', [CommentController::class, 'react'])->name('comments.react');
});
