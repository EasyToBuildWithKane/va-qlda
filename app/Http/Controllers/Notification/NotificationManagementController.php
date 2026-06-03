<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Models\AppNotification;
use App\Support\Enums\NotificationPriority;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class NotificationManagementController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->allows('notifications.manage'), 403);

        $now = now();
        $adminFeed = AppNotification::query()->where('is_admin_feed', true);

        $stats = [
            'total' => (clone $adminFeed)->count(),
            'unread' => (clone $adminFeed)->whereNull('read_at')->count(),
            'critical' => (clone $adminFeed)->where('priority', NotificationPriority::Critical)->count(),
            'high' => (clone $adminFeed)->where('priority', NotificationPriority::High)->count(),
            'today' => (clone $adminFeed)->whereDate('created_at', today())->count(),
            'unread_critical' => (clone $adminFeed)
                ->where('priority', NotificationPriority::Critical)
                ->whereNull('read_at')
                ->count(),
        ];

        $health = $this->buildOperationalHealth($now->copy()->subHours(24));

        $trend = AppNotification::query()
            ->where('is_admin_feed', true)
            ->where('created_at', '>=', $now->copy()->subDays(14))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => [
                'date' => $r->getAttribute('date'),
                'count' => (int) $r->getAttribute('count'),
            ]);

        $byCategory = AppNotification::query()
            ->where('is_admin_feed', true)
            ->select('category', DB::raw('COUNT(*) as count'))
            ->groupBy('category')
            ->orderByDesc('count')
            ->get()
            ->map(fn ($r) => [
                'category' => $r->category->value,
                'count' => (int) $r->getAttribute('count'),
            ]);

        $aggregatedFeed = $this->buildAggregatedFeed();

        $topActors = AppNotification::query()
            ->where('is_admin_feed', true)
            ->whereNotNull('actor_name')
            ->where('created_at', '>=', $now->copy()->subDays(7))
            ->select('actor_name', DB::raw('COUNT(*) as count'))
            ->groupBy('actor_name')
            ->orderByDesc('count')
            ->limit(8)
            ->get()
            ->map(fn ($r) => [
                'actor_name' => $r->actor_name,
                'count' => (int) $r->getAttribute('count'),
            ]);

        $recentErrors = AppNotification::query()
            ->where('is_admin_feed', true)
            ->whereIn('type', [
                'admin_api_error', 'admin_job_failed', 'admin_queue_failed',
                'admin_import_failed', 'system_error', 'admin_permission_error',
            ])
            ->orderByDesc('created_at')
            ->limit(20)
            ->get(['id', 'type', 'title', 'body', 'priority', 'read_at', 'created_at'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'type' => $r->type->value,
                'title' => $r->title,
                'body' => $r->body,
                'priority' => $r->priority->value,
                'is_read' => $r->read_at !== null,
                'created_at' => $r->created_at?->toISOString(),
            ]);

        return Inertia::render('Notifications/Management', [
            'stats' => $stats,
            'health' => $health,
            'trend' => $trend,
            'byCategory' => $byCategory,
            'aggregatedFeed' => $aggregatedFeed,
            'topActors' => $topActors,
            'recentErrors' => $recentErrors,
        ]);
    }

    private function buildOperationalHealth(Carbon $since): array
    {
        $checks = [
            'queue' => ['admin_queue_failed'],
            'jobs' => ['admin_job_failed'],
            'api' => ['admin_api_error'],
            'import' => ['admin_import_failed'],
            'security' => ['admin_permission_error'],
            'system' => ['system_error'],
        ];

        $result = [];
        foreach ($checks as $key => $types) {
            $count = AppNotification::query()
                ->where('is_admin_feed', true)
                ->whereIn('type', $types)
                ->where('created_at', '>=', $since)
                ->count();

            $result[$key] = [
                'count' => $count,
                'status' => match (true) {
                    $count === 0 => 'healthy',
                    $count <= 2 => 'warning',
                    default => 'critical',
                },
            ];
        }

        return $result;
    }

    private function buildAggregatedFeed(): array
    {
        $raw = AppNotification::query()
            ->where('is_admin_feed', true)
            ->where('created_at', '>=', now()->subDays(7))
            ->orderByDesc('created_at')
            ->limit(200)
            ->get(['id', 'type', 'actor_name', 'actor_account_id', 'category', 'title', 'action_url', 'priority', 'read_at', 'created_at', 'project_id']);

        $groups = [];
        foreach ($raw as $notif) {
            $bucket = (int) ($notif->created_at->timestamp / 1800);
            $actorKey = $notif->actor_name ?? '__system__';
            $key = "{$actorKey}|{$notif->category->value}|{$bucket}";

            if (! isset($groups[$key])) {
                $groups[$key] = [
                    'actor_name' => $notif->actor_name,
                    'actor_account_id' => $notif->actor_account_id,
                    'category' => $notif->category->value,
                    'latest_at' => $notif->created_at->toISOString(),
                    'items' => [],
                ];
            }

            $groups[$key]['items'][] = [
                'id' => $notif->id,
                'type' => $notif->type->value,
                'title' => $notif->title,
                'action_url' => $notif->action_url,
                'priority' => $notif->priority->value,
                'is_read' => $notif->read_at !== null,
                'project_id' => $notif->project_id,
                'created_at' => $notif->created_at->toISOString(),
            ];
        }

        $aggregated = array_values($groups);
        usort($aggregated, fn ($a, $b) => strcmp($b['latest_at'], $a['latest_at']));
        $aggregated = array_slice($aggregated, 0, 40);

        foreach ($aggregated as &$group) {
            $group['count'] = count($group['items']);
            $group['project_ids'] = array_values(array_unique(array_filter(array_column($group['items'], 'project_id'))));
        }
        unset($group);

        return $aggregated;
    }
}
