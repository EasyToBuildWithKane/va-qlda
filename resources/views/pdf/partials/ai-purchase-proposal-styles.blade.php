/* Vùng an toàn khớp public/docx/background.png (logo + tagline trên, thanh đỏ dưới) */
@page {
    size: A4;
    margin: 42mm 12mm 15mm 14mm;
}

* { box-sizing: border-box; }

body {
    font-family: 'DejaVu Serif', 'Times New Roman', serif;
    font-size: 11pt;
    line-height: 1.75;
    color: #000;
    margin: 0;
    padding: 0;
}

.doc-content {
    position: relative;
    z-index: 1;
    width: 100%;
    max-width: 182mm;
    margin: 0 auto;
    padding: 0 2mm 2mm 1mm;
}

/* Nội dung in trong vùng trắng (dưới letterhead nền, trên thanh đỏ) */
.doc-content-on-bg {
    padding-top: 2mm;
    padding-bottom: 3mm;
}

.doc-content p {
    margin: 2pt 0;
    line-height: 1.75;
}

/* Letterhead — header.png (không dùng nền CSS); inset tránh mép in cắt logo/chữ */
.doc-letterhead {
    width: 100%;
    margin: 0 0 4pt 0;
    padding: 2mm 9mm 1mm 7mm;
    box-sizing: border-box;
    line-height: 0;
}

.doc-letterhead-img {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
    margin: 0 auto;
}

.form-code {
    text-align: right;
    font-size: 9.5pt;
    font-style: italic;
    margin: 0 0 3pt 0;
    color: #444;
}

/* Mục nội dung có viền (phiếu đề xuất + giấy thanh toán) */
table.doc-detail-fields,
table.payment-detail-fields {
    width: 100%;
    border-collapse: collapse;
    margin: 4pt 0 3pt 0;
    font-size: 11pt;
    border: 1pt solid #000;
}

table.doc-detail-fields td,
table.payment-detail-fields td {
    padding: 6pt 8pt;
    vertical-align: top;
    line-height: 1.65;
    border: 1pt solid #000;
}

table.doc-detail-fields td.field-label,
table.payment-detail-fields td.field-label {
    width: 32%;
    font-weight: bold;
    white-space: nowrap;
    background-color: #f5f5f5;
}

table.doc-detail-fields td.field-value,
table.payment-detail-fields td.field-value {
    width: 68%;
}

/* @deprecated — giữ doc-national-block nếu view cũ còn include */
.doc-national-block {
    width: 100%;
    margin: 0 0 6pt 0;
    padding: 0 4pt;
    text-align: center;
}

.doc-national-block .republic {
    font-weight: bold;
    font-size: 11.5pt;
    text-transform: uppercase;
    line-height: 1.45;
    margin: 0 0 2pt 0;
}

.doc-national-block .motto {
    font-size: 10.5pt;
    line-height: 1.55;
    margin: 0 0 2pt 0;
}

.doc-national-block .doc-date {
    font-size: 10pt;
    font-style: italic;
    margin: 2pt 0 0 0;
    line-height: 1.45;
}

.doc-national-block .motto-line {
    display: inline-block;
    border-bottom: 1px solid #000;
    padding-bottom: 0.5pt;
}

.page-bg {
    position: fixed;
    top: -42mm;
    left: -14mm;
    width: 210mm;
    height: 297mm;
    z-index: 0;
    overflow: hidden;
    line-height: 0;
}
.page-bg img {
    display: block;
    width: 210mm;
    height: 297mm;
    margin: 0;
    padding: 0;
}

.doc-header {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 3pt;
    border: 1pt solid #000;
    table-layout: fixed;
}
.doc-header td {
    padding: 5pt 8pt 6pt;
    vertical-align: middle;
    border: 1pt solid #000;
    line-height: 1.75;
}
.doc-header .cell-left {
    width: 40%;
    font-size: 11pt;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.2pt;
}
.doc-header .cell-left .doc-header-school {
    display: inline-block;
    white-space: nowrap;
    letter-spacing: 0.1pt;
}
.doc-header .cell-left .unit {
    font-weight: normal;
    font-size: 10pt;
    line-height: 1.6;
}
.doc-header .cell-left .doc-header-dept {
    font-weight: bold;
    text-transform: uppercase;
    letter-spacing: 0.1pt;
}
.doc-header .cell-right {
    width: 60%;
    text-align: center;
    padding-left: 10pt;
    padding-right: 10pt;
}
.doc-header .cell-right .republic {
    font-weight: bold;
    font-size: 11.5pt;
    text-transform: uppercase;
    line-height: 1.5;
    margin-bottom: 2pt;
}
.doc-header .cell-right .motto {
    font-size: 10.5pt;
    line-height: 1.6;
    margin-bottom: 2pt;
}
.doc-header .cell-right .doc-date {
    font-size: 10pt;
    font-style: italic;
    margin-top: 2pt;
    line-height: 1.5;
}
.doc-header .cell-right .motto-line {
    display: inline-block;
    border-bottom: 1px solid #000;
    padding-bottom: 0.5pt;
    text-decoration: none;
}

