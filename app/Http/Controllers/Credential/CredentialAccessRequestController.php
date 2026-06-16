<?php

namespace App\Http\Controllers\Credential;

use App\Http\Controllers\Controller;
use App\Http\Requests\Credential\StoreAccessRequestRequest;
use App\Models\Credential;
use App\Models\CredentialAccessGrant;
use App\Models\CredentialAccessRequest;
use App\Support\Enums\CredentialAccessRequestStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialAccessRequestController extends Controller
{
    public function store(StoreAccessRequestRequest $request, Credential $credential): JsonResponse
    {
        $data = $request->validated();

        $accessRequest = CredentialAccessRequest::create([
            'credential_id' => $credential->id,
            'requester_id' => $request->user()->id,
            'status' => CredentialAccessRequestStatus::Pending,
            'requested_permissions' => $data['requested_permissions'],
            'reason' => $data['reason'] ?? null,
        ]);

        return response()->json([
            'success' => true,
            'data' => ['id' => $accessRequest->id],
            'message' => 'Đã gửi yêu cầu truy cập. Chờ người phụ trách duyệt.',
        ]);
    }

    public function respond(Request $request, Credential $credential, CredentialAccessRequest $accessRequest): JsonResponse
    {
        $this->authorize('manageAccess', $credential);

        if ($accessRequest->credential_id !== $credential->id) {
            abort(404);
        }

        $validated = $request->validate([
            'decision' => ['required', 'in:approved,rejected'],
        ]);

        $status = $validated['decision'] === 'approved'
            ? CredentialAccessRequestStatus::Approved
            : CredentialAccessRequestStatus::Rejected;

        $accessRequest->update([
            'status' => $status,
            'approver_id' => $request->user()->id,
            'responded_at' => now(),
        ]);

        if ($status === CredentialAccessRequestStatus::Approved) {
            CredentialAccessGrant::updateOrCreate(
                [
                    'credential_id' => $credential->id,
                    'account_id' => $accessRequest->requester_id,
                ],
                [
                    'permissions' => $accessRequest->requested_permissions,
                    'granted_by' => $request->user()->id,
                    'expires_at' => $accessRequest->expires_at,
                ],
            );
        }

        return response()->json([
            'success' => true,
            'message' => $status === CredentialAccessRequestStatus::Approved
                ? 'Đã duyệt yêu cầu truy cập.'
                : 'Đã từ chối yêu cầu truy cập.',
        ]);
    }
}
