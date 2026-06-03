<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12pt; line-height: 1.45; color: #111; }
        .center { text-align: center; }
        .bold { font-weight: bold; }
        .mt { margin-top: 12px; }
        .section-title { font-weight: bold; margin-top: 14px; }
        table.budget { width: 100%; border-collapse: collapse; margin-top: 8px; }
        table.budget th, table.budget td { border: 1px solid #333; padding: 6px 8px; font-size: 11pt; }
        table.budget th { background: #f1f5f9; }
        .pre { white-space: pre-wrap; }
    </style>
</head>
<body>
    <p class="center bold">HỆ THỐNG TRƯỜNG VIỆT MỸ — PHÒNG CÔNG NGHỆ</p>
    <p class="center bold mt">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</p>
    <p class="center">Độc lập – Tự do – Hạnh phúc</p>
    <p class="center">{{ $vars['doc_date'] }}</p>
    <p class="center bold mt" style="font-size: 14pt;">PHIẾU ĐỀ XUẤT</p>
    <p class="center">(Về việc: {{ $vars['subject_about'] }})</p>

    <p class="mt"><span class="bold">Kính gửi:</span> {{ $vars['send_to_part1'] }}<br>
        {{ $vars['send_to_part2'] }}</p>

    <p class="section-title">Đại diện:</p>
    <p>Họ &amp; Tên: {{ $vars['proposer_name'] }}<br>
        Chức vụ: {{ $vars['proposer_position'] }}<br>
        Đơn vị / Phòng ban: {{ $vars['proposer_department'] }}</p>

    <p class="section-title">Nội dung đề xuất:</p>
    <p class="pre">{{ $vars['proposal_content'] }}</p>

    <p class="section-title">Mục tiêu:</p>
    <p class="pre">{{ $vars['objectives'] }}</p>

    <p class="section-title">Thông tin chi tiết:</p>
    <p class="bold">4.1 Ngân sách dự kiến:</p>
    <table class="budget">
        <thead>
            <tr>
                <th>STT</th>
                <th>Sản phẩm / Công cụ</th>
                <th>Số lượng</th>
                <th>Thành tiền (VNĐ/Tháng)</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>1</td>
                <td>{{ $vars['tool_product_line'] }}</td>
                <td>{{ $vars['quantity'] }}</td>
                <td>{{ $vars['cost_monthly_formatted'] }}</td>
            </tr>
        </tbody>
    </table>
    <p>4.2 Số lượng nhân sự sử dụng: {{ $vars['staff_count_line'] }}</p>
    <p class="bold">4.3 Nhân sự tiếp nhận:</p>
    <p>Họ &amp; Tên: {{ $vars['recipient_name'] }}<br>
        Chức vụ: {{ $vars['recipient_position'] }}<br>
        Email: {{ $vars['recipient_email'] }}<br>
        Số điện thoại: {{ $vars['recipient_phone'] }}</p>
    <p>4.4 Tình trạng: {{ $vars['check_new'] }} Mua mới &nbsp;&nbsp; {{ $vars['check_renewal'] }} Gia hạn</p>
    <p>4.5 Thông tin bổ sung — Email đăng ký: {{ $vars['registration_email'] }}</p>
    <p>Thời gian đưa vào sử dụng (dự kiến): {{ $vars['planned_use_date'] }}</p>
    <p class="mt">Kính trình Ban Lãnh Đạo xem xét và phê duyệt.</p>
</body>
</html>
