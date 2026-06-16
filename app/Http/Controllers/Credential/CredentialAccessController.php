<?php

namespace App\Http\Controllers\Credential;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credential\GrantAccessRequest;
use App\Http\Resources\CredentialAccessGrantResource;
use App\Models\Credential;
use App\Models\CredentialAccessGrant;
use App\Support\Credential\CredentialActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialAccessController extends Controller
{
    public function index(Request $request, Credential $credential): JsonResponse
    {
        $this->authorize('view', $credential);

        $grants = $credential->accessGrants()->with('account')->get();

        return response()->json([
            'success' => true,
            'data' => CredentialAccessGrantResource::collection($grants),
        ]);
    }

    public function store(GrantAccessRequest $request, Credential $credential): JsonResponse
    {
        $data = $request->validated();

        $grant = CredentialAccessGrant::updateOrCreate(
            [
                'credential_id' => $credential->id,
                'account_id' => $data['account_id'],
            ],
            [
                'permissions' => $data['permissions'],
                'granted_by' => $request->user()->id,
                'expires_at' => $data['expires_at'] ?? null,
            ],
        );

        CredentialActivityLogger::accessGranted($credential, $request->user(), (int) $data['account_id'], $request);

        $grant->load('account');

        return response()->json([
            'success' => true,
            'data' => new CredentialAccessGrantResource($grant),
            'message' => 'Đã cấp quyền truy cập.',
        ]);
    }

    public function destroy(Request $request, Credential $credential, CredentialAccessGrant $accessGrant): JsonResponse
    {
        $this->authorize('manageAccess', $credential);

        if ($accessGrant->credential_id !== $credential->id) {
            abort(404);
        }

        $accountId = (int) $accessGrant->account_id;
        $accessGrant->delete();

        CredentialActivityLogger::accessRevoked($credential, $request->user(), $accountId, $request);

        return response()->json([
            'success' => true,
            'message' => 'Đã thu hồi quyền.',
        ]);
    }
}
