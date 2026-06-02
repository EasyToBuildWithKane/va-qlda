<?php

namespace App\Http\Controllers\Blocker;

use App\Http\Controllers\Controller;
use App\Http\Requests\Blocker\StoreBlockerRequest;
use App\Http\Requests\Blocker\UpdateBlockerRequest;
use App\Http\Resources\BlockerResource;
use App\Models\Blocker;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class BlockerController extends Controller
{
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

        Blocker::create([
            ...$data,
            'status' => $data['status'] ?? BlockerStatus::Open->value,
            'raised_by_id' => $request->user()->employee_id,
            'raised_at' => now(),
        ]);

        return back()->with('success', 'Đã ghi nhận vướng mắc.');
    }

    public function update(UpdateBlockerRequest $request, Blocker $blocker): RedirectResponse
    {
        $data = $request->validated();

        if (array_key_exists('status', $data)) {
            $resolved = $data['status'] === BlockerStatus::Resolved->value;
            $data['resolved_at'] = $resolved ? ($blocker->resolved_at ?? now()) : null;
        }

        $blocker->update($data);

        return back()->with('success', 'Đã cập nhật vướng mắc.');
    }

    public function destroy(Blocker $blocker): RedirectResponse
    {
        $this->authorize('delete', $blocker);

        $blocker->delete();

        return back()->with('success', 'Đã xoá vướng mắc.');
    }
}
