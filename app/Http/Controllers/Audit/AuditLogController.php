<?php

namespace App\Http\Controllers\Audit;

use App\Http\Controllers\Controller;
use App\Http\Resources\SecurityAuditLogResource;
use App\Models\SecurityAuditLog;
use App\Models\SystemAccount;
use App\Support\Audit\AuditActionCatalog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Trình xem nhật ký truy vết hợp nhất (security_audit_logs) — admin/super.
 * Đọc sổ cái cross-module, gắn nhãn/màu qua {@see AuditActionCatalog}.
 */
class AuditLogController extends Controller
{
    public function __invoke(Request $request): Response
    {
        abort_unless($request->user()->allows('audit.view'), 403);

        $validated = $request->validate([
            'module' => ['nullable', 'string'],
            'action' => ['nullable', 'string'],
            'actor_account_id' => ['nullable', 'integer', 'exists:system_accounts,id'],
            'search' => ['nullable', 'string', 'max:200'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', \Illuminate\Validation\Rule::in([15, 25, 50, 100])],
        ]);

        $perPage = (int) ($validated['per_page'] ?? 25);

        $query = SecurityAuditLog::query()->with('actor:id,display_name');

        if (! empty($validated['module'])) {
            $query->whereIn('action', AuditActionCatalog::actionsForModule($validated['module']));
        }
        if (! empty($validated['action'])) {
            $query->where('action', $validated['action']);
        }
        if (! empty($validated['actor_account_id'])) {
            $query->where('actor_account_id', $validated['actor_account_id']);
        }
        if (! empty($validated['search'])) {
            $term = '%'.$validated['search'].'%';
            $query->where(fn ($q) => $q
                ->where('action', 'like', $term)
                ->orWhere('subject_type', 'like', $term)
                ->orWhere('meta', 'like', $term));
        }
        if (! empty($validated['from'])) {
            $query->whereDate('created_at', '>=', $validated['from']);
        }
        if (! empty($validated['to'])) {
            $query->whereDate('created_at', '<=', $validated['to']);
        }

        $paginator = $query->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', (int) ($validated['page'] ?? 1))
            ->withQueryString();

        return Inertia::render('Audit/Index', [
            'logs' => SecurityAuditLogResource::collection($paginator->items()),
            'meta' => [
                'total' => $paginator->total(),
                'from' => $paginator->firstItem() ?? 0,
                'to' => $paginator->lastItem() ?? 0,
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
            ],
            'filters' => [
                'module' => $validated['module'] ?? null,
                'action' => $validated['action'] ?? null,
                'actor_account_id' => $validated['actor_account_id'] ?? null,
                'search' => $validated['search'] ?? null,
                'from' => $validated['from'] ?? null,
                'to' => $validated['to'] ?? null,
                'per_page' => $perPage,
            ],
            'stats' => $this->stats(),
            'trend' => $this->trend(),
            'byModule' => $this->byModule(),
            'options' => [
                'modules' => AuditActionCatalog::moduleOptions(),
                'actors' => $this->actorOptions(),
                'perPage' => [15, 25, 50, 100],
            ],
        ]);
    }

    /** @return array<string, int> */
    private function stats(): array
    {
        $base = SecurityAuditLog::query();

        return [
            'total' => (clone $base)->count(),
            'today' => (clone $base)->whereDate('created_at', today())->count(),
            'week' => (clone $base)->where('created_at', '>=', now()->subDays(7))->count(),
            'login_failed' => (clone $base)->where('action', 'auth.login_failed')->where('created_at', '>=', now()->subDays(7))->count(),
        ];
    }

    /** @return array<int, array{date:string, count:int}> */
    private function trend(): array
    {
        return SecurityAuditLog::query()
            ->where('created_at', '>=', now()->subDays(14))
            ->select(DB::raw('DATE(created_at) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r) => ['date' => (string) $r->getAttribute('date'), 'count' => (int) $r->getAttribute('count')])
            ->all();
    }

    /** @return array<int, array{module:string, module_label:string, icon:string, count:int}> */
    private function byModule(): array
    {
        $rows = SecurityAuditLog::query()
            ->where('created_at', '>=', now()->subDays(30))
            ->select('action', DB::raw('COUNT(*) as count'))
            ->groupBy('action')
            ->get();

        $byModule = [];
        foreach ($rows as $row) {
            $desc = AuditActionCatalog::describe($row->action);
            $key = $desc['module'];
            if (! isset($byModule[$key])) {
                $byModule[$key] = [
                    'module' => $key,
                    'module_label' => $desc['module_label'],
                    'icon' => $desc['icon'],
                    'count' => 0,
                ];
            }
            $byModule[$key]['count'] += (int) $row->getAttribute('count');
        }

        $out = array_values($byModule);
        usort($out, fn ($a, $b) => $b['count'] <=> $a['count']);

        return $out;
    }

    /** @return array<int, array{id:int, display_name:string}> */
    private function actorOptions(): array
    {
        $ids = SecurityAuditLog::query()
            ->whereNotNull('actor_account_id')
            ->distinct()
            ->pluck('actor_account_id');

        return SystemAccount::query()
            ->whereIn('id', $ids)
            ->orderBy('display_name')
            ->get(['id', 'display_name'])
            ->map(fn (SystemAccount $a) => ['id' => $a->id, 'display_name' => $a->display_name])
            ->all();
    }
}
