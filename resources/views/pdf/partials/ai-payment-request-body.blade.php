<p class="pr-form-code">{{ $vars['form_code'] }}</p>

<div class="pr-a4-header">
    <img src="{{ 'file://'.public_path('docx/header.png') }}" class="pr-header-banner" alt="">
</div>
<p class="pr-dept-line">{{ $vars['department_header'] }}</p>

<table class="pr-header-meta">
    <tr>
        <td class="pr-meta-spacer"></td>
        <td class="cell-right">
            <div class="republic">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
            <div class="motto">
                <span class="motto-line">Độc lập – Tự do – Hạnh phúc</span>
            </div>
            <div class="doc-date">TP. HCM, ngày {{ $vars['doc_day'] }} tháng {{ $vars['doc_month'] }} năm {{ $vars['doc_year'] }}</div>
        </td>
    </tr>
</table>

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
    <table class="pr-detail-fields">
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
