<?php

namespace App\Support\Mail;

use App\Models\EmailTemplate;

/**
 * Sample placeholder values for preview and test sends.
 *
 * @return array<string, string>
 */
final class EmailTemplateSampleVars
{
    public static function forKey(string $key): array
    {
        $base = [
            'assignee_name' => 'Nguyễn Văn A',
            'task_name' => 'Thiết kế màn hình Sprint',
            'project_name' => 'Dự án QLDA mẫu',
            'sprint_name' => 'Sprint 1',
            'due_date' => '20/06/2026',
            'task_url' => url('/projects/1?tab=sprints&task=1'),
            'date' => now()->format('d/m/Y'),
            'task_count' => '2',
        ];

        $base['tasks_table'] = self::sampleTasksTableHtml();

        return match ($key) {
            EmailTemplate::KEY_TASK_ASSIGNED => array_intersect_key($base, array_flip([
                'assignee_name', 'task_name', 'project_name', 'sprint_name', 'due_date', 'task_url',
            ])),
            EmailTemplate::KEY_DAILY_SUMMARY => array_intersect_key($base, array_flip([
                'assignee_name', 'project_name', 'date', 'tasks_table', 'task_count',
            ])),
            EmailTemplate::KEY_SPRINT_SUMMARY => array_intersect_key($base, array_flip([
                'assignee_name', 'project_name', 'sprint_name', 'tasks_table', 'task_count',
            ])),
            default => $base,
        };
    }

    public static function sampleTasksTableHtml(): string
    {
        return '<table style="width:100%;border:1px solid #e2e8f0;border-collapse:collapse;">'
            .'<thead><tr style="background:#FDF2F6;">'
            .'<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Công việc</th>'
            .'<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Trạng thái</th>'
            .'<th style="padding:8px 10px;text-align:left;color:#9A0036;font-size:13px;">Hạn</th>'
            .'</tr></thead><tbody>'
            .'<tr><td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Thiết kế màn Sprint</td>'
            .'<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Đang làm</td>'
            .'<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">20/06/2026</td></tr>'
            .'<tr><td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Review API task</td>'
            .'<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">Cần làm</td>'
            .'<td style="padding:8px 10px;border-bottom:1px solid #e2e8f0;">21/06/2026</td></tr>'
            .'</tbody></table>';
    }
}
