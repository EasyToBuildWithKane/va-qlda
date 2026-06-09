<?php

namespace App\Http\Controllers\Realtime;

use App\Http\Controllers\Controller;
use App\Support\Realtime\CommentThreadAuthorizer;
use App\Support\Realtime\ThreadSubscribeToken;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class RealtimeController extends Controller
{
    public function threadToken(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null, 403);
        abort_unless(config('realtime.enabled'), 404);

        $data = $request->validate([
            'type' => ['required', 'string'],
            'id' => ['required', 'integer', 'min:1'],
        ]);

        abort_unless(CommentThreadAuthorizer::isAllowedType($data['type']), 422);

        return response()->json([
            'token' => ThreadSubscribeToken::issue($user, $data['type'], (int) $data['id']),
            'room' => ThreadSubscribeToken::roomName($data['type'], (int) $data['id']),
        ]);
    }
}
