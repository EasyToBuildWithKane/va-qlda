<?php

namespace App\Http\Controllers\Comment;

use App\Http\Controllers\Controller;
use App\Http\Requests\Comment\StoreCommentRequest;
use App\Models\Blocker;
use App\Models\Bug;
use App\Models\Feedback;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;

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
        ]);

        $model = $this->resolve($extra['commentable_type'], (int) $extra['commentable_id']);

        $model->comments()->create([
            'employee_id' => $request->user()->employee_id,
            'body' => $data['body'],
        ]);

        return back()->with('success', 'Đã gửi bình luận.');
    }

    private function resolve(string $type, int $id): Model
    {
        /** @var class-string<Model> $class */
        $class = self::TYPES[$type];

        return $class::findOrFail($id);
    }
}
