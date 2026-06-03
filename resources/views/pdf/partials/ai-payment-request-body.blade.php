<p class="form-code">{{ $vars['form_code'] }}</p>

<table class="meta-block">
    <tr>
        <td class="meta-label">Đơn vị:</td>
        <td class="meta-value">"{{ $vars['company_unit'] }}"</td>
    </tr>
    <tr>
        <td class="meta-label">Bộ phận:</td>
        <td class="meta-value">{{ $vars['department_header'] }}</td>
    </tr>
</table>

<div class="doc-title doc-title-payment">
    <h1>GIẤY ĐỀ NGHỊ THANH TOÁN</h1>
</div>

<p class="doc-date-line">
    Ngày
    <span class="date-num">{{ $vars['doc_day'] }}</span>
    tháng
    <span class="date-num">{{ $vars['doc_month'] }}</span>
    năm
    <span class="date-num">{{ $vars['doc_year'] }}</span>
</p>

<div class="kinh-gui-row">
    <span class="doc-no">Số: {{ $vars['doc_number'] }}</span>
    <span class="bold">Kính gửi:</span>
    {{ $vars['send_to'] }}
</div>

<table class="payment-fields">
    <tr>
        <td class="field-label">Họ và tên người đề nghị:</td>
        <td class="field-value">{{ $vars['proposer_name'] }}</td>
    </tr>
    <tr>
        <td class="field-label">Bộ phận (hoặc địa chỉ):</td>
        <td class="field-value">{{ $vars['proposer_department'] }}</td>
    </tr>
    <tr>
        <td class="field-label">Nội dung thanh toán:</td>
        <td class="field-value">{{ $vars['payment_content'] }}</td>
    </tr>
    <tr>
        <td class="field-label">Số tiền:</td>
        <td class="field-value">{{ $vars['amount_formatted'] }}</td>
    </tr>
    <tr>
        <td class="field-label">Viết bằng chữ:</td>
        <td class="field-value">{{ $vars['amount_in_words'] }}</td>
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

<table class="sig-payment">
    <tr>
        <td>
            <div class="sig-title">Người đề nghị thanh toán</div>
            <div class="sig-space"></div>
            <div class="sig-caption">(Ký, họ tên)</div>
        </td>
        <td>
            <div class="sig-title">Trưởng đơn vị</div>
            <div class="sig-space"></div>
            <div class="sig-caption">(Ký, họ tên)</div>
        </td>
        <td>
            <div class="sig-title">P. Kế toán</div>
            <div class="sig-space"></div>
            <div class="sig-caption">(Ký, họ tên)</div>
        </td>
        <td>
            <div class="sig-title">P. Tổng giám đốc</div>
            <div class="sig-space"></div>
            <div class="sig-caption">(Ký, họ tên)</div>
        </td>
    </tr>
</table>
