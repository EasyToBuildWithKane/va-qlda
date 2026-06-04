@php
    $schoolHeader = mb_strtoupper((string) ($vars['school_name'] ?? 'Hệ Thống Trường Việt Mỹ'), 'UTF-8');
    $deptHeader = mb_strtoupper((string) ($vars['department_header'] ?? 'Phòng Công Nghệ'), 'UTF-8');
@endphp

<p class="form-code">{{ $vars['form_code'] }}</p>

<table class="doc-header">
    <tr>
        <td class="cell-left">
            {{ $schoolHeader }}<br>
            <span class="unit">—<br>{{ $deptHeader }}</span>
        </td>
        <td class="cell-right">
            <div class="republic">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
            <div class="motto">
                <span class="motto-line">Độc lập – Tự do – Hạnh phúc</span>
            </div>
            <div class="doc-date">{{ $vars['doc_date'] }}</div>
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
    <table class="field-lines">
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
