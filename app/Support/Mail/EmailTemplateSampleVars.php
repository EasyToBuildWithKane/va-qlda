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
            'project_name' => 'Dự án Workspace mẫu',
            'sprint_name' => 'Sprint 1',
            'due_date' => '20/06/2026',
            'task_url' => url('/projects/1?tab=sprints&task=1'),
            'date' => now()->format('d/m/Y'),
            'task_count' => '2',
            'submitter_name' => 'Nguyễn Văn A',
            'submitter_email' => 'nguyenvana@vaschools.edu.vn',
            'proposal_title' => 'Đề xuất triển khai LMS mới',
            'proposal_content' => 'Mô tả nhu cầu và phạm vi triển khai hệ thống LMS cho học kỳ tới.',
            'reference_code' => 'CN-00042',
            'department' => 'Phòng Học vụ',
            'submitted_at' => now()->format('d/m/Y H:i'),
            'portal_url' => route('congnghe'),
            'rejection_reason' => 'Nội dung chưa đủ thông tin kỹ thuật; vui lòng bổ sung quy mô người dùng và tích hợp hệ thống hiện có.',
            'status_label' => 'Từ chối',
            'mine_url' => url('/congnghe/de-xuat-cua-toi/1'),
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
            EmailTemplate::KEY_CONGNGHE_PROPOSAL_SUBMITTED => array_intersect_key($base, array_flip([
                'submitter_name', 'submitter_email', 'proposal_title', 'reference_code',
                'department', 'submitted_at', 'proposal_content', 'portal_url',
            ])),
            EmailTemplate::KEY_CONGNGHE_PROPOSAL_REJECTED => array_intersect_key($base, array_flip([
                'submitter_name', 'proposal_title', 'reference_code', 'department',
                'submitted_at', 'rejection_reason', 'status_label', 'mine_url',
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
