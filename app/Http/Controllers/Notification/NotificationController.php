<?php

namespace App\Http\Controllers\Notification;

use App\Http\Controllers\Controller;
use App\Http\Resources\AppNotificationResource;
use App\Models\AppNotification;
use App\Models\SystemAccount;
use App\Services\NotificationService;
use App\Support\Enums\NotificationCategory;
use App\Support\Enums\NotificationPriority;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class NotificationController extends Controller
{
    public function __construct(
        private readonly NotificationService $notifications,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $account = $request->user();
        $validated = $request->validate([
            'tab' => ['nullable', 'string', Rule::in(['all', 'unread', 'read'])],
            'category' => ['nullable', 'string', Rule::enum(NotificationCategory::class)],
            'priority' => ['nullable', 'string', Rule::enum(NotificationPriority::class)],
            'actor_account_id' => ['nullable', 'integer', 'exists:system_accounts,id'],
            'project_id' => ['nullable', 'integer', 'exists:projects,id'],
            'sprint_id' => ['nullable', 'integer'],
            'search' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'cursor' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:10', 'max:50'],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);
        $query = $this->notifications->queryForAccount($account)->with('project');

        $tab = $validated['tab'] ?? 'all';
        if ($tab === 'unread') {
            $query->whereNull('read_at');
        } elseif ($tab === 'read') {
            $query->whereNotNull('read_at');
        }

        if (! empty($validated['category'])) {
            $query->where('category', $validated['category']);
        }
        if (! empty($validated['priority'])) {
            $query->where('priority', $validated['priority']);
        }
        if (! empty($validated['actor_account_id'])) {
            $query->where('actor_account_id', $validated['actor_account_id']);
        }
        if (! empty($validated['project_id'])) {
            $query->where('project_id', $validated['project_id']);
        }
        if (! empty($validated['sprint_id'])) {
            $query->where('sprint_id', $validated['sprint_id']);
        }
        if (! empty($validated['search'])) {
            $term = '%'.$validated['search'].'%';
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', $term)
                    ->orWhere('body', 'like', $term)
                    ->orWhere('actor_name', 'like', $term);
            });
        }
        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }
        if (! empty($validated['cursor'])) {
            $query->where('id', '<', $validated['cursor']);
        }

        $items = $query->limit($perPage + 1)->get();
        $hasMore = $items->count() > $perPage;
        if ($hasMore) {
            $items = $items->take($perPage);
        }

        return response()->json([
            'data' => AppNotificationResource::collection($items),
            'meta' => [
                'has_more' => $hasMore,
                'next_cursor' => $hasMore ? $items->last()?->getKey() : null,
            ],
        ]);
    }

    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markRead(Request $request, AppNotification $notification): JsonResponse
    {
        $this->authorizeNotification($request->user(), $notification);

        if (! $notification->read_at) {
            $notification->update(['read_at' => now()]);
        }

        return response()->json([
            'notification' => new AppNotificationResource($notification->fresh()->load('project')),
            'unread_count' => $this->notifications->unreadCount($request->user()),
        ]);
    }

    public function markAllRead(Request $request): JsonResponse
    {
        $account = $request->user();

        AppNotification::query()
            ->where('recipient_account_id', $account->id)
            ->whereNull('read_at')
            ->update(['read_at' => now()]);

        return response()->json([
            'unread_count' => 0,
            'message' => 'Đã đánh dấu tất cả là đã đọc.',
        ]);
    }

    public function acknowledge(Request $request, AppNotification $notification): JsonResponse
    {
        $this->authorizeNotification($request->user(), $notification);

        $notification->update([
            'acknowledged_at' => now(),
            'read_at' => $notification->read_at ?? now(),
        ]);

        return response()->json([
            'notification' => new AppNotificationResource($notification->fresh()->load('project')),
        ]);
    }

    public function assign(Request $request, AppNotification $notification): JsonResponse
    {
        $this->authorizeNotification($request->user(), $notification);

        $data = $request->validate([
            'assigned_to_account_id' => ['required', 'integer', 'exists:system_accounts,id'],
        ]);

        $notification->update([
            'assigned_to_account_id' => $data['assigned_to_account_id'],
            'read_at' => $notification->read_at ?? now(),
        ]);

        return response()->json([
            'notification' => new AppNotificationResource($notification->fresh()->load('project')),
        ]);
    }

    public function bulk(Request $request): JsonResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:100'],
            'ids.*' => ['integer'],
            'action' => ['required', 'string', Rule::in(['read', 'acknowledge'])],
        ]);

        $account = $request->user();

        $query = AppNotification::query()
            ->where('recipient_account_id', $account->id)
            ->whereIn('id', $data['ids']);

        if ($data['action'] === 'read') {
            $query->whereNull('read_at')->update(['read_at' => now()]);
        } else {
            $query->update([
                'acknowledged_at' => now(),
                'read_at' => now(),
            ]);
        }

        return response()->json([
            'unread_count' => $this->notifications->unreadCount($account),
            'message' => 'Đã cập nhật '.count($data['ids']).' thông báo.',
        ]);
    }

    public function preferences(Request $request): JsonResponse
    {
        $pref = $this->notifications->preferencesFor($request->user());

        return response()->json([
            'preferences' => [
                'disabled_types' => $pref->disabled_types ?? [],
                'channel_in_app' => $pref->channel_in_app,
                'channel_email' => $pref->channel_email,
                'channel_push' => $pref->channel_push,
            ],
            'types' => collect(\App\Support\Enums\NotificationType::cases())->map(fn ($t) => [
                'value' => $t->value,
                'label' => $t->label(),
                'category' => $t->category()->value,
            ])->values(),
        ]);
    }

    public function updatePreferences(Request $request): JsonResponse
    {
        $data = $request->validate([
            'disabled_types' => ['nullable', 'array'],
            'disabled_types.*' => ['string', Rule::enum(\App\Support\Enums\NotificationType::class)],
            'channel_in_app' => ['boolean'],
            'channel_email' => ['boolean'],
            'channel_push' => ['boolean'],
        ]);

        $pref = $this->notifications->preferencesFor($request->user());
        $pref->update($data);

        return response()->json([
            'preferences' => [
                'disabled_types' => $pref->disabled_types ?? [],
                'channel_in_app' => $pref->channel_in_app,
                'channel_email' => $pref->channel_email,
                'channel_push' => $pref->channel_push,
            ],
            'message' => 'Đã lưu cài đặt thông báo.',
        ]);
    }

    public function actors(Request $request): JsonResponse
    {
        $ids = AppNotification::query()
            ->where('recipient_account_id', $request->user()->id)
            ->whereNotNull('actor_account_id')
            ->distinct()
            ->pluck('actor_account_id');

        $actors = SystemAccount::query()
            ->whereIn('id', $ids)
            ->orderBy('display_name')
            ->get(['id', 'display_name']);

        return response()->json(['data' => $actors]);
    }

    private function authorizeNotification(SystemAccount $account, AppNotification $notification): void
    {
        abort_unless(
            $notification->recipient_account_id === $account->id,
            403,
            'Không có quyền truy cập thông báo này.',
        );
    }
}
