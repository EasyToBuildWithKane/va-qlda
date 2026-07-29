<?php

namespace App\Support\Mail;

/**
 * Wraps admin-editable template fragments in a branded HTML shell for email clients.
 */
final class EmailBrandLayout
{
    public static function wrap(string $innerHtml, ?string $preheader = null): string
    {
        $appName = (string) (config('task_email.from_name') ?: config('va.app_name', 'VAschools Workspace'));
        $year = date('Y');
        $preheaderHtml = $preheader !== null && $preheader !== ''
            ? '<div style="display:none;max-height:0;overflow:hidden;mso-hide:all;">'
                .e(strip_tags($preheader))
                .'</div>'
            : '';

        return '<!DOCTYPE html>'
            .'<html lang="vi"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            .'<title>'.e($appName).'</title></head>'
            .'<body style="margin:0;padding:0;background:#f1f5f9;font-family:system-ui,-apple-system,Segoe UI,sans-serif;color:#1e293b;">'
            .$preheaderHtml
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f1f5f9;padding:24px 12px;">'
            .'<tr><td align="center">'
            .'<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;">'
            .'<tr><td style="background:#9A0036;border-radius:12px 12px 0 0;padding:20px 24px;">'
            .'<p style="margin:0;font-size:18px;font-weight:700;color:#ffffff;letter-spacing:-0.02em;">'.e($appName).'</p>'
            .'<p style="margin:6px 0 0;font-size:12px;color:rgba(255,255,255,0.85);">Thông báo công việc · Workspace</p>'
            .'</td></tr>'
            .'<tr><td style="background:#ffffff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 12px 12px;padding:24px;font-size:15px;line-height:1.55;">'
            .$innerHtml
            .'</td></tr>'
            .'<tr><td style="padding:16px 8px 0;text-align:center;font-size:11px;color:#94a3b8;line-height:1.5;">'
            .'Email tự động từ '.e($appName).' · © '.$year.' VAschools'
            .'<br><span style="color:#cbd5e1;">Vui lòng không trả lời trực tiếp email này.</span>'
            .'</td></tr>'
            .'</table></td></tr></table></body></html>';
    }
}
