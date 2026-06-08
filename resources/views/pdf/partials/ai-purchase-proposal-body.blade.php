@php
    $sendToExtra = array_values(array_filter(array_map(
        trim(...),
        preg_split('/\r\n|\r|\n/', (string) ($vars['send_to_part2'] ?? '')) ?: [],
    )));
@endphp

@include('pdf.partials.doc-header-bordered', [
    'formCode' => $vars['form_code'] ?? '',
    'schoolName' => $vars['school_name'] ?? null,
    'departmentHeader' => $vars['department_header'] ?? null,
    'docDate' => $vars['doc_date'] ?? null,
])

<div class="doc-title">
    <h1>PHIẾU ĐỀ XUẤT</h1>
    <p class="subtitle">(Về việc: {{ $vars['subject_about'] }})</p>
</div>

<div class="kinh-gui">
    <span class="label">Kính gửi:</span>
    &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $vars['send_to_part1'] }}<br>
    @foreach($sendToExtra as $line)
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $line }}<br>
    @endforeach
</div>

<div class="section">
    <p class="section-num">1. Đại diện:</p>
    <div class="indent">
        <p><span class="bold">Họ &amp; Tên:</span> {{ $vars['proposer_name'] }}</p>
        <p><span class="bold">Chức vụ:</span> {{ $vars['proposer_position'] }}</p>
        <p><span class="bold">Đơn vị / Phòng ban:</span> {{ $vars['proposer_department'] }}</p>
    </div>
</div>

<div class="section">
    <p class="section-num">2. Nội dung đề xuất:</p>
    <table class="doc-detail-fields">
        <tr>
            <td class="field-label">Nội dung đề xuất:</td>
            <td class="field-value pre">{{ $vars['proposal_content'] }}</td>
        </tr>
    </table>
</div>

@if(!empty($vars['objectives']))
<div class="section">
    <p class="section-num">3. Mục tiêu:</p>
    <div class="indent">
        @php
            $objectiveLines = array_filter(explode("\n", trim($vars['objectives'])));
        @endphp
        @if(count($objectiveLines) > 1)
            <ul class="objectives">
                @foreach($objectiveLines as $line)
                    <li>{{ trim($line, "- •·") }}</li>
                @endforeach
            </ul>
        @else
            <p class="pre">{{ $vars['objectives'] }}</p>
        @endif
    </div>
</div>
@endif

<div class="section">
    <p class="section-num">4. Thông tin chi tiết:</p>

    <p class="indent bold">4.1 Ngân sách dự kiến:</p>
    <table class="budget">
        <thead>
            <tr>
                <th style="width:8%">STT</th>
                <th style="width:38%">Sản phẩm / Công cụ</th>
                <th style="width:12%">Số lượng</th>
                <th style="width:22%">Thành tiền (VNĐ/Tháng)</th>
                <th style="width:20%">Ghi chú</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td class="center">1</td>
                <td>{{ $vars['tool_product_line'] }}</td>
                <td class="center">{{ $vars['quantity'] }}</td>
                <td class="center">~ {{ $vars['cost_monthly_formatted'] }}</td>
                <td></td>
            </tr>
        </tbody>
    </table>

    <p class="indent"><span class="bold">4.2 Số lượng nhân sự sử dụng:</span> {{ $vars['staff_count_line'] }}</p>

    <p class="indent bold">4.3 Nhân sự tiếp nhận:</p>
    <table class="field-lines">
        <tr>
            <td class="field-label">Họ &amp; Tên:</td>
            <td class="field-value">{{ $vars['recipient_name'] }}</td>
        </tr>
        <tr>
            <td class="field-label">Chức vụ:</td>
            <td class="field-value">{{ $vars['recipient_position'] }}</td>
        </tr>
        <tr>
            <td class="field-label">Email:</td>
            <td class="field-value">{{ $vars['recipient_email'] }}</td>
        </tr>
        <tr>
            <td class="field-label">Số điện thoại:</td>
            <td class="field-value">{{ $vars['recipient_phone'] }}</td>
        </tr>
    </table>

    <p class="indent bold">4.4 Tình trạng:</p>
    <table class="status-checks">
        <tr>
            <td class="status-opt">
                <table class="status-inner">
                    <tr>
                        <td class="status-box">
                            @if($vars['check_new'] === '☑')
                                <img src="{{ $checkboxImg }}" class="checkbox-img" alt="">
                            @else
                                <span class="checkbox-empty"></span>
                            @endif
                        </td>
                        <td class="status-text">Mua mới</td>
                    </tr>
                </table>
            </td>
            <td class="status-opt">
                <table class="status-inner">
                    <tr>
                        <td class="status-box">
                            @if($vars['check_renewal'] === '☑')
                                <img src="{{ $checkboxImg }}" class="checkbox-img" alt="">
                            @else
                                <span class="checkbox-empty"></span>
                            @endif
                        </td>
                        <td class="status-text">Gia hạn</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(!empty($vars['registration_email_slots']))
    <p class="indent bold">4.5 Thông tin bổ sung:</p>
    @foreach($vars['registration_email_slots'] as $idx => $slot)
    <p class="indent2">
        Email đăng ký tài khoản@if(count($vars['registration_email_slots']) > 1) (nhân sự {{ $idx + 1 }})@endif:
        {{ $slot }}
    </p>
    @endforeach
    @endif
</div>

<div class="doc-page-footer">
<div class="section section-5">
    <p class="section-num">
        5. Thời gian đưa vào sử dụng (dự kiến):
        <span class="planned-date">{{ $vars['planned_use_date'] }}</span>
    </p>
</div>

<p class="closing">
    Kính trình Ban Lãnh Đạo xem xét và phê duyệt.
</p>

<table class="sig">
    <tr>
        <td>
            <div class="sig-title">Người đề xuất</div>
            <div class="sig-space"></div>
            <div class="sig-name">{{ $vars['proposer_name'] }}</div>
        </td>
        <td></td>
        <td>
            <div class="sig-title">Phòng Công nghệ</div>
            <div class="sig-title">Trưởng phòng</div>
            <div class="sig-space"></div>
            <div class="sig-name">Bùi Quang Toàn</div>
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
