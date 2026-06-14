<?php

namespace App\Models;

use App\Support\Mail\EmailBrandLayout;
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
     * Full HTML document for outbound mail (brand shell + rendered fragment).
     *
     * @param  array<string, string|int|null>  $vars
     */
    public function renderBodyForDelivery(array $vars): string
    {
        $inner = $this->renderBody($vars);
        $preheader = $this->renderSubject($vars);

        return EmailBrandLayout::wrap($inner, $preheader);
    }

    /**
     * @return array<int, array{key: string, label: string, hint: string}>
     */
    public static function variableMeta(string $key): array
    {
        $catalog = [
            'assignee_name' => ['label' => 'Tên người nhận', 'hint' => 'Họ tên nhân viên được giao việc'],
            'task_name' => ['label' => 'Tên công việc', 'hint' => 'Tiêu đề task'],
            'project_name' => ['label' => 'Tên dự án', 'hint' => 'Tên dự án QLDA'],
            'sprint_name' => ['label' => 'Tên sprint', 'hint' => 'Sprint chứa task (hoặc —)'],
            'due_date' => ['label' => 'Hạn hoàn thành', 'hint' => 'Định dạng dd/mm/yyyy'],
            'task_url' => ['label' => 'Link mở task', 'hint' => 'URL deep-link vào tab Sprint'],
            'date' => ['label' => 'Ngày tổng hợp', 'hint' => 'Ngày gửi email tổng hợp'],
            'tasks_table' => ['label' => 'Bảng công việc', 'hint' => 'HTML bảng do hệ thống tạo — không sửa cấu trúc'],
            'task_count' => ['label' => 'Số lượng task', 'hint' => 'Tổng số dòng trong bảng'],
        ];

        return array_values(array_map(
            fn (string $varKey) => [
                'key' => $varKey,
                'label' => $catalog[$varKey]['label'] ?? $varKey,
                'hint' => $catalog[$varKey]['hint'] ?? '',
            ],
            self::variableHints($key),
        ));
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
