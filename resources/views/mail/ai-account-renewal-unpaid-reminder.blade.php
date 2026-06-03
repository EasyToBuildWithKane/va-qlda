<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Chưa thanh toán gia hạn tài khoản AI</title>
</head>
<body style="font-family: system-ui, -apple-system, Segoe UI, sans-serif; line-height: 1.5; color: #1e293b; max-width: 520px; margin: 0 auto; padding: 24px;">
    <p style="font-size: 18px; font-weight: 600; margin: 0 0 16px; color: #be123c;">🔴 Đã hết hạn — chưa thanh toán gia hạn</p>

    <p style="font-size: 14px; margin: 0 0 16px;">
        License đã hết hạn nhưng chi phí gia hạn có thể vẫn phát sinh. Vui lòng thanh toán và cập nhật trạng thái trên hệ thống.
    </p>

    <table style="width: 100%; border: 1px solid #e2e8f0; border-radius: 8px; border-collapse: collapse;">
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b; width: 130px;">Công cụ</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;"><strong>{{ $account->tool_name }}</strong></td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Hết hạn</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0;">
                {{ $account->expiry_date->format('d/m/Y') }} (quá {{ $daysOver }} ngày)
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #64748b;">Thanh toán</td>
            <td style="padding: 10px 12px; border-bottom: 1px solid #e2e8f0; color: #b45309; font-weight: 600;">Chưa thanh toán</td>
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
        <a href="{{ url('/ai-accounts') }}" style="color: #9A0036; font-weight: 600;">Mở Quản lý AI → đánh dấu «Đã thanh toán»</a>
    </p>
</body>
</html>
