<?php

namespace App\Support\Realtime;

use App\Http\Resources\CommentResource;
use App\Models\Comment;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redis;

class CommentRealtimePublisher
{
    public static function created(string $commentableType, int $commentableId, Comment $comment): void
    {
        $comment->loadMissing('author');
        self::publish('comment.created', $commentableType, $commentableId, [
            'comment' => (new CommentResource($comment))->resolve(),
        ]);
    }

    public static function deleted(string $commentableType, int $commentableId, int $commentId): void
    {
        self::publish('comment.deleted', $commentableType, $commentableId, [
            'comment_id' => $commentId,
        ]);
    }

    public static function updated(string $commentableType, int $commentableId, Comment $comment): void
    {
        $comment->loadMissing('author');
        self::publish('comment.updated', $commentableType, $commentableId, [
            'comment' => (new CommentResource($comment))->resolve(),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function publish(string $event, string $commentableType, int $commentableId, array $data): void
    {
        if (! config('realtime.enabled')) {
            return;
        }

        $message = json_encode([
            'event' => $event,
            'commentable_type' => $commentableType,
            'commentable_id' => $commentableId,
            'room' => ThreadSubscribeToken::roomName($commentableType, $commentableId),
            'data' => $data,
        ], JSON_UNESCAPED_UNICODE);

        if ($message === false) {
            return;
        }

        try {
            Redis::publish((string) config('realtime.redis_channel'), $message);
        } catch (\Throwable $e) {
            Log::warning('Comment realtime publish failed', ['error' => $e->getMessage()]);
        }
    }
}
