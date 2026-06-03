<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Support\Enums\NotificationPriority;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationManagementController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->allows('notifications.manage'), 403);

        $base = AppNotification::query()->where('is_admin_feed', true);

        $stats = [
            'total' => (clone $base)->count(),
            'unread' => (clone $base)->whereNull('read_at')->count(),
            'critical' => (clone $base)->where('priority', NotificationPriority::Critical)->count(),
            'warning' => (clone $base)->where('priority', NotificationPriority::High)->count(),
            'info' => (clone $base)->whereIn('priority', [
                NotificationPriority::Medium,
                NotificationPriority::Low,
            ])->count(),
        ];

        $trend = AppNotification::query()
            ->where('is_admin_feed', true)
            ->where('created_at', '>=', now()->subDays(14))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => $r->getAttribute('date'), 'count' => (int) $r->getAttribute('count')]);

        $byCategory = AppNotification::query()
            ->where('is_admin_feed', true)
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->get()
            ->mapWithKeys(fn ($r) => [$r->category->value => (int) $r->getAttribute('count')]);

        $recentErrors = AppNotification::query()
            ->where('is_admin_feed', true)
            ->whereIn('type', [
                'admin_api_error',
                'admin_job_failed',
                'admin_queue_failed',
                'admin_import_failed',
                'system_error',
            ])
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'title', 'body', 'priority', 'created_at']);

        $topActors = AppNotification::query()
            ->where('is_admin_feed', true)
            ->whereNotNull('actor_name')
            ->select('actor_name', DB::raw('COUNT(*) as count'))
            ->groupBy('actor_name')
            ->orderByDesc('count')
            ->limit(8)
            ->get();

        return Inertia::render('Notifications/Management', [
            'stats' => $stats,
            'trend' => $trend,
            'byCategory' => $byCategory,
            'recentErrors' => $recentErrors,
            'topActors' => $topActors,
        ]);
    }
}