.doc-title {
    text-align: center;
    margin: 8pt 0 6pt 0;
}
.doc-title h1 {
    font-size: 14pt;
    font-weight: bold;
    text-transform: uppercase;
    margin: 0 0 3pt 0;
    letter-spacing: 0.5pt;
    line-height: 1.4;
}
.doc-title .subtitle {
    font-size: 10pt;
    font-style: italic;
    margin: 0;
    line-height: 1.5;
}

.kinh-gui {
    margin: 2pt 0 0 22pt;
}
.kinh-gui .label { font-style: italic; font-weight: bold; }
.kinh-gui .send-to-line {
    margin: 0;
    padding: 0 0 0 0;
}

table.kinh-gui-table {
    width: 100%;
    border-collapse: collapse;
    margin: 2pt 0 0 0;
}
table.kinh-gui-table td {
    padding: 0;
    vertical-align: top;
    line-height: 1.75;
}
table.kinh-gui-table td.kinh-gui-label {
    width: 54pt;
    font-style: italic;
    font-weight: bold;
    white-space: nowrap;
}
table.kinh-gui-table td.kinh-gui-body {
    padding-left: 4pt;
}

.section {
    margin: 1pt 0 0 0;
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

/* 4.3 — gạch đầu dòng, thẳng với indent mục 4 */
ul.recipient-list {
    margin: 0 0 2pt 32pt;
    padding: 0 0 0 14pt;
    list-style-type: disc;
}
ul.recipient-list li {
    margin: 0;
    padding: 0;
    line-height: 1.75;
}
.bold { font-weight: bold; }
.italic { font-style: italic; }
.underline { text-decoration: underline; }
.center { text-align: center; }

table.budget {
    width: 100%;
    border-collapse: collapse;
    margin: 1pt 0 2pt 0;
    font-size: 10pt;
}
table.budget th,
table.budget td {
    border: 1pt solid #000;
    padding: 2pt 3pt;
    vertical-align: middle;
}
table.budget th {
    font-weight: bold;
    text-align: center;
    background-color: #f5f5f5;
}
table.budget td.center { text-align: center; }

/* 4.4 — tình trạng: một hàng, ô vuông + X (tương thích DomPDF) */
table.status-options {
    width: auto;
    border-collapse: collapse;
    margin: 0 0 4pt 32pt;
}
table.status-options td.status-cell {
    padding: 1pt 36pt 1pt 0;
    vertical-align: middle;
    line-height: 1.75;
    white-space: nowrap;
}
.status-box-mark {
    display: inline-block;
    width: 11pt;
    height: 11pt;
    margin-right: 6pt;
    border: 1pt solid #000;
    text-align: center;
    line-height: 10pt;
    font-size: 8.5pt;
    font-weight: bold;
    vertical-align: middle;
}
.status-label {
    font-size: 11pt;
    vertical-align: middle;
}

table.sig {
    width: 100%;
    border-collapse: collapse;
    margin-top: 6pt;
}
table.sig td {
    border: 1pt dotted #666;
    padding: 6pt 8pt 8pt;
    vertical-align: top;
    text-align: center;
    font-size: 10pt;
    width: 33.33%;
    height: 96pt;
}
table.sig .sig-title {
    font-weight: bold;
    line-height: 1.4;
    margin: 0 0 1pt 0;
}
table.sig .sig-space {
    display: block;
    height: 58pt;
    min-height: 58pt;
}
table.sig .sig-name {
    margin-top: 0;
    font-weight: bold;
    line-height: 1.4;
}

.pre { white-space: pre-wrap; }

ul.objectives {
    margin: 1pt 0 1pt 18pt;
    padding: 0;
}
ul.objectives li {
    margin-bottom: 0.5pt;
}

.section-5 .planned-date {
    color: #cc0000;
    font-weight: bold;
}

.doc-page-footer {
    page-break-inside: avoid;
    break-inside: avoid;
}

.closing {
    margin-top: 4pt;
    margin-bottom: 2pt;
    font-style: italic;
    font-size: 10.5pt;
    line-height: 1.75;
}
