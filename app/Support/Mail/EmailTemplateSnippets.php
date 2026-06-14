<?php

namespace App\Support\Mail;

use App\Models\EmailTemplate;

/**
 * Insertable HTML blocks for the admin template editor (email-safe inline styles).
 *
 * @return array<int, array{id: string, label: string, description: string, html: string}>
 */
final class EmailTemplateSnippets
{
    public static function forKey(string $key): array
    {
        $common = [
            self::snippet('greeting', 'Lời chào', 'Đoạn mở đầu với tên người nhận', self::greeting()),
            self::snippet('muted_paragraph', 'Đoạn mô tả', 'Văn bản phụ màu xám', self::mutedParagraph()),
            self::snippet('highlight_box', 'Hộp nhấn mạnh', 'Nền hồng nhạt thương hiệu', self::highlightBox()),
            self::snippet('cta_button', 'Nút hành động', 'Nút brand dẫn link (dùng {{task_url}})', self::ctaButton()),
            self::snippet('divider', 'Đường phân cách', 'Khoảng cách giữa các phần', self::divider()),
        ];

        $extra = match ($key) {
            EmailTemplate::KEY_TASK_ASSIGNED => [
                self::snippet('task_card', 'Thẻ công việc', 'Tóm tắt task + sprint + hạn', self::taskCard()),
            ],
            EmailTemplate::KEY_DAILY_SUMMARY, EmailTemplate::KEY_SPRINT_SUMMARY => [
                self::snippet('tasks_table', 'Bảng công việc', 'Chèn {{tasks_table}} — hệ thống render khi gửi', '{{tasks_table}}'),
                self::snippet('task_count_line', 'Dòng đếm task', 'Tổng số công việc', self::taskCountLine()),
            ],
            default => [],
        };

        return array_merge($common, $extra);
    }

    /**
     * @return array{id: string, label: string, description: string, html: string}
     */
    private static function snippet(string $id, string $label, string $description, string $html): array
    {
        return compact('id', 'label', 'description', 'html');
    }

    private static function greeting(): string
    {
        return '<p style="margin:0 0 12px;font-size:15px;line-height:1.5;">Xin chào <strong>{{assignee_name}}</strong>,</p>';
    }

    private static function mutedParagraph(): string
    {
        return '<p style="margin:0 0 16px;font-size:14px;line-height:1.55;color:#64748b;">Nội dung mô tả ngắn gọn tại đây.</p>';
    }

    private static function highlightBox(): string
    {
        return '<p style="margin:16px 0 0;padding:12px 16px;background:#FDF2F6;border-radius:8px;font-size:14px;color:#9A0036;line-height:1.5;">'
            .'<strong>Ghi chú:</strong> Thông tin quan trọng.</p>';
    }

    private static function ctaButton(): string
    {
        return '<p style="margin:20px 0 0;">'
            .'<a href="{{task_url}}" style="display:inline-block;background:#9A0036;color:#ffffff;text-decoration:none;'
            .'font-weight:600;font-size:14px;padding:12px 22px;border-radius:8px;">Xem chi tiết</a></p>';
    }

    private static function divider(): string
    {
        return '<hr style="margin:20px 0;border:none;border-top:1px solid #e2e8f0;">';
    }

    private static function taskCard(): string
    {
        return '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" '
            .'style="border:1px solid #e2e8f0;border-radius:10px;border-collapse:separate;margin-bottom:20px;">'
            .'<tr><td style="padding:14px 16px;border-bottom:1px solid #f1f5f9;">'
            .'<span style="font-size:11px;font-weight:600;text-transform:uppercase;letter-spacing:0.04em;color:#9A0036;">Công việc</span><br>'
            .'<span style="font-size:16px;font-weight:600;color:#1e293b;">{{task_name}}</span></td></tr>'
            .'<tr><td style="padding:12px 16px;font-size:14px;color:#475569;">'
            .'<strong>Sprint:</strong> {{sprint_name}} &nbsp;·&nbsp; <strong>Hạn:</strong> {{due_date}}</td></tr>'
            .'</table>';
    }

    private static function taskCountLine(): string
    {
        return '<p style="margin:16px 0 0;padding:12px 16px;background:#FDF2F6;border-radius:8px;font-size:14px;color:#9A0036;">'
            .'<strong>{{task_count}}</strong> công việc.</p>';
    }
}
