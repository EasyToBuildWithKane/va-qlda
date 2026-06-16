<?php

namespace App\Http\Controllers\Credential;

use App\Http\Controllers\Controller;
use App\Http\Resources\CredentialRelationResource;
use App\Models\Credential;
use App\Models\CredentialRelation;
use App\Support\Enums\CredentialRelationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CredentialRelationController extends Controller
{
    public function index(Request $request, Credential $credential): JsonResponse
    {
        $this->authorize('view', $credential);

        $relations = $credential->outgoingRelations()->with('target')->get();

        return response()->json([
            'success' => true,
            'data' => CredentialRelationResource::collection($relations),
        ]);
    }

    public function store(Request $request, Credential $credential): JsonResponse
    {
        $this->authorize('update', $credential);

        $validated = $request->validate([
            'target_id' => ['required', 'integer', 'exists:credentials,id', 'not_in:'.$credential->id],
            'relation_type' => ['required', Rule::in(CredentialRelationType::values())],
            'label' => ['nullable', 'string', 'max:255'],
        ], [
            'target_id.not_in' => 'Không thể liên kết tài khoản với chính nó.',
        ]);

        $relation = CredentialRelation::create([
            'source_id' => $credential->id,
            'target_id' => $validated['target_id'],
            'relation_type' => $validated['relation_type'],
            'label' => $validated['label'] ?? null,
            'created_by' => $request->user()->id,
        ]);

        $relation->load('target');

        return response()->json([
            'success' => true,
            'data' => new CredentialRelationResource($relation),
            'message' => 'Đã thêm liên kết hạ tầng.',
        ]);
    }

    public function destroy(Request $request, Credential $credential, CredentialRelation $relation): JsonResponse
    {
        $this->authorize('update', $credential);

        if ($relation->source_id !== $credential->id) {
            abort(404);
        }

        $relation->delete();

        return response()->json([
            'success' => true,
            'message' => 'Đã xóa liên kết.',
        ]);
    }
}
