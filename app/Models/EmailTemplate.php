<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string $subject
 * @property string $body_html
 * @property bool $is_active
 */
class EmailTemplate extends Model
{
    public const KEY_TASK_ASSIGNED = 'task_assigned';

    public const KEY_DAILY_SUMMARY = 'daily_summary';

    public const KEY_SPRINT_SUMMARY = 'sprint_summary';

    protected $fillable = [
        'key',
        'name',
        'subject',
        'body_html',
        'is_active',
        'updated_by',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(SystemAccount::class, 'updated_by');
    }

    public static function findByKey(string $key): ?self
    {
        return self::query()->where('key', $key)->first();
    }

    /**
     * @param  array<string, string|int|null>  $vars
     */
    public function renderSubject(array $vars): string
    {
        return $this->replacePlaceholders($this->subject, $vars);
    }

    /**
     * @param  array<string, string|int|null>  $vars
     */
    public function renderBody(array $vars): string
    {
        return $this->replacePlaceholders($this->body_html, $vars);
    }

    /**
     * @return array<int, string>
     */
    public static function variableHints(string $key): array
    {
        return match ($key) {
            self::KEY_TASK_ASSIGNED => [
                'assignee_name', 'task_name', 'project_name', 'sprint_name', 'due_date', 'task_url',
            ],
            self::KEY_DAILY_SUMMARY => [
                'assignee_name', 'project_name', 'date', 'tasks_table', 'task_count',
            ],
            self::KEY_SPRINT_SUMMARY => [
                'assignee_name', 'project_name', 'sprint_name', 'tasks_table', 'task_count',
            ],
            default => [],
        };
    }

    /**
     * @param  array<string, string|int|null>  $vars
     */
    private function replacePlaceholders(string $text, array $vars): string
    {
        foreach ($vars as $name => $value) {
            $text = str_replace('{{'.$name.'}}', (string) ($value ?? ''), $text);
        }

        return preg_replace('/\{\{[a-z_]+\}\}/', '', $text) ?? $text;
    }
}
