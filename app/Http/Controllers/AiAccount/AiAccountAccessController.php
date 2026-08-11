<?php

namespace App\Http\Controllers\AiAccount;

use App\Http\Controllers\Controller;
use App\Http\Requests\AiAccount\GrantAiAccountAccessRequest;
use App\Http\Resources\AiAccountAccessGrantResource;
use App\Models\AiAccount;
use App\Models\AiAccountAccessGrant;
use App\Support\SecurityAuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AiAccountAccessController extends Controller
{
    public function index(Request $request, AiAccount $aiAccount): JsonResponse
    {
        $this->authorize('view', $aiAccount);

        $grants = $aiAccount->accessGrants()->with(['account', 'grantedBy'])->get();

        return response()->json([
            'success' => true,
            'data' => AiAccountAccessGrantResource::collection($grants)->resolve(),
        ]);
    }

    public function store(GrantAiAccountAccessRequest $request, AiAccount $aiAccount): JsonResponse
    {
        $data = $request->validated();

        $grant = AiAccountAccessGrant::query()->updateOrCreate(
            [
                'ai_account_id' => $aiAccount->id,
                'account_id' => $data['account_id'],
            ],
            [
                'permissions' => $data['permissions'],
                'granted_by' => $request->user()->id,
                'expires_at' => $data['expires_at'] ?? null,
            ],
        );

        SecurityAuditLogger::aiAccount($request->user(), 'access_granted', null, [
            'ai_account_id' => $aiAccount->id,
            'target_account_id' => (int) $data['account_id'],
            'permissions' => $data['permissions'],
        ]);

        $grant->load(['account', 'grantedBy']);

        return response()->json([
            'success' => true,
            'data' => new AiAccountAccessGrantResource($grant),
            'message' => 'Đã cấp quyền truy cập.',
        ]);
    }

    public function destroy(Request $request, AiAccount $aiAccount, AiAccountAccessGrant $accessGrant): JsonResponse
    {
        $this->authorize('manageAccess', $aiAccount);

        if ($accessGrant->ai_account_id !== $aiAccount->id) {
            abort(404);
        }

        $accountId = (int) $accessGrant->account_id;
        $accessGrant->delete();

        SecurityAuditLogger::aiAccount($request->user(), 'access_revoked', null, [
            'ai_account_id' => $aiAccount->id,
            'target_account_id' => $accountId,
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Đã thu hồi quyền.',
        ]);
    }
}
