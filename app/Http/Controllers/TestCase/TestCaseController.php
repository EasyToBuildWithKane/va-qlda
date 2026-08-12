<?php

namespace App\Http\Controllers\TestCase;

use App\Http\Controllers\Controller;
use App\Http\Requests\TestCase\ExecuteTestCaseRequest;
use App\Http\Requests\TestCase\ImportTestCaseRequest;
use App\Http\Requests\TestCase\StoreTestCaseRequest;
use App\Http\Requests\TestCase\UpdateTestCaseRequest;
use App\Http\Resources\TestCaseResource;
use App\Models\Blocker;
use App\Models\TestCase;
use App\Models\TestCaseRun;
use App\Models\TestSuite;
use App\Services\NotificationService;
use App\Support\Enums\BlockerSeverity;
use App\Support\Enums\BlockerStatus;
use App\Support\Enums\NotificationType;
use App\Support\Enums\TestCasePriority;
use App\Support\Enums\TestCaseRunResult;
use App\Support\Enums\TestCaseStatus;
use App\Support\Options;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class TestCaseController extends Controller
{
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', TestCase::class);

        $account = $request->user();

        $query = TestCase::query()
            ->with(['suite', 'owner', 'lastRunBy', 'task'])
            ->orderBy('project_id')
            ->orderBy('suite_id')
            ->orderBy('id');

        if ($projectId = $request->query('project_id')) {
            $query->where('project_id', $projectId);
        }
        if ($suiteId = $request->query('suite_id')) {
            $query->where('suite_id', $suiteId);
        }
        if ($status = $request->query('status')) {
            $query->where('status', $status);
        }
        if ($priority = $request->query('priority')) {
            $query->where('priority', $priority);
        }
        if ($lastResult = $request->query('last_result')) {
            if ($lastResult === 'not_run') {
                $query->whereNull('last_result');
            } else {
                $query->where('last_result', $lastResult);
            }
        }
        if ($ownerId = $request->query('owner_id')) {
            $query->where('owner_id', $ownerId);
        }
        if ($search = $request->query('q')) {
            $query->where(fn ($q) => $q
                ->where('title', 'like', "%{$search}%")
                ->orWhere('code', 'like', "%{$search}%")
                ->orWhere('preconditions', 'like', "%{$search}%"));
        }

        $perPage = (int) $request->query('per_page', 15);
        if (! in_array($perPage, [10, 15, 20, 50], true)) {
            $perPage = 15;
        }

        $totalQuery = TestCase::query();
        if ($projectId = $request->query('project_id')) {
            $totalQuery->where('project_id', $projectId);
        }

        $summary = [
            'total' => (clone $totalQuery)->count(),
            'ready' => (clone $totalQuery)->where('status', TestCaseStatus::Ready->value)->count(),
            'pass' => (clone $totalQuery)->where('last_result', TestCaseRunResult::Pass->value)->count(),
            'fail' => (clone $totalQuery)->where('last_result', TestCaseRunResult::Fail->value)->count(),
            'not_run' => (clone $totalQuery)->whereNull('last_result')->count(),
        ];

        return Inertia::render('TestCase/Index', [
            'testCases' => TestCaseResource::collection($query->paginate($perPage)->withQueryString()),
            'filters' => (object) $request->only([
                'status', 'priority', 'last_result', 'project_id', 'suite_id', 'owner_id', 'q', 'per_page',
            ]),
            'summary' => $summary,
            'options' => [
                'projects' => Options::projects(),
                'employees' => Options::employees(),
                'status' => TestCaseStatus::options(),
                'priority' => TestCasePriority::options(),
                'runResult' => TestCaseRunResult::options(),
            ],
            'can' => [
                'create' => $account->can('create', TestCase::class),
            ],
        ]);
    }

    public function store(StoreTestCaseRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $testCase = TestCase::create([
            ...$data,
            'status' => $data['status'] ?? TestCaseStatus::Draft->value,
        ]);

        return back()->with([
            'success' => 'Đã thêm test case.',
            'created_test_case_id' => $testCase->id,
        ]);
    }

    public function update(UpdateTestCaseRequest $request, TestCase $testCase): RedirectResponse
    {
        $testCase->update($request->validated());

        return back()->with('success', 'Đã cập nhật test case.');
    }

    public function destroy(TestCase $testCase): RedirectResponse
    {
        $this->authorize('delete', $testCase);

        $testCase->delete();

        return back()->with('success', 'Đã xoá test case.');
    }

    public function import(ImportTestCaseRequest $request): RedirectResponse
    {
        $data = $request->validated();
        $account = $request->user();
        $created = 0;

        DB::transaction(function () use ($data, &$created) {
            foreach ($data['rows'] as $row) {
                TestCase::create([
                    'project_id' => $data['project_id'],
                    'title' => $row['title'],
                    'priority' => $row['priority'],
                    'status' => $row['status'] ?? TestCaseStatus::Draft->value,
                    'suite_id' => $row['suite_id'] ?? null,
                    'owner_id' => $row['owner_id'] ?? null,
                    'preconditions' => $row['preconditions'] ?? null,
                    'expected_result' => $row['expected_result'] ?? null,
                ]);
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

    public function execute(ExecuteTestCaseRequest $request, TestCase $testCase): RedirectResponse
    {
        $data = $request->validated();
        $account = $request->user();
        $result = TestCaseRunResult::from($data['result']);

        DB::transaction(function () use ($data, $testCase, $account, $result) {
            $blockerId = $testCase->blocker_id;

            if ($result === TestCaseRunResult::Fail && ! empty($data['create_blocker'])) {
                $blocker = Blocker::create([
                    'project_id' => $testCase->project_id,
                    'task_id' => $testCase->task_id,
                    'title' => $data['blocker_title'] ?? "Fail: {$testCase->code} — {$testCase->title}",
                    'description' => $data['actual_result'] ?? null,
                    'severity' => BlockerSeverity::Medium->value,
                    'status' => BlockerStatus::Open->value,
                    'raised_by_id' => $account->employee_id,
                    'raised_at' => now(),
                ]);
                $blockerId = $blocker->id;
                $testCase->update(['blocker_id' => $blockerId]);
            }

            TestCaseRun::create([
                'test_case_id' => $testCase->id,
                'result' => $result->value,
                'actual_result' => $data['actual_result'] ?? null,
                'note' => $data['note'] ?? null,
                'executed_by_id' => $account->employee_id,
                'executed_at' => now(),
                'blocker_id' => $blockerId,
            ]);

            $testCase->update([
                'last_result' => $result->value,
                'last_run_at' => now(),
                'last_run_by_id' => $account->employee_id,
                'last_actual_result' => $data['actual_result'] ?? null,
                'last_run_note' => $data['note'] ?? null,
            ]);
        });

        $label = $result->labelVi();

        return back()->with('success', "Đã ghi nhận kết quả thực thi: {$label}.");
    }

    public function suiteStore(Request $request): RedirectResponse
    {
        $this->authorize('create', TestCase::class);

        $data = $request->validate([
            'project_id' => ['required', 'integer', 'exists:projects,id'],
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ], [
            'name.required' => 'Tên bộ test không được để trống.',
        ]);

        $suite = TestSuite::create($data);

        return back()->with([
            'success' => 'Đã thêm bộ test.',
            'created_suite_id' => $suite->id,
        ]);
    }

    public function suiteUpdate(Request $request, TestSuite $suite): RedirectResponse
    {
        $this->authorize('create', TestCase::class);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'sort_order' => ['nullable', 'integer'],
        ], [
            'name.required' => 'Tên bộ test không được để trống.',
        ]);

        $suite->update($data);

        return back()->with('success', 'Đã cập nhật bộ test.');
    }

    public function suiteDestroy(Request $request, TestSuite $suite): RedirectResponse
    {
        abort_unless($request->user()->allows('testcase.delete'), 403);

        $suite->delete();

        return back()->with('success', 'Đã xoá bộ test.');
    }
}
