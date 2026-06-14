<?php

namespace App\Support\Mail;

use App\Models\EmailTemplate;

/**
 * Production default subjects and inner HTML (content inside {@see EmailBrandLayout}).
 */
final class EmailTemplateDefaults
{
    /**
     * @return array{subject: string, body_html: string}
     */
    public static function forKey(string $key): array
    {
        return match ($key) {
            EmailTemplate::KEY_TASK_ASSIGNED => [
                'subject' => '[QLDA] Giao việc: {{task_name}} — {{project_name}}',
                'body_html' => self::taskAssignedBody(),
            ],
            EmailTemplate::KEY_DAILY_SUMMARY => [
                'subject' => '[QLDA] Tổng hợp {{date}} — {{project_name}}',
                'body_html' => self::dailySummaryBody(),
            ],
            EmailTemplate::KEY_SPRINT_SUMMARY => [
                'subject' => '[QLDA] Sprint {{sprint_name}} — {{project_name}}',
                'body_html' => self::sprintSummaryBody(),
            ],
            default => ['subject' => '', 'body_html' => ''],
        };
    }

    private static function taskAssignedBody(): string
    {
        return '<p style="margin:0 0 12px;font-size:15px;">Xin chào <strong>{{assignee_name}}</strong>,</p>'
            .'<p style="margin:0 0 20px;color:#64748b;">Bạn được giao công việc mới trong dự án '
            .'<strong style="color:#1e293b;">{{project_name}}</strong>.</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            .'style="border:1px solid #e2e8f0;border-radius:10px;border-collapse:separate;margin-bottom:20px;">'
            .'<tr><td style="padding:14px 16px;border-bottom:1px solid #f1f5f9;">'
            .'<span style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:#9A0036;">Công việc</span><br>'
            .'<span style="font-size:16px;font-weight:600;color:#1e293b;">{{task_name}}</span></td></tr>'
            .'<tr><td style="padding:12px 16px;font-size:14px;color:#475569;">'
            .'<strong>Sprint:</strong> {{sprint_name}} &nbsp;·&nbsp; <strong>Hạn:</strong> {{due_date}}</td></tr>'
            .'</table>'
            .'<p style="margin:0;">'
            .'<a href="{{task_url}}" style="display:inline-block;background:#9A0036;color:#ffffff;text-decoration:none;'
            .'font-weight:600;font-size:14px;padding:12px 22px;border-radius:8px;">Mở công việc</a></p>';
    }

    private static function dailySummaryBody(): string
    {
        return '<p style="margin:0 0 12px;font-size:15px;">Xin chào <strong>{{assignee_name}}</strong>,</p>'
            .'<p style="margin:0 0 16px;color:#64748b;">Tổng hợp công việc của bạn trong ngày '
            .'<strong style="color:#1e293b;">{{date}}</strong> — dự án <strong>{{project_name}}</strong>.</p>'
            .'{{tasks_table}}'
            .'<p style="margin:16px 0 0;padding:12px 16px;background:#FDF2F6;border-radius:8px;font-size:14px;color:#9A0036;">'
            .'<strong>{{task_count}}</strong> công việc trong danh sách trên.</p>';
    }

    private static function sprintSummaryBody(): string
    {
        return '<p style="margin:0 0 12px;font-size:15px;">Xin chào <strong>{{assignee_name}}</strong>,</p>'
            .'<p style="margin:0 0 16px;color:#64748b;">Công việc được giao trong sprint '
            .'<strong style="color:#1e293b;">{{sprint_name}}</strong> (dự án {{project_name}}).</p>'
            .'{{tasks_table}}'
            .'<p style="margin:16px 0 0;padding:12px 16px;background:#FDF2F6;border-radius:8px;font-size:14px;color:#9A0036;">'
            .'<strong>{{task_count}}</strong> công việc trong sprint.</p>';
    }
}
