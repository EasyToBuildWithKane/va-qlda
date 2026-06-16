<?php

namespace App\Http\Controllers\Credential;

use App\Http\Controllers\Controller;
use App\Http\Resources\CredentialAuditResource;
use App\Models\Credential;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CredentialAuditController extends Controller
{
    public function index(Request $request, Credential $credential): JsonResponse
    {
        $this->authorize('view', $credential);

        $perPage = min(max((int) $request->query('per_page', 30), 5), 100);

        $logs = $credential->auditLogs()
            ->with('account')
            ->latest('created_at')
            ->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => CredentialAuditResource::collection($logs),
            'meta' => [
                'current_page' => $logs->currentPage(),
                'last_page' => $logs->lastPage(),
                'total' => $logs->total(),
            ],
        ]);
    }
}
