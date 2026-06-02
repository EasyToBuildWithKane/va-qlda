<?php

namespace App\Http\Controllers\Blocker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blocker\ImportBlockerRequest;
use App\Http\Requests\Blocker\StoreBlockerRequest;
use App\Http\Requests\Blocker\UpdateBlockerRequest;
use App\Http\Resources\BlockerResource;
use App\Models\Blocker;
use App\Support\BlockerActivityLogger;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Options;
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

        return (string) $value;
    }

    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Blocker::class);

        $account = $request->user();

        $query = Blocker::query()
            ->with(['project', 'task', 'raisedBy', 'owner'])
            ->withCount('comments')
            ->latest('raised_at');

        if ($status = $request->query('status')) {
            $query->where('status', $status);
        } else {
            $query->open(); // default: only unresolved
        }
        if ($severity = $request->query('severity')) {
            $query->where('severity', $severity);
        }
        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($request->boolean('mine') && $account->employee_id) {
            $query->where('owner_id', $account->employee_id);
        }

        return Inertia::render('Blocker/Index', [
            'blockers' => BlockerResource::collection($query->get()),
            'filters' => (object) $request->only(['status', 'severity', 'project_id', 'mine']),
            'summary' => [
                'open' => Blocker::open()->count(),
                'critical' => Blocker::open()->where('severity', BlockerSeverity::Critical->value)->count(),
                'resolved' => Blocker::where('status', BlockerStatus::Resolved->value)->count(),
            ],
            'options' => [
                'projects' => Options::projects(),
                'employees' => Options::employees(),
                'severity' => BlockerSeverity::options(),
                'status' => BlockerStatus::options(),
            ],
            'can' => ['create' => $account->can('create', Blocker::class)],
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

        return back()->with('success', 'Đã ghi nhận vướng mắc.');
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

        return back()->with('success', "Đã nhập {$created} vướng mắc từ file.");
    }

    public function update(UpdateBlockerRequest $request, Blocker $blocker): RedirectResponse
    {
        $data = $request->validated();

        $trackFields = ['title', 'description', 'root_cause', 'resolution', 'severity', 'status', 'owner_id', 'due_date'];
        $before = $blocker->only($trackFields);
        $oldStatus = $blocker->status->value;

        if (array_key_exists('status', $data)) {
            $terminal = in_array($data['status'], [
                BlockerStatus::Resolved->value,
                BlockerStatus::Closed->value,
            ], true);
            $data['resolved_at'] = $terminal ? ($blocker->resolved_at ?? now()) : null;
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

        return back()->with('success', 'Đã cập nhật vướng mắc.');
    }

    public function destroy(Blocker $blocker): RedirectResponse
    {
        $this->authorize('delete', $blocker);

        $blocker->delete();

        return back()->with('success', 'Đã xoá vướng mắc.');
    }

    public function bulk(Request $request): RedirectResponse
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
                'status' => $this->authorize('update', $blocker),
                'assignee' => $this->authorize('update', $blocker),
                'delete' => $this->authorize('delete', $blocker),
            };
        }

        if ($data['action'] === 'delete') {
            Blocker::query()->whereIn('id', $data['ids'])->delete();

            return back()->with('success', 'Đã xoá '.count($data['ids']).' vướng mắc.');
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
        }
        if ($data['action'] === 'assignee') {
            $payload['owner_id'] = $data['owner_id'] ?? null;
        }

        Blocker::query()->whereIn('id', $data['ids'])->update($payload);

        return back()->with('success', 'Đã cập nhật '.count($data['ids']).' vướng mắc.');
    }
}
