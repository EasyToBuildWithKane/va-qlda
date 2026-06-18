@php
    $mineUrl = $proposal['mine_url'] ?? url('/congnghe/de-xuat-cua-toi');
@endphp

<p style="margin:0 0 8px;font-size:11px;font-weight:600;letter-spacing:0.12em;text-transform:uppercase;color:#9A0036;">
    Thông báo trạng thái đề xuất
</p>
<p style="margin:0 0 20px;font-size:20px;font-weight:700;color:#0f172a;line-height:1.3;">
    {{ $proposal['title'] }}
</p>
@if(!empty($proposal['reference_code']))
<p style="margin:0 0 16px;font-size:13px;color:#64748b;">
    Mã tham chiếu: <strong style="color:#9A0036;">{{ $proposal['reference_code'] }}</strong>
</p>
@endif

<p style="margin:0 0 16px;font-size:15px;color:#334155;">
    Xin chào <strong>{{ $proposal['name'] }}</strong>,
    Phòng Công Nghệ đã xem xét đề xuất của bạn và cập nhật trạng thái:
    <strong style="color:#be123c;">{{ $proposal['status_label'] ?? 'Từ chối' }}</strong>.
</p>

<div style="margin-bottom:20px;padding:16px 18px;background:#fff1f2;border:1px solid #fecdd3;border-radius:10px;">
    <p style="margin:0 0 10px;font-size:12px;font-weight:600;letter-spacing:0.08em;text-transform:uppercase;color:#be123c;">
        Lý do từ chối
    </p>
    <div style="font-size:15px;line-height:1.6;color:#881337;white-space:pre-wrap;">{{ $proposal['rejection_reason'] }}</div>
</div>

<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border:1px solid #e2e8f0;border-radius:10px;border-collapse:separate;border-spacing:0;margin-bottom:20px;">
    <tr>
        <td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;color:#64748b;font-size:13px;width:130px;vertical-align:top;">Phòng ban</td>
        <td style="padding:12px 14px;border-bottom:1px solid #e2e8f0;font-size:14px;color:#0f172a;">{{ $proposal['department'] }}</td>
    </tr>
    <tr>
        <td style="padding:12px 14px;color:#64748b;font-size:13px;vertical-align:top;">Thời điểm gửi</td>
        <td style="padding:12px 14px;font-size:14px;color:#0f172a;">{{ $proposal['submitted_at'] }}</td>
    </tr>
</table>

<p style="margin:0;font-size:14px;">
    <a href="{{ $mineUrl }}" style="display:inline-block;padding:10px 18px;background:#9A0036;color:#ffffff;text-decoration:none;border-radius:8px;font-weight:600;font-size:14px;">
        Xem đề xuất đã gửi
    </a>
</p>

<p style="margin:20px 0 0;font-size:12px;color:#94a3b8;line-height:1.5;">
    Bạn có thể chỉnh sửa nội dung và gửi đề xuất mới qua cổng Phòng Công Nghệ nếu cần.
</p>
