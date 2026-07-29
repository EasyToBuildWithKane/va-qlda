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
                'subject' => '[Workspace] Giao việc: {{task_name}} — {{project_name}}',
                'body_html' => self::taskAssignedBody(),
            ],
            EmailTemplate::KEY_DAILY_SUMMARY => [
                'subject' => '[Workspace] Tổng hợp {{date}} — {{project_name}}',
                'body_html' => self::dailySummaryBody(),
            ],
            EmailTemplate::KEY_SPRINT_SUMMARY => [
                'subject' => '[Workspace] Sprint {{sprint_name}} — {{project_name}}',
                'body_html' => self::sprintSummaryBody(),
            ],
            EmailTemplate::KEY_CONGNGHE_PROPOSAL_SUBMITTED => [
                'subject' => '[VAS · Phòng Công Nghệ] Đề xuất PM: {{proposal_title}} ({{reference_code}})',
                'body_html' => self::congngheProposalSubmittedBody(),
            ],
            EmailTemplate::KEY_CONGNGHE_PROPOSAL_REJECTED => [
                'subject' => '[VAS · Phòng Công Nghệ] Đề xuất bị từ chối: {{proposal_title}} ({{reference_code}})',
                'body_html' => self::congngheProposalRejectedBody(),
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

    private static function congngheProposalSubmittedBody(): string
    {
        return '<p style="margin:0 0 8px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#9A0036;">'
            .'Đề xuất giải pháp phần mềm</p>'
            .'<p style="margin:0 0 20px;font-size:20px;font-weight:700;color:#0f172a;line-height:1.3;">{{proposal_title}}</p>'
            .'<p style="margin:0 0 16px;font-size:15px;color:#334155;">Có đề xuất mới từ <strong>{{submitter_name}}</strong> qua cổng Phòng Công Nghệ. '
            .'Bạn có thể trả lời trực tiếp email này — thư sẽ tới người gửi.</p>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            .'style="border:1px solid #e2e8f0;border-radius:10px;border-collapse:separate;margin-bottom:20px;">'
            .'<tr><td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:130px;">Người gửi</td>'
            .'<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;"><strong>{{submitter_name}}</strong></td></tr>'
            .'<tr><td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">Email</td>'
            .'<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;font-size:14px;"><a href="mailto:{{submitter_email}}" style="color:#9A0036;">{{submitter_email}}</a></td></tr>'
            .'<tr><td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;">Phòng ban</td>'
            .'<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">{{department}}</td></tr>'
            .'<tr><td style="padding:12px 14px;color:#64748b;font-size:13px;">Thời điểm</td>'
            .'<td style="padding:12px 14px;font-size:14px;color:#0f172a;">{{submitted_at}}</td></tr>'
            .'</table>'
            .'<div style="margin-bottom:20px;padding:16px 18px;background:#f8fafc;border:1px solid #e2e8f0;border-radius:10px;">'
            .'<p style="margin:0 0 10px;font-size:12px;font-weight:600;text-transform:uppercase;color:#64748b;">Nội dung đề xuất</p>'
            .'<div style="font-size:15px;line-height:1.6;color:#1e293b;white-space:pre-wrap;">{{proposal_content}}</div></div>'
            .'<p style="margin:0;"><a href="{{portal_url}}" style="display:inline-block;padding:10px 18px;background:#9A0036;color:#ffffff;'
            .'text-decoration:none;border-radius:8px;font-weight:600;">Mở cổng Phòng Công Nghệ</a></p>';
    }

    private static function congngheProposalRejectedBody(): string
    {
        return '<p style="margin:0 0 8px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#9A0036;">'
            .'Thông báo trạng thái đề xuất</p>'
            .'<p style="margin:0 0 20px;font-size:20px;font-weight:700;color:#0f172a;line-height:1.3;">{{proposal_title}}</p>'
            .'<p style="margin:0 0 16px;font-size:13px;color:#64748b;">Mã tham chiếu: <strong style="color:#9A0036;">{{reference_code}}</strong></p>'
            .'<p style="margin:0 0 16px;font-size:15px;color:#334155;">Xin chào <strong>{{submitter_name}}</strong>, '
            .'Phòng Công Nghệ đã cập nhật trạng thái: <strong style="color:#be123c;">{{status_label}}</strong>.</p>'
            .'<div style="margin-bottom:20px;padding:16px 18px;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;">'
            .'<p style="margin:0 0 10px;font-size:12px;font-weight:600;text-transform:uppercase;color:#be123c;">Lý do từ chối</p>'
            .'<div style="font-size:15px;line-height:1.6;color:#881337;white-space:pre-wrap;">{{rejection_reason}}</div></div>'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            .'style="border:1px solid #e2e8f0;border-radius:10px;border-collapse:separate;margin-bottom:20px;">'
            .'<tr><td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:130px;">Phòng ban</td>'
            .'<td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">{{department}}</td></tr>'
            .'<tr><td style="padding:12px 14px;color:#64748b;font-size:13px;">Thời điểm gửi</td>'
            .'<td style="padding:12px 14px;font-size:14px;color:#0f172a;">{{submitted_at}}</td></tr>'
            .'</table>'
            .'<p style="margin:0;"><a href="{{mine_url}}" style="display:inline-block;padding:10px 18px;background:#9A0036;color:#ffffff;'
            .'text-decoration:none;border-radius:8px;font-weight:600;">Xem đề xuất đã gửi</a></p>';
    }
}
