<?php

namespace App\Http\Controllers\Blocker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blocker\BulkStoreBlockerRequest;
use App\Http\Requests\Blocker\ImportBlockerRequest;
use App\Http\Requests\Blocker\RecheckBlockerRequest;
use App\Http\Requests\Blocker\StoreBlockerRequest;
use App\Http\Requests\Blocker\UpdateBlockerRequest;
use App\Http\Resources\BlockerResource;
use App\Models\Blocker;
use App\Services\NotificationService;
use App\Services\Telegram\BlockerResolvedTelegramNotifier;
use App\Support\BlockerActivityLogger;
use App\Support\BlockerRecheck;
use App\Support\Enums\BlockerRecheckResult;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Enums\NotificationType;
use App\Support\Enums\SystemRole;
use App\Support\EvidenceLinkPreview;
use App\Support\NotificationDispatcher;
use App\Support\Options;
use App\Support\ProjectActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class BlockerController extends Controller
{
    /** Chuẩn hoá giá trị so sánh / lưu lịch sử (enum, ngày, scalar). */
    private function trackValueToString(mixed $value): string
    {
        if ($value === null) {
            return '';
        }
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format('Y-m-d H:i:s');
        }
        if (is_array($value)) {
            return json_encode($value, JSON_UNESCAPED_UNICODE) ?: '';
        }

        return (string) $value;
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Blocker::class);

        $account = $request->user();

        $query = Blocker::query()
            ->with([
                'project',
                'task',
                'raisedBy',
                'owner',
                'recheckedBy',
                'attachments' => fn ($a) => $a->with('uploadedBy')->latest(),
                'comments' => fn ($c) => $c->whereNull('parent_id')->with(['author', 'replies.author'])->latest(),
            ])
            ->withCount('comments')
            ->orderByPriority();

        $status = $request->query('status');
        if ($status === 'all') {
            // no status scope
        } elseif ($status === 'active') {
            $query->open();
        } elseif ($status) {
            $query->where('status', $status);
        } else {
            $query->listDefault();
        }

        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }
        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }
        if ($raisedById = $request->query('raised_by_id')) {
            $query->where('raised_by_id', $raisedById);
        }
        if ($request->boolean('mine') && $account->employee_id) {
            $query->where('owner_id', $account->employee_id);
        }
        if ($request->boolean('overdue')) {
            $query->overdue();
        }
        if ($request->boolean('recheck_pending')) {
            $query->where('status', BlockerStatus::Resolved->value)
                ->where(function ($q) {
                    $q->whereNull('recheck_result')
                        ->orWhere('recheck_result', BlockerRecheckResult::Pending->value);
                });
        }
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('description', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('root_cause', 'like', "%{$search}%"));
        }

        $perPage = (int) $request->query('per_page', 10);
        if (! in_array($perPage, [5, 10, 15, 20], true)) {
            $perPage = 10;
        }

        return Inertia::render('Blocker/Index', [
            'blockers' => BlockerResource::collection($query->paginate($perPage)->withQueryString()),
            'filters' => (object) $request->only([
                'status', 'severity', 'project_id', 'owner_id', 'raised_by_id',
                'mine', 'overdue', 'recheck_pending', 'q', 'per_page',
            ]),
            'summary' => [
                'open' => Blocker::open()->count(),
                'critical' => Blocker::open()->where('severity', BlockerSeverity::Critical->value)->count(),
                'resolved' => Blocker::where('status', BlockerStatus::Resolved->value)->count(),
                'recheck_pending' => Blocker::query()
                    ->where('status', BlockerStatus::Resolved->value)
                    ->where('recheck_result', BlockerRecheckResult::Pending->value)
                    ->count(),
            ],
            'options' => [
                'projects' => Options::projects(),
                'employees' => Options::employees(),
                'severity' => BlockerSeverity::options(),
                'status' => BlockerStatus::options(),
            ],
            'can' => [
                'create' => $account->can('create', Blocker::class),
                'comment' => $account->role !== SystemRole::Viewer,
            ],
        ]);
    }

    public function evidenceLinkPreview(Request $request): JsonResponse
    {
        $this->authorize('viewAny', Blocker::class);

        $validated = $request->validate([
            'url' => ['required', 'string', 'url', 'max:2048'],
        ]);

        return response()->json([
            'image_url' => EvidenceLinkPreview::resolveImageUrl($validated['url']),
        ]);
    }

    public function store(StoreBlockerRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $blocker = Blocker::create([
            ...$data,
            'status' => $data['status'] ?? BlockerStatus::Open->value,
            'raised_by_id' => $request->user()->employee_id,
            'raised_at' => now(),
        ]);

        BlockerActivityLogger::created($blocker, $request->user());
        NotificationDispatcher::blockerCreated($blocker, $request->user());

        return back()->with([
            'success' => 'Đã ghi nhận test case.',
            'created_blocker_id' => $blocker->id,
        ]);
    }

    public function bulkStore(BulkStoreBlockerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $defaults = $data['defaults'] ?? [];
        $account = $request->user();
        $created = 0;
        $createdIds = [];

        DB::transaction(function () use ($data, $defaults, $account, &$created, &$createdIds) {
            foreach ($data['rows'] as $row) {
                $status = $defaults['status'] ?? BlockerStatus::Open->value;
                $blocker = Blocker::create([
                    'project_id' => $defaults['project_id'] ?? null,
                    'title' => $row['title'],
                    'severity' => $defaults['severity'] ?? BlockerSeverity::Medium->value,
                    'status' => $status,
                    'owner_id' => $defaults['owner_id'] ?? null,
                    'due_date' => $defaults['due_date'] ?? null,
                    'raised_by_id' => $account->employee_id,
                    'raised_at' => now(),
                ]);

                BlockerActivityLogger::created($blocker, $account);
                NotificationDispatcher::blockerCreated($blocker, $account);
                $created++;
                $createdIds[] = $blocker->id;
            }
        });

        return back()->with([
            'success' => "Đã ghi nhận {$created} test case.",
            'created_blocker_ids' => $createdIds,
        ]);
    }

    public function import(ImportBlockerRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $account = $request->user();
        $created = 0;

        DB::transaction(function () use ($data, $account, &$created) {
            foreach ($data['rows'] as $row) {
                $status = $row['status'] ?? BlockerStatus::Open->value;
                $terminal = in_array($status, [
                    BlockerStatus::Resolved->value,
                    BlockerStatus::Closed->value,
                ], true);

                $blocker = Blocker::create([
                    'project_id' => $data['project_id'],
                    'title' => $row['title'],
                    'severity' => $row['severity'],
                    'status' => $status,
                    'owner_id' => $row['owner_id'] ?? null,
                    'due_date' => $row['due_date'] ?? null,
                    'root_cause' => $row['root_cause'] ?? null,
                    'resolution' => $row['resolution'] ?? null,
                    'description' => $row['description'] ?? null,
                    'raised_by_id' => $account->employee_id,
                    'raised_at' => now(),
                    'resolved_at' => $terminal ? now() : null,
                ]);

                BlockerActivityLogger::created($blocker, $account);
                $created++;
            }
        });

        app(NotificationService::class)->recordSystemEvent(
            $account,
            NotificationType::SystemImport,
            "Nhập {$created} test case từ Excel",
            null,
            null,
        );

        return back()->with('success', "Đã nhập {$created} test case từ file.");
    }

    public function update(
        UpdateBlockerRequest $request,
        Blocker $blocker,
        BlockerResolvedTelegramNotifier $blockerTelegram,
    ): RedirectResponse {
        $data = $request->validated();

        $trackFields = ['title', 'description', 'root_cause', 'resolution', 'evidence_links', 'severity', 'status', 'owner_id', 'due_date'];
        $before = $blocker->only($trackFields);
        $oldStatus = $blocker->status->value;

        if (array_key_exists('status', $data)) {
            $terminal = in_array($data['status'], [
                BlockerStatus::Resolved->value,
                BlockerStatus::Closed->value,
            ], true);
            $data['resolved_at'] = $terminal ? ($blocker->resolved_at ?? now()) : null;

            if ($data['status'] === BlockerStatus::Resolved->value) {
                $data['recheck_result'] = BlockerRecheckResult::Pending->value;
                $data['recheck_note'] = null;
                $data['rechecked_at'] = null;
                $data['rechecked_by_id'] = null;
            }
        }

        $blocker->update($data);
        $blocker->refresh();

        $account = $request->user();
        $statusChanged = isset($data['status']) && $data['status'] !== $oldStatus;
        if ($statusChanged) {
            BlockerActivityLogger::statusChanged($blocker, $oldStatus, $data['status'], $account);
        }

        $changes = [];
        foreach ($trackFields as $field) {
            if ($field === 'status' && $statusChanged) {
                continue;
            }
            if (! array_key_exists($field, $data)) {
                continue;
            }
            $newVal = $blocker->{$field};
            $oldVal = $before[$field] ?? null;
            if ($this->trackValueToString($newVal) !== $this->trackValueToString($oldVal)) {
                $changes[$field] = match (true) {
                    $newVal instanceof \BackedEnum => $newVal->value,
                    $newVal instanceof \DateTimeInterface => $newVal->format('Y-m-d'),
                    default => $newVal,
                };
            }
        }
        BlockerActivityLogger::updated($blocker, $account, $changes);

        $notifyChanges = $changes;
        if ($statusChanged) {
            $notifyChanges['status'] = $data['status'];
        }
        NotificationDispatcher::blockerUpdated($blocker, $account, $notifyChanges);

        if ($statusChanged && isset($data['status'])) {
            $blockerTelegram->notifyStatusChanged(
                $blocker,
                $account,
                $oldStatus,
                $data['status'],
            );
        }

        return back()->with('success', 'Đã cập nhật test case.');
    }

    public function recheck(
        RecheckBlockerRequest $request,
        Blocker $blocker,
        BlockerResolvedTelegramNotifier $blockerTelegram,
    ): RedirectResponse {
        $result = BlockerRecheckResult::from($request->validated('result'));
        $note = $request->validated('note');
        $account = $request->user();

        $transition = BlockerRecheck::apply($blocker, $result, $account, filled($note) ? trim((string) $note) : null);
        $blocker->refresh();

        BlockerActivityLogger::recheckApplied($blocker, $result->value, $blocker->recheck_note, $account);
        BlockerActivityLogger::statusChanged(
            $blocker,
            $transition['old_status'],
            $transition['new_status'],
            $account,
        );

        NotificationDispatcher::blockerUpdated($blocker, $account, ['status' => $transition['new_status']]);

        $blockerTelegram->notifyStatusChanged(
            $blocker,
            $account,
            $transition['old_status'],
            $transition['new_status'],
            $result === BlockerRecheckResult::Failed ? $blocker->recheck_note : null,
        );

        $message = $result === BlockerRecheckResult::Passed
            ? 'Đã xác nhận xử lý đúng — test case được đóng.'
            : 'Đã trả về người xử lý — trạng thái chuyển sang đang xử lý.';

        return back()->with('success', $message);
    }

    public function destroy(Blocker $blocker): RedirectResponse
    {
        $this->authorize('delete', $blocker);

        $user = request()->user();
        if ($blocker->project_id) {
            $blocker->loadMissing('project');
            if ($blocker->project) {
                ProjectActivityLogger::blockerRemoved($blocker->project, $blocker, $user);
            }
        }
        $blocker->delete();

        return back()->with('success', 'Đã xoá test case.');
    }

    public function bulk(Request $request, BlockerResolvedTelegramNotifier $blockerTelegram): RedirectResponse
    {
        $data = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer', 'exists:blockers,id'],
            'action' => ['required', 'in:status,assignee,delete'],
            'status' => ['nullable', Rule::in(BlockerStatus::values())],
            'owner_id' => ['nullable', 'integer', 'exists:employees,id'],
        ]);

        $blockers = Blocker::query()->whereIn('id', $data['ids'])->get();

        foreach ($blockers as $blocker) {
            match ($data['action']) {
                'status', 'assignee' => $this->authorize('update', $blocker),
                'delete' => $this->authorize('delete', $blocker),
                default => abort(400),
            };
        }

        $account = $request->user();

        if ($data['action'] === 'delete') {
            foreach ($blockers as $blocker) {
                $blocker->loadMissing('project');
                if ($blocker->project) {
                    ProjectActivityLogger::blockerRemoved($blocker->project, $blocker, $account);
                }
            }
            Blocker::query()->whereIn('id', $data['ids'])->delete();

            return back()->with('success', 'Đã xoá '.count($data['ids']).' test case.');
        }

        $payload = [];
        if ($data['action'] === 'status' && isset($data['status'])) {
            $payload['status'] = $data['status'];
            $terminal = in_array($data['status'], [
                BlockerStatus::Resolved->value,
                BlockerStatus::Closed->value,
            ], true);
            if ($terminal) {
                $payload['resolved_at'] = now();
            }
            if ($data['status'] === BlockerStatus::Resolved->value) {
                $payload['recheck_result'] = BlockerRecheckResult::Pending->value;
                $payload['recheck_note'] = null;
                $payload['rechecked_at'] = null;
                $payload['rechecked_by_id'] = null;
            }
        }
        if ($data['action'] === 'assignee') {
            $payload['owner_id'] = $data['owner_id'] ?? null;
        }

        $statusTransitions = [];
        $statusUpdateIds = $data['ids'];
        if ($data['action'] === 'status' && isset($data['status'])) {
            $newStatus = $data['status'];
            $statusUpdateIds = [];
            foreach ($blockers as $blocker) {
                if ($blocker->status->isTerminal()) {
                    continue;
                }
                $oldStatus = $blocker->status->value;
                if ($oldStatus === $newStatus) {
                    continue;
                }
                $statusUpdateIds[] = $blocker->id;
                $statusTransitions[] = ['blocker' => $blocker, 'old' => $oldStatus, 'new' => $newStatus];
            }
        }

        if ($data['action'] === 'status' && $statusUpdateIds !== []) {
            Blocker::query()->whereIn('id', $statusUpdateIds)->update($payload);
        } elseif ($data['action'] !== 'status') {
            Blocker::query()->whereIn('id', $data['ids'])->update($payload);
        }

        foreach ($statusTransitions as $transition) {
            $blockerTelegram->notifyStatusChanged(
                $transition['blocker']->fresh(),
                $account,
                $transition['old'],
                $transition['new'],
            );
        }

        $bulkLabel = $data['action'] === 'status' ? 'Cập nhật hàng loạt trạng thái' : 'Cập nhật hàng loạt người xử lý';
        foreach ($blockers as $blocker) {
            BlockerActivityLogger::bulkUpdated($blocker->fresh(), $bulkLabel, $account);
        }

        return back()->with('success', 'Đã cập nhật '.count($data['ids']).' test case.');
    }
}
