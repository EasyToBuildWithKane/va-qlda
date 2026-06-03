<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\StoreAiAccountPasswordViewerRequest;
use App\Models\AiAccount;
use App\Models\AiAccountPasswordViewer;
use App\Models\SystemAccount;
use App\Support\Enums\SystemRole;
use App\Support\PublicMediaUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAccountPasswordViewerController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $this->authorize('managePasswordViewers', AiAccount::class);

        $viewers = AiAccountPasswordViewer::query()
            ->with(['account.employee', 'grantedBy.employee'])
            ->orderByDesc('created_at')
            ->get()
            ->map(fn (AiAccountPasswordViewer $row) => $this->viewerRow($row))
            ->values()
            ->all();

        $existingIds = collect($viewers)->pluck('system_account_id')->all();

        $candidates = SystemAccount::query()
            ->where('is_active', true)
            ->where('role', '!=', SystemRole::Admin->value)
            ->when($existingIds !== [], fn ($q) => $q->whereNotIn('id', $existingIds))
            ->with('employee')
            ->orderBy('display_name')
            ->get()
            ->map(fn (SystemAccount $a) => $this->candidateRow($a))
            ->values()
            ->all();

        return response()->json([
            'success' => true,
            'data' => [
                'viewers' => $viewers,
                'candidates' => $candidates,
            ],
        ]);
    }

    public function store(StoreAiAccountPasswordViewerRequest $request): JsonResponse
    {
        $viewer = AiAccountPasswordViewer::create([
            'system_account_id' => $request->validated('system_account_id'),
            'granted_by' => $request->user()->id,
        ]);

        $viewer->load(['account.employee', 'grantedBy.employee']);

        return response()->json([
            'success' => true,
            'data' => ['viewer' => $this->viewerRow($viewer)],
            'message' => 'Đã cấp quyền xem mật khẩu tài khoản AI.',
        ], 201);
    }

    public function destroy(Request $request, AiAccountPasswordViewer $passwordViewer): JsonResponse
    {
        $this->authorize('managePasswordViewers', AiAccount::class);

        $name = $passwordViewer->account?->employee?->full_name
            ?? $passwordViewer->account?->display_name
            ?? 'Thành viên';

        $passwordViewer->delete();

        return response()->json([
            'success' => true,
            'message' => "Đã thu hồi quyền xem mật khẩu của {$name}.",
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function viewerRow(AiAccountPasswordViewer $row): array
    {
        $account = $row->account;
        $employee = $account?->employee;

        return [
            'id' => $row->id,
            'system_account_id' => $row->system_account_id,
            'name' => $employee?->full_name ?? $account?->display_name ?? '—',
            'email' => $employee?->email ?? $account?->username,
            'role' => $account?->role?->value,
            'role_label' => $account?->role?->label() ?? '—',
            'department' => is_array($employee?->meta) ? ($employee->meta['department_name'] ?? null) : null,
            'granted_by_name' => $row->grantedBy?->employee?->full_name
                ?? $row->grantedBy?->display_name,
            'granted_at' => $row->created_at?->format('d/m/Y H:i'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function candidateRow(SystemAccount $account): array
    {
        $employee = $account->employee;

        return [
            'id' => $account->id,
            'label' => trim(($employee?->full_name ?? $account->display_name).' · '.($employee?->email ?? $account->username)),
            'name' => $employee?->full_name ?? $account->display_name,
            'email' => $employee?->email ?? $account->username,
            'role_label' => $account->role?->label() ?? '—',
            'department' => is_array($employee?->meta) ? ($employee->meta['department_name'] ?? null) : null,
            'avatar_path' => PublicMediaUrl::fromPublicDisk($employee?->avatar_path),
        ];
    }
}
