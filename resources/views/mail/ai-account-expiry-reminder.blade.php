<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Tài khoản AI sắp hết hạn</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, sans-serif; line-height: 1.5; color: #1e293b; max-width: 520px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: 600; margin: 0 0 16px;">⚠️ Tài khoản AI sắp hết hạn</p>

    <table style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; width: 120px;">Công cụ</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;"><strong>{{ $account->tool_name }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Nhóm</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">{{ $account->group_function->value }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b;">License</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">{{ $account->license_type }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Hết hạn</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">
                {{ $account->expiry_date->format('d/m/Y') }} (còn {{ $daysLeft }} ngày)
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Chi phí</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">{{ $costLine }}</td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; color: #64748b;">Email TK</td>
            <td style="padding: 10px 12px;">{{ $account->email_registered }}</td>
        </tr>
    </table>

    <p style="margin-top: 20px; font-size: 14px;">
        <a href="{{ url('/ai-accounts') }}" style="color: #9A0036;">Mở Quản lý tài khoản AI</a>
    </p>

    <p style="margin-top: 24px; font-size: 12px; color: #94a3b8;">
        {{ config('app.name') }} — thông báo tự động
    </p>
</body>
</html>
