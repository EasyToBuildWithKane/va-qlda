<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        @page {
            size: A4;
            margin: 42mm 20mm 28mm 25mm;
        }

        * { box-sizing: border-box; }

        body {
            font-family: 'DejaVu Serif', 'Times New Roman', serif;
            font-size: 12pt;
            line-height: 1.5;
            color: #000;
            margin: 0;
            padding: 0;
        }

        /* Full-page background image repeated on every page */
        .page-bg {
            position: fixed;
            top: -42mm;
            left: -25mm;
            width: 210mm;
            height: 297mm;
            z-index: -1;
        }
        .page-bg img {
            width: 100%;
            height: 100%;
        }

        /* ─── Header section ─── */
        .doc-header {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 6pt;
        }
        .doc-header td {
            padding: 2pt 4pt;
            vertical-align: top;
        }
        .doc-header .cell-left {
            width: 42%;
            font-size: 12pt;
            font-weight: bold;
            text-align: center;
            text-transform: uppercase;
        }
        .doc-header .cell-left .unit {
            font-weight: normal;
            font-size: 11pt;
        }
        .doc-header .cell-right {
            width: 58%;
            text-align: center;
        }
        .doc-header .cell-right .republic {
            font-weight: bold;
            font-size: 13pt;
            text-transform: uppercase;
        }
        .doc-header .cell-right .motto {
            font-size: 12pt;
        }
        .doc-header .cell-right .doc-date {
            font-size: 11pt;
            font-style: italic;
            margin-top: 2pt;
        }
        .doc-header .cell-right .underline {
            display: inline-block;
            border-bottom: 1px solid #000;
            padding-bottom: 1pt;
        }

        /* ─── Title ─── */
        .doc-title {
            text-align: center;
            margin: 10pt 0 4pt 0;
        }
        .doc-title h1 {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            margin: 0;
            letter-spacing: 1pt;
        }
        .doc-title .subtitle {
            font-size: 11pt;
            font-style: italic;
        }

        /* ─── Body ─── */
        .kinh-gui {
            margin: 10pt 0 0 30pt;
        }
        .kinh-gui .label { font-style: italic; font-weight: bold; }

        .section {
            margin: 6pt 0 4pt 0;
        }
        .section-num {
            font-weight: bold;
            margin-bottom: 2pt;
        }
        .indent {
            margin-left: 18pt;
        }
        .indent2 {
            margin-left: 36pt;
        }
        .bold { font-weight: bold; }
        .italic { font-style: italic; }
        .underline { text-decoration: underline; }
        .center { text-align: center; }

        /* ─── Budget table ─── */
        table.budget {
            width: 100%;
            border-collapse: collapse;
            margin: 6pt 0;
            font-size: 11pt;
        }
        table.budget th,
        table.budget td {
            border: 1pt solid #000;
            padding: 5pt 6pt;
            vertical-align: middle;
        }
        table.budget th {
            font-weight: bold;
            text-align: center;
            background-color: #f5f5f5;
        }
        table.budget td.center { text-align: center; }
        table.budget td.right { text-align: right; }

        /* ─── Checkbox ─── */
        .checkbox-row {
            display: inline-block;
            margin-right: 20pt;
            vertical-align: middle;
        }
        .checkbox-row img {
            width: 14pt;
            height: 14pt;
            vertical-align: middle;
            margin-right: 3pt;
        }
        .check-mark {
            display: inline-block;
            width: 14pt;
            height: 14pt;
            border: 1pt solid #000;
            text-align: center;
            line-height: 14pt;
            vertical-align: middle;
            font-size: 11pt;
            margin-right: 3pt;
        }

        /* ─── Signature table ─── */
        table.sig {
            width: 100%;
            border-collapse: collapse;
            margin-top: 16pt;
        }
        table.sig td {
            border: 1pt dotted #666;
            padding: 8pt 10pt;
            vertical-align: top;
            text-align: center;
            font-size: 11pt;
            width: 33.33%;
        }
        table.sig .sig-title { font-weight: bold; }
        table.sig .sig-name { margin-top: 50pt; font-weight: bold; }

        .pre { white-space: pre-wrap; }

        ul.objectives {
            margin: 4pt 0 4pt 20pt;
            padding: 0;
        }
        ul.objectives li {
            margin-bottom: 2pt;
        }
    </style>
</head>
<body>

    {{-- Full-page background (appears on every page) --}}
    <div class="page-bg">
        <img src="{{ 'file://'.public_path('docx/background.png') }}" alt="">
    </div>

    {{-- ─── Header table ─── --}}
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

    {{-- ─── Title ─── --}}
    <div class="doc-title">
        <h1>PHIẾU ĐỀ XUẤT</h1>
        <p class="subtitle">(Về việc: {{ $vars['subject_about'] }})</p>
    </div>

    {{-- ─── Kính gửi ─── --}}
    <div class="kinh-gui">
        <span class="label">Kính gửi:</span>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $vars['send_to_part1'] }}<br>
        &nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;&nbsp;{{ $vars['send_to_part2'] }}
    </div>

    {{-- ─── 1. Đại diện ─── --}}
    <div class="section">
        <p class="section-num">1. Đại diện:</p>
        <div class="indent">
            <p><span class="bold">Họ &amp; Tên:</span> {{ $vars['proposer_name'] }}</p>
            <p><span class="bold">Chức vụ:</span> {{ $vars['proposer_position'] }}</p>
            <p><span class="bold">Đơn vị / Phòng ban:</span> {{ $vars['proposer_department'] }}</p>
        </div>
    </div>

    {{-- ─── 2. Nội dung đề xuất ─── --}}
    <div class="section">
        <p class="section-num">2. Nội dung đề xuất:</p>
        <div class="indent">
            <p class="pre">{{ $vars['proposal_content'] }}</p>
        </div>
    </div>

    {{-- ─── 3. Mục tiêu ─── --}}
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

    {{-- ─── 4. Thông tin chi tiết ─── --}}
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
            <span class="checkbox-row">
                <img src="{{ 'file://'.public_path('docx/checkbox.png') }}" alt="">
                @if($vars['check_new'] === '☑') <strong>✓</strong>&nbsp; @endif
                Mua mới
            </span>
            <span class="checkbox-row">
                <img src="{{ 'file://'.public_path('docx/checkbox.png') }}" alt="">
                @if($vars['check_renewal'] === '☑') <strong>✓</strong>&nbsp; @endif
                Gia hạn
            </span>
        </p>

        <p class="indent">
            <span class="bold">4.5 Thông tin bổ sung:</span><br>
            <span class="indent2">Email đăng ký tài khoản: {{ $vars['registration_email'] }}</span>
        </p>
    </div>

    {{-- ─── 5. Thời gian ─── --}}
    <div class="section">
        <p class="section-num">
            5. Thời gian đưa vào sử dụng (dự kiến):
            <span style="color: #cc0000; font-weight: bold;">{{ $vars['planned_use_date'] }}</span>
        </p>
    </div>

    {{-- ─── Closing ─── --}}
    <p style="margin-top: 10pt; font-style: italic;">
        Kính trình Ban Lãnh Đạo xem xét và phê duyệt.
    </p>

    {{-- ─── Signature table ─── --}}
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

</body>
</html>
