<?php

namespace App\Http\Controllers\Comment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Models\Blocker;
use App\Models\Bug;
use App\Models\Comment;
use App\Models\Feedback;
use App\Models\Task;
use App\Support\BlockerActivityLogger;
use App\Support\BugActivityLogger;
use App\Support\FeedbackActivityLogger;
use App\Support\NotificationDispatcher;
use App\Support\Realtime\CommentRealtimePublisher;
use App\Support\TaskActivityLogger;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /** Whitelist of commentable types so arbitrary models can't be targeted. */
    private const TYPES = [
        'bug' => Bug::class,
        'feedback' => Feedback::class,
        'blocker' => Blocker::class,
        'task' => Task::class,
    ];

    public function store(StoreCommentRequest $request): RedirectResponse
    {
        $data = $request->validated();

        $extra = $request->validate([
            'commentable_type' => ['required', 'string', 'in:'.implode(',', array_keys(self::TYPES))],
            'commentable_id' => ['required', 'integer'],
            'parent_id' => ['nullable', 'integer', 'exists:comments,id'],
        ]);

        $model = $this->resolve($extra['commentable_type'], (int) $extra['commentable_id']);

        $comment = Comment::create([
            'commentable_type' => $model->getMorphClass(),
            'commentable_id' => $model->getKey(),
            'parent_id' => $extra['parent_id'] ?? null,
            'employee_id' => $request->user()->employee_id,
            'body' => $data['body'],
        ]);

        $user = $request->user();

        CommentRealtimePublisher::created(
            $extra['commentable_type'],
            (int) $extra['commentable_id'],
            $comment,
        );

        if ($model instanceof Blocker) {
            BlockerActivityLogger::commentAdded($model, $user);
            $isMention = str_contains($data['body'], '@');
            NotificationDispatcher::blockerComment($model->fresh(['project', 'owner', 'raisedBy']), $user, $isMention);
        } elseif ($model instanceof Task) {
            TaskActivityLogger::commentAdded($model, $user);
            $isMention = str_contains($data['body'], '@');
            NotificationDispatcher::taskComment($model->fresh(['project', 'watchers']), $user, $isMention);
        } elseif ($model instanceof Bug) {
            BugActivityLogger::commentAdded($model, $user);
        } elseif ($model instanceof Feedback) {
            FeedbackActivityLogger::commentAdded($model, $user);
        }

        return back()->with('success', 'Đã gửi bình luận.');
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorizeComment($request, $comment);

        $data = $request->validate([
            'body' => ['required', 'string', 'max:5000'],
        ]);

        $comment->update(['body' => $data['body']]);
        $this->logCommentEvent($comment, 'updated', $request->user());

        return back()->with('success', 'Đã cập nhật bình luận.');
    }

    public function destroy(Request $request, Comment $comment): RedirectResponse
    {
        $this->authorizeComment($request, $comment);

        $comment->loadMissing('commentable');
        $typeKey = $this->commentableTypeKey($comment);
        $commentableId = (int) $comment->commentable_id;
        $commentId = (int) $comment->id;

        $this->logCommentEvent($comment, 'deleted', $request->user());

        $comment->replies()->delete();
        $comment->delete();

        if ($typeKey !== null) {
            CommentRealtimePublisher::deleted($typeKey, $commentableId, $commentId);
        }

        return back()->with('success', 'Đã xoá bình luận.');
    }

    public function react(Request $request, Comment $comment): RedirectResponse
    {
        $user = $request->user();
        abort_unless((bool) $user?->employee_id, 403);

        $data = $request->validate([
            'emoji' => ['required', 'string', 'max:8'],
        ]);

        $reactions = $comment->reactions ?? [];
        $emoji = $data['emoji'];
        $ids = $reactions[$emoji] ?? [];
        $employeeId = $user->employee_id;

        if (in_array($employeeId, $ids, true)) {
            $ids = array_values(array_filter($ids, fn ($id) => $id !== $employeeId));
        } else {
            $ids[] = $employeeId;
        }

        if ($ids === []) {
            unset($reactions[$emoji]);
        } else {
            $reactions[$emoji] = $ids;
        }

        $comment->update(['reactions' => $reactions === [] ? null : $reactions]);

        return back();
    }

    private function authorizeComment(Request $request, Comment $comment): void
    {
        $user = $request->user();
        abort_unless($user !== null, 403);

        if ($comment->employee_id && $comment->employee_id === $user->employee_id) {
            return;
        }

        $comment->loadMissing('commentable');
        $target = $comment->commentable;

        if ($target instanceof Task) {
            abort_unless($user->can('contribute', $target->project), 403);

            return;
        }

        if ($target instanceof Blocker) {
            abort_unless($user->can('update', $target), 403);

            return;
        }

        abort(403);
    }

    private function resolve(string $type, int $id): Model
    {
        /** @var class-string<Model> $class */
        $class = self::TYPES[$type];

        return $class::findOrFail($id);
    }

    private function commentableTypeKey(Comment $comment): ?string
    {
        foreach (self::TYPES as $key => $class) {
            if ($comment->commentable_type === (new $class)->getMorphClass()) {
                return $key;
            }
        }

        return null;
    }

    private function logCommentEvent(Comment $comment, string $action, $user): void
    {
        $target = $comment->commentable;
        if (! $target) {
            return;
        }

        if ($target instanceof Task) {
            $action === 'updated'
                ? TaskActivityLogger::commentUpdated($target, $user)
                : TaskActivityLogger::commentDeleted($target, $user);
        } elseif ($target instanceof Blocker) {
            $action === 'updated'
                ? BlockerActivityLogger::commentUpdated($target, $user)
                : BlockerActivityLogger::commentDeleted($target, $user);
        } elseif ($target instanceof Bug) {
            $action === 'updated'
                ? BugActivityLogger::commentUpdated($target, $user)
                : BugActivityLogger::commentDeleted($target, $user);
        } elseif ($target instanceof Feedback) {
            $action === 'updated'
                ? FeedbackActivityLogger::commentUpdated($target, $user)
                : FeedbackActivityLogger::commentDeleted($target, $user);
        }
    }
}
