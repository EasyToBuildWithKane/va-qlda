<!DOCTYPE html>
<html lang="vi">
<head>
    <meta charset="utf-8">
    <style>
        /* ════════════════════════════════════════
           VAS – Phiếu Đề Xuất  |  Print-ready A4
           Palette: đen / xám / trắng + 1 accent
        ════════════════════════════════════════ */

        @page {
            size: A4;
            margin: 25mm 20mm 28mm 25mm;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Serif', 'Times New Roman', serif;
            font-size: 11pt;
            line-height: 1.65;
            color: #111;
            background: #fff;
        }

        /* ── Tiêu đề tổ chức ──────────────────── */
        .header-wrap {
            display: table;
            width: 100%;
            border-bottom: 2pt solid #111;
            padding-bottom: 10pt;
            margin-bottom: 14pt;
        }
        .header-left,
        .header-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .header-right { text-align: center; }

        .org-name {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
            line-height: 1.4;
        }
        .org-unit {
            font-size: 10.5pt;
            font-style: italic;
            margin-top: 1pt;
            text-decoration: underline;
        }
        .republic {
            font-size: 11pt;
            font-weight: bold;
            text-transform: uppercase;
        }
        .motto {
            font-size: 11pt;
            font-style: italic;
            margin-top: 1pt;
            text-decoration: underline;
        }
        .doc-date {
            font-size: 10.5pt;
            font-style: italic;
            margin-top: 5pt;
        }

        /* ── Tiêu đề văn bản ──────────────────── */
        .title-block {
            text-align: center;
            margin: 0 0 16pt;
        }
        .doc-title {
            font-size: 16pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 2pt;
        }
        .doc-subtitle {
            font-size: 11pt;
            font-style: italic;
            margin-top: 4pt;
        }
        /* gạch chân trang trí dưới tiêu đề */
        .title-rule {
            width: 64pt;
            height: 2pt;
            background: #111;
            margin: 7pt auto 0;
        }

        /* ── Kính gửi ─────────────────────────── */
        .kinh-gui {
            margin-bottom: 14pt;
            padding: 7pt 10pt;
            border: 0.75pt solid #bbb;
            background: #f8f8f8;
        }
        .kinh-gui table { border-collapse: collapse; }
        .kg-label {
            font-weight: bold;
            font-size: 11pt;
            padding-right: 14pt;
            vertical-align: top;
            white-space: nowrap;
        }
        .kg-val {
            font-size: 11pt;
            line-height: 1.8;
        }

        /* ── Section ──────────────────────────── */
        .section { margin-bottom: 12pt; }
        .section-title {
            font-size: 11.5pt;
            font-weight: bold;
            border-bottom: 0.75pt solid #111;
            padding-bottom: 3pt;
            margin-bottom: 7pt;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }

        /* ── Bảng thông tin đại diện ───────────── */
        table.info-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 11pt;
        }
        table.info-table td {
            border: 0.75pt solid #bbb;
            padding: 6pt 10pt;
            vertical-align: top;
        }
        table.info-table td.lbl {
            font-weight: bold;
            font-size: 10pt;
            color: #444;
            width: 30%;
            background: #f5f5f5;
        }
        table.info-table td.val {
            width: 70%;
        }
        /* 3-col dành cho đại diện */
        table.info-3col { width: 100%; border-collapse: collapse; font-size: 11pt; }
        table.info-3col td {
            border: 0.75pt solid #bbb;
            padding: 6pt 10pt;
            vertical-align: top;
            width: 33.33%;
        }
        table.info-3col td.lbl {
            font-weight: bold;
            font-size: 9.5pt;
            color: #555;
            text-transform: uppercase;
            letter-spacing: 0.5pt;
            background: #f5f5f5;
        }

        /* ── Nội dung đề xuất ─────────────────── */
        .text-box {
            border: 0.75pt solid #bbb;
            padding: 9pt 12pt;
            font-size: 11pt;
            white-space: pre-wrap;
            line-height: 1.8;
            background: #fefefe;
        }

        /* ── Danh sách mục tiêu ───────────────── */
        ul.obj-list {
            list-style: none;
            padding: 0;
            border: 0.75pt solid #bbb;
        }
        ul.obj-list li {
            padding: 6pt 12pt;
            border-bottom: 0.5pt solid #ddd;
            font-size: 11pt;
        }
        ul.obj-list li:last-child { border-bottom: none; }
        ul.obj-list li::before {
            content: "—";
            margin-right: 8pt;
            color: #555;
        }

        /* ── Sub-section ──────────────────────── */
        .sub-title {
            font-size: 10.5pt;
            font-weight: bold;
            margin: 10pt 0 5pt;
            padding-left: 8pt;
            border-left: 3pt solid #111;
        }

        /* ── Bảng ngân sách ───────────────────── */
        table.budget {
            width: 100%;
            border-collapse: collapse;
            font-size: 10.5pt;
        }
        table.budget th {
            border: 0.75pt solid #888;
            background: #222;
            color: #fff;
            font-weight: bold;
            font-size: 9.5pt;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
            padding: 6pt 9pt;
            text-align: center;
        }
        table.budget td {
            border: 0.75pt solid #bbb;
            padding: 7pt 9pt;
            vertical-align: middle;
        }
        table.budget td.center { text-align: center; }
        table.budget td.money {
            text-align: center;
            font-weight: bold;
            font-size: 11.5pt;
        }

        /* ── Checkbox trạng thái ──────────────── */
        .cb-wrap { font-size: 11pt; }
        .cb {
            display: inline-block;
            width: 11pt; height: 11pt;
            border: 1pt solid #777;
            text-align: center;
            line-height: 10pt;
            font-size: 9pt;
            vertical-align: middle;
            margin-right: 3pt;
            background: #fff;
        }
        .cb.on {
            background: #111;
            border-color: #111;
            color: #fff;
        }
        .cb-lbl {
            vertical-align: middle;
            margin-right: 18pt;
        }

        /* ── Ngày dùng ────────────────────────── */
        .date-tag {
            display: inline-block;
            border: 1pt solid #111;
            padding: 3pt 14pt;
            font-weight: bold;
            font-size: 11.5pt;
            letter-spacing: 0.5pt;
            margin-top: 2pt;
        }

        /* ── Lời kết ──────────────────────────── */
        .closing {
            text-align: center;
            font-style: italic;
            font-size: 11pt;
            margin: 16pt 0 18pt;
            color: #333;
        }

        /* ── Bảng ký ──────────────────────────── */
        table.sig {
            width: 100%;
            border-collapse: collapse;
        }
        table.sig td {
            border: 0.75pt solid #bbb;
            padding: 10pt 12pt 12pt;
            text-align: center;
            vertical-align: top;
            width: 33.33%;
        }
        .sig-role {
            font-size: 10pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 0.4pt;
        }
        .sig-pos {
            font-size: 9.5pt;
            font-style: italic;
            color: #555;
            margin-top: 1pt;
        }
        .sig-space {
            height: 50pt;
        }
        .sig-note {
            font-size: 9.5pt;
            font-style: italic;
            color: #666;
            margin-top: 2pt;
        }
        .sig-name {
            font-size: 11pt;
            font-weight: bold;
            border-top: 0.75pt solid #999;
            padding-top: 5pt;
            margin-top: 4pt;
        }
    </style>
