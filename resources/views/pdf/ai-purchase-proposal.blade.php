<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4;
            margin: 40mm 15mm 22mm 20mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Serif', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.35;
            color: #000;
            margin: 0;
            padding: 0;
        }

        .doc-content {
            width: 100%;
            max-width: 170mm;
            margin: 0 auto;
        }

        .doc-content p {
            margin: 0.5pt 0;
        }

        .page-bg {
            position: fixed;
            top: -40mm;
            left: -20mm;
            width: 210mm;
            height: 297mm;
            z-index: -1;
        }
        .page-bg img {
            width: 100%;
            height: 100%;
        }

        .doc-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 3pt;
            border: 1pt solid #000;
        }
        .doc-header td {
            padding: 3pt 5pt;
            vertical-align: top;
            border: 1pt solid #000;
        }
        .doc-header .cell-left {
            width: 42%;
            font-size: 11pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .doc-header .cell-left .unit {
            font-weight: normal;
            font-size: 10pt;
        }
        .doc-header .cell-right {
            width: 58%;
            text-align: center;
        }
        .doc-header .cell-right .republic {
            font-weight: bold;
            font-size: 11.5pt;
            text-transform: uppercase;
        }
        .doc-header .cell-right .motto {
            font-size: 10.5pt;
        }
        .doc-header .cell-right .doc-date {
            font-size: 10pt;
            font-style: italic;
            margin-top: 1pt;
        }
        .doc-header .cell-right .underline {
            display: inline-block;
            border-bottom: 1px solid #000;
            padding-bottom: 0.5pt;
        }

        .doc-title {
            text-align: center;
            margin: 4pt 0 2pt 0;
        }
        .doc-title h1 {
            font-size: 14pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 0.5pt;
        }
        .doc-title .subtitle {
            font-size: 10pt;
            font-style: italic;
        }

        .kinh-gui {
            margin: 3pt 0 1pt 24pt;
        }
        .kinh-gui .label { font-style: italic; font-weight: bold; }

        .section {
            margin: 2pt 0 1pt 0;
        }
        .section-num {
            font-weight: bold;
            margin: 0;
        }
        .indent {
            margin-left: 16pt;
        }
        .indent2 {
            margin-left: 32pt;
        }
        .bold { font-weight: bold; }
        .italic { font-style: italic; }
        .underline { text-decoration: underline; }
        .center { text-align: center; }

        table.budget {
            width: 100%;
            border-collapse: collapse;
            margin: 2pt 0 3pt 0;
            font-size: 10pt;
        }
        table.budget th,
        table.budget td {
            border: 1pt solid #000;
            padding: 3pt 4pt;
            vertical-align: middle;
        }
        table.budget th {
            font-weight: bold;
            text-align: center;
            background-color: #f5f5f5;
        }
        table.budget td.center { text-align: center; }

        .checkbox-option {
            display: inline-block;
            margin-right: 14pt;
            vertical-align: middle;
            white-space: nowrap;
        }
        .checkbox-img {
            width: 12pt;
            height: 12pt;
            vertical-align: middle;
            margin-right: 3pt;
        }
        .checkbox-empty {
            display: inline-block;
            width: 12pt;
            height: 12pt;
            border: 1pt solid #000;
            vertical-align: middle;
            margin-right: 3pt;
        }

        table.sig {
            width: 100%;
            border-collapse: collapse;
            margin-top: 8pt;
        }
        table.sig td {
            border: 1pt dotted #666;
            padding: 5pt 6pt;
            vertical-align: top;
            text-align: center;
            font-size: 10pt;
            width: 33.33%;
        }
        table.sig .sig-title { font-weight: bold; }
        table.sig .sig-name { margin-top: 42pt; font-weight: bold; }

        .pre { white-space: pre-wrap; }

        ul.objectives {
            margin: 1pt 0 1pt 18pt;
            padding: 0;
        }
        ul.objectives li {
            margin-bottom: 0.5pt;
        }

        .closing {
            margin-top: 4pt;
            font-style: italic;
            font-size: 10.5pt;
        }
    </style>
</head>
<body>

    <div class="page-bg">
        <img src="{{ 'file://'.public_path('docx/background.png') }}" alt="">
    </div>

    @php
        $checkboxImg = 'file://'.public_path('docx/checkbox.png');
    @endphp

    <div class="doc-content">

    <table class="doc-header">
        <tr>
            <td class="cell-left">
                HỆ THỐNG TRƯỜNG VIỆT MỸ<br>
                <span class="unit">—<br>PHÒNG CÔNG NGHỆ</span>
            </td>
            <td class="cell-right">
                <div class="republic">CỘNG HÒA XÃ HỘI CHỦ NGHĨA VIỆT NAM</div>
                <div class="motto">
                    <span class="underline">Độc lập – Tự do – Hạnh phúc</span>
                </div>
                <div class="doc-date">{{ $vars['doc_date'] }}</div>
            </td>
        </tr>
    </table>

    <div class="doc-title">
        <h1>PHIẾU ĐỀ XUẤT</h1>
        <p class="subtitle">(Về việc: {{ $vars['subject_about'] }})</p>
    </div>

    <div class="kinh-gui">
        <span class="label">Kính gửi:</span>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $vars['send_to_part1'] }}<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $vars['send_to_part2'] }}
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
        <div class="indent">
            <p class="pre">{{ $vars['proposal_content'] }}</p>
        </div>
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
        <div class="indent2">
            <p><span class="bold">Họ &amp; Tên:</span> {{ $vars['recipient_name'] }}</p>
            <p><span class="bold">Chức vụ:</span> {{ $vars['recipient_position'] }}</p>
            <p><span class="bold">Email:</span> {{ $vars['recipient_email'] }}</p>
            <p><span class="bold">Số điện thoại:</span> {{ $vars['recipient_phone'] }}</p>
        </div>

        <p class="indent">
            <span class="bold">4.4 Tình trạng:</span>&nbsp;
            <span class="checkbox-option">
                @if($vars['check_new'] === '☑')
                    <img src="{{ $checkboxImg }}" class="checkbox-img" alt="">
                @else
                    <span class="checkbox-empty"></span>
                @endif
                Mua mới
            </span>
            <span class="checkbox-option">
                @if($vars['check_renewal'] === '☑')
                    <img src="{{ $checkboxImg }}" class="checkbox-img" alt="">
                @else
                    <span class="checkbox-empty"></span>
                @endif
                Gia hạn
            </span>
        </p>

        <p class="indent">
            <span class="bold">4.5 Thông tin bổ sung:</span><br>
            <span class="indent2">Email đăng ký tài khoản: {{ $vars['registration_email'] }}</span>
        </p>
    </div>

    <div class="section">
        <p class="section-num">
            5. Thời gian đưa vào sử dụng (dự kiến):
            <span style="color: #cc0000; font-weight: bold;">{{ $vars['planned_use_date'] }}</span>
        </p>
    </div>

    <p class="closing">
        Kính trình Ban Lãnh Đạo xem xét và phê duyệt.
    </p>

    <table class="sig">
        <tr>
            <td>
                <div class="sig-title">Người đề xuất</div>
                <div class="sig-name">{{ $vars['proposer_name'] }}</div>
            </td>
            <td style="border: 1pt dotted #666;"></td>
            <td>
                <div class="sig-title">Phòng Công nghệ</div>
                <div class="sig-title">Trưởng phòng</div>
                <div class="sig-name">Bùi Quang Toàn</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="sig-title">Phòng Kế toán</div>
                <div class="sig-title">Kế Toán trưởng</div>
                <div class="sig-name">Trần Thị Tình</div>
            </td>
            <td colspan="2" style="border: 1pt dotted #666;">
                <div class="sig-title">Phó Tổng Giám đốc</div>
                <div class="sig-title">Thường trực</div>
                <div class="sig-name">Nguyễn Ngọc Hiển</div>
            </td>
        </tr>
    </table>

    </div>

</body>
</html>
