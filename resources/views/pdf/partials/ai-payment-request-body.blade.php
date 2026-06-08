@include('pdf.partials.doc-header-bordered', [
    'formCode' => $vars['form_code'] ?? '',
    'schoolName' => $vars['school_name'] ?? null,
    'departmentHeader' => $vars['department_header'] ?? null,
    'docDate' => $vars['doc_date'] ?? null,
])

<div class="doc-title">
    <h1>GIẤY ĐỀ NGHỊ THANH TOÁN</h1>
    <p class="subtitle">Số: {{ $vars['doc_number'] }}</p>
</div>

<div class="kinh-gui">
    <span class="label">Kính gửi:</span>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $vars['send_to'] }}
</div>

<div class="section">
    <p class="section-num">1. Người đề nghị:</p>
    <div class="indent">
        <p><span class="bold">Họ và tên:</span> {{ $vars['proposer_name'] }}</p>
        <p><span class="bold">Đơn vị / Bộ phận:</span> {{ $vars['proposer_department'] }}</p>
    </div>
</div>

<div class="section">
    <p class="section-num">2. Nội dung thanh toán:</p>
    <table class="doc-detail-fields">
        <tr>
            <td class="field-label">Nội dung thanh toán:</td>
            <td class="field-value pre">{{ $vars['payment_content'] }}</td>
        </tr>
        <tr>
            <td class="field-label">Số tiền:</td>
            <td class="field-value bold">{{ $vars['amount_formatted'] }}</td>
        </tr>
        <tr>
            <td class="field-label">Viết bằng chữ:</td>
            <td class="field-value italic">{{ $vars['amount_in_words'] }}</td>
        </tr>
        <tr>
            <td class="field-label">Thời gian thanh toán:</td>
            <td class="field-value">{{ $vars['payment_date'] }}</td>
        </tr>
        <tr>
            <td class="field-label">Hình thức thanh toán:</td>
            <td class="field-value">{{ $vars['payment_method'] }}</td>
        </tr>
        <tr>
            <td class="field-label">Số nhân sự sử dụng:</td>
            <td class="field-value">{{ $vars['staff_count_line'] ?? '—' }}</td>
        </tr>
        @if(!empty($vars['registration_emails_line']) && $vars['registration_emails_line'] !== '—')
        <tr>
            <td class="field-label">Email đăng ký TK:</td>
            <td class="field-value">{{ $vars['registration_emails_line'] }}</td>
        </tr>
        @endif
    </table>
</div>

<div class="doc-page-footer">
    <p class="closing">
        Kính trình Ban Lãnh Đạo xem xét và phê duyệt.
    </p>

    <table class="sig">
        <tr>
            <td>
                <div class="sig-title">Người đề nghị</div>
                <div class="sig-title">thanh toán</div>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $vars['proposer_name'] }}</div>
            </td>
            <td></td>
            <td>
                <div class="sig-title">Trưởng đơn vị</div>
                <div class="sig-space"></div>
                <div class="sig-name"></div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="sig-title">Phòng Kế toán</div>
                <div class="sig-title">Kế Toán trưởng</div>
                <div class="sig-space"></div>
                <div class="sig-name">Trần Thị Tình</div>
            </td>
            <td colspan="2">
                <div class="sig-title">Phó Tổng Giám đốc</div>
                <div class="sig-title">Thường trực</div>
                <div class="sig-space"></div>
                <div class="sig-name">Nguyễn Ngọc Hiển</div>
            </td>
        </tr>
    </table>
</div>