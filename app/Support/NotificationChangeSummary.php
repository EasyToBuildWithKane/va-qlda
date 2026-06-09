<?php

namespace App\Support;

/**
 * Mô tả ngắn các trường thay đổi cho title/body thông báo inbox.
 */
class NotificationChangeSummary
{
    /** @var array<string, string> */
    private const TASK_LABELS = [
        'title' => 'tiêu đề',
        'description' => 'mô tả',
        'status' => 'trạng thái',
        'priority' => 'ưu tiên',
        'phase' => 'giai đoạn',
        'sprint_id' => 'sprint',
        'assignee_id' => 'người thực hiện',
        'reviewer_id' => 'người duyệt',
        'reporter_id' => 'người báo',
        'start_date' => 'ngày bắt đầu',
        'due_date' => 'hạn',
        'estimate_hours' => 'giờ ước tính',
        'story_points' => 'story point',
        'progress' => 'tiến độ',
        'epic_id' => 'epic',
        'parent_id' => 'task cha',
    ];

    /** @var array<string, string> */
    private const PROJECT_LABELS = [
        'code' => 'mã dự án',
        'name' => 'tên',
        'description' => 'mô tả',
        'color' => 'màu',
        'status' => 'trạng thái',
        'type' => 'loại',
        'scope' => 'phạm vi',
        'start_date' => 'ngày bắt đầu',
        'due_date' => 'hạn dự án',
        'budget' => 'ngân sách',
        'actual_budget' => 'chi phí thực tế',
        'manager_id' => 'chủ dự án',
        'department_id' => 'phòng ban',
        'is_active' => 'trạng thái hoạt động',
        'sort_order' => 'thứ tự',
    ];

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function task(array $changes): ?string
    {
        return self::summarize($changes, self::TASK_LABELS);
    }

    /**
     * @param  array<string, mixed>  $changes
     */
    public static function project(array $changes): ?string
    {
        return self::summarize($changes, self::PROJECT_LABELS);
    }

    /**
     * @param  array<string, mixed>  $changes
     * @param  array<string, string>  $labels
     */
    private static function summarize(array $changes, array $labels): ?string
    {
        if ($changes === []) {
            return null;
        }

        $parts = [];
        foreach (array_keys($changes) as $field) {
            if (isset($labels[$field])) {
                $parts[] = $labels[$field];
            }
        }

        if ($parts === []) {
            return null;
        }

        $max = 8;
        $slice = array_slice($parts, 0, $max);
        $text = implode(', ', $slice);
        if (count($parts) > $max) {
            $text .= '…';
        }

        return 'Thay đổi: '.$text;
    }
}