</head>
<body>

    {{-- ── HEADER ─────────────────────────────── --}}
    <div class="header-wrap">
        <div class="header-left">
            <div class="org-name">Hệ Thống Trường Việt Mỹ</div>
            <div class="org-unit">Phòng Công Nghệ</div>
        </div>
        <div class="header-right">
            <div class="republic">Cộng Hoà Xã Hội Chủ Nghĩa Việt Nam</div>
            <div class="motto">Độc lập – Tự do – Hạnh phúc</div>
            <div class="doc-date">{{ $vars['doc_date'] }}</div>
        </div>
    </div>

    {{-- ── TIÊU ĐỀ VĂN BẢN ──────────────────────── --}}
    <div class="title-block">
        <div class="doc-title">Phiếu Đề Xuất</div>
        <div class="doc-subtitle">Về việc: {{ $vars['subject_about'] }}</div>
        <div class="title-rule"></div>
    </div>

    {{-- ── KÍNH GỬI ───────────────────────────────── --}}
    <div class="kinh-gui">
        <table>
            <tr>
                <td class="kg-label">Kính gửi:</td>
                <td class="kg-val">
                    Ban Giám Đốc<br>
                    Phòng Công Nghệ &amp; Phòng Kế Toán
                </td>
            </tr>
        </table>
    </div>

    {{-- ── 1. ĐẠI DIỆN ────────────────────────────── --}}
    <div class="section">
        <div class="section-title">1. Đại Diện</div>
        <table class="info-3col">
            <tr>
                <td class="lbl">Họ &amp; Tên</td>
                <td class="lbl">Chức Vụ</td>
                <td class="lbl">Đơn Vị / Phòng Ban</td>
            </tr>
            <tr>
                <td>{{ $vars['proposer_name'] }}</td>
                <td>{{ $vars['proposer_position'] }}</td>
                <td>{{ $vars['proposer_department'] }}</td>
            </tr>
        </table>
    </div>

    {{-- ── 2. NỘI DUNG ĐỀ XUẤT ───────────────────── --}}
    <div class="section">
        <div class="section-title">2. Nội Dung Đề Xuất</div>
        <div class="text-box">{{ $vars['proposal_content'] }}</div>
    </div>

    {{-- ── 3. MỤC TIÊU (nếu có) ──────────────────── --}}
    @if(!empty($vars['objectives']))
    <div class="section">
        <div class="section-title">3. Mục Tiêu</div>
        @php $lines = array_filter(explode("\n", trim($vars['objectives']))); @endphp
        @if(count($lines) > 1)
            <ul class="obj-list">
                @foreach($lines as $line)
                    <li>{{ trim($line, '-–•·') }}</li>
                @endforeach
            </ul>
        @else
            <div class="text-box">{{ $vars['objectives'] }}</div>
        @endif
    </div>
    @endif

    {{-- ── 4. THÔNG TIN CHI TIẾT ─────────────────── --}}
    <div class="section">
        <div class="section-title">4. Thông Tin Chi Tiết</div>

        {{-- 4.1 Ngân sách --}}
        <div class="sub-title">4.1 Ngân sách dự kiến</div>
        <table class="budget">
            <thead>
                <tr>
                    <th style="width:6%">STT</th>
                    <th style="width:38%">Sản phẩm / Công cụ</th>
                    <th style="width:10%">SL</th>
                    <th style="width:28%">Thành tiền (VNĐ / Tháng)</th>
                    <th>Ghi chú</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td class="center">1</td>
                    <td>{{ $vars['tool_product_line'] }}</td>
                    <td class="center">{{ $vars['quantity'] }}</td>
                    <td class="money">~ {{ $vars['cost_monthly_formatted'] }}</td>
                    <td></td>
                </tr>
            </tbody>
        </table>

        {{-- 4.2 Nhân sự & Tình trạng --}}
        <div class="sub-title">4.2 Nhân sự &amp; Tình trạng</div>
        <table class="info-table">
            <tr>
                <td class="lbl">Số lượng nhân sự sử dụng</td>
                <td class="val">{{ $vars['staff_count_line'] }}</td>
            </tr>
            <tr>
                <td class="lbl">Tình trạng</td>
                <td class="val cb-wrap">
                    <span class="cb {{ $vars['check_new'] === '☑' ? 'on' : '' }}">{{ $vars['check_new'] === '☑' ? '✓' : '' }}</span>
                    <span class="cb-lbl">Mua mới</span>
                    <span class="cb {{ $vars['check_renewal'] === '☑' ? 'on' : '' }}">{{ $vars['check_renewal'] === '☑' ? '✓' : '' }}</span>
                    <span class="cb-lbl">Gia hạn</span>
                </td>
            </tr>
            <tr>
                <td class="lbl">Email đăng ký tài khoản</td>
                <td class="val">{{ $vars['registration_email'] }}</td>
            </tr>
        </table>

        {{-- 4.3 Nhân sự tiếp nhận --}}
        <div class="sub-title">4.3 Nhân sự tiếp nhận</div>
        <table class="info-3col">
            <tr>
                <td class="lbl">Họ &amp; Tên</td>
                <td class="lbl">Chức Vụ</td>
                <td class="lbl">Email</td>
            </tr>
            <tr>
                <td>{{ $vars['recipient_name'] }}</td>
                <td>{{ $vars['recipient_position'] }}</td>
                <td>{{ $vars['recipient_email'] }}</td>
            </tr>
            <tr>
                <td class="lbl" colspan="2">Số Điện Thoại</td>
                <td class="lbl"></td>
            </tr>
            <tr>
                <td colspan="2">{{ $vars['recipient_phone'] }}</td>
                <td></td>
            </tr>
        </table>
    </div>

    {{-- ── 5. THỜI GIAN DỰ KIẾN ──────────────────── --}}
    <div class="section">
        <div class="section-title">5. Thời Gian Đưa Vào Sử Dụng (Dự Kiến)</div>
        <p style="margin-top:5pt;">
            <span class="date-tag">{{ $vars['planned_use_date'] }}</span>
        </p>
    </div>

    {{-- ── LỜI KẾT ──────────────────────────────────── --}}
    <p class="closing">Kính trình Ban Lãnh Đạo xem xét và phê duyệt.</p>

    {{-- ── CHỮ KÝ ───────────────────────────────────── --}}
    <table class="sig">
        <tr>
            <td>
                <div class="sig-role">Người đề xuất</div>
                <div class="sig-space"></div>
                <div class="sig-name">{{ $vars['proposer_name'] }}</div>
            </td>
            <td>
                {{-- ô trống giữa --}}
            </td>
            <td>
                <div class="sig-role">Phòng Công Nghệ</div>
                <div class="sig-pos">Trưởng phòng</div>
                <div class="sig-space"></div>
                <div class="sig-name">Bùi Quang Toàn</div>
            </td>
        </tr>
        <tr>
            <td>
                <div class="sig-role">Phòng Kế Toán</div>
                <div class="sig-pos">Kế Toán Trưởng</div>
                <div class="sig-space"></div>
                <div class="sig-name">Trần Thị Tình</div>
            </td>
            <td colspan="2">
                <div class="sig-role">Phó Tổng Giám Đốc</div>
                <div class="sig-pos">Thường Trực</div>
                <div class="sig-space"></div>
                <div class="sig-name">Nguyễn Ngọc Hiển</div>
            </td>
        </tr>
    </table>

</body>
</html>