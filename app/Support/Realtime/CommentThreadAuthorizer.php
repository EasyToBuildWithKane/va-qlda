<?php

namespace App\Support\Realtime;

use App\Models\Blocker;
use App\Models\Feedback;
use App\Models\KbArticle;
use App\Models\SystemAccount;
use App\Models\Task;
use Illuminate\Database\Eloquent\Model;

class CommentThreadAuthorizer
{
    /** @var array<string, class-string<Model>> */
    private const TYPES = [
        'feedback' => Feedback::class,
        'blocker' => Blocker::class,
        'task' => Task::class,
        'kb_article' => KbArticle::class,
    ];

    public static function canSubscribe(SystemAccount $account, string $type, int $id): bool
    {
        if (! isset(self::TYPES[$type])) {
            return false;
        }

        /** @var class-string<Model> $class */
        $class = self::TYPES[$type];
        $model = $class::query()->find($id);
        if (! $model) {
            return false;
        }

        if ($model instanceof Task) {
            $model->loadMissing('project');

            return $model->project && $account->can('contribute', $model->project);
        }

        return $account->can('view', $model);
    }

    public static function isAllowedType(string $type): bool
    {
        return isset(self::TYPES[$type]);
    }
}
