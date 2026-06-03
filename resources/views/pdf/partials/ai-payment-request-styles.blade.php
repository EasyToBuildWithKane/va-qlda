/* ── Payment Request overrides ── */

.payment-request .pr-form-code {
    text-align: right;
    font-size: 9.5pt;
    font-style: italic;
    margin: 0 0 3pt 0;
    color: #444;
}

/* Letterhead A4 — docx/header.png full width */
.payment-request .pr-a4-header {
    width: 100%;
    margin: 0 0 2pt 0;
    line-height: 0;
}

.payment-request .pr-header-banner {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
    margin: 0;
}

.payment-request .pr-dept-line {
    margin: 0 0 4pt 0;
    padding: 0 2pt;
    font-size: 10.5pt;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.15pt;
    color: #7a0030;
    line-height: 1.4;
}

/* Quốc hiệu + ngày tháng (dưới banner, không viền bảng doc-header) */
.payment-request table.pr-header-meta {
    width: 100%;
    border-collapse: collapse;
    margin: 0 0 4pt 0;
    table-layout: fixed;
}

.payment-request table.pr-header-meta td {
    padding: 0;
    vertical-align: top;
    border: none;
}

.payment-request table.pr-header-meta .pr-meta-spacer {
    width: 42%;
}

.payment-request table.pr-header-meta .cell-right {
    width: 58%;
    text-align: center;
    padding: 2pt 6pt 4pt;
}

.payment-request table.pr-header-meta .republic {
    font-weight: bold;
    font-size: 11.5pt;
    text-transform: uppercase;
    line-height: 1.45;
    margin-bottom: 2pt;
}

.payment-request table.pr-header-meta .motto {
    font-size: 10.5pt;
    line-height: 1.5;
    margin-bottom: 2pt;
}

.payment-request table.pr-header-meta .doc-date {
    font-size: 10pt;
    font-style: italic;
    margin-top: 2pt;
    line-height: 1.45;
}

.payment-request table.pr-header-meta .motto-line {
    display: inline-block;
    border-bottom: 1px solid #000;
    padding-bottom: 0.5pt;
}

.payment-request .doc-title {
    margin: 11pt 0 10pt 0;
}

.payment-request .doc-title h1 {
    margin-bottom: 4pt;
}

/* Section 2 – bảng nội dung thanh toán có viền */
.payment-request table.pr-detail-fields {
    width: 100%;
    border-collapse: collapse;
    margin: 4pt 0 3pt 0;
    font-size: 11pt;
    border: 1pt solid #000;
}

.payment-request table.pr-detail-fields td {
    padding: 6pt 8pt;
    vertical-align: top;
    line-height: 1.65;
    border: 1pt solid #000;
}

.payment-request table.pr-detail-fields td.field-label {
    width: 34%;
    font-weight: bold;
    padding-right: 8pt;
    white-space: nowrap;
    background: #fafafa;
}

.payment-request table.pr-detail-fields td.field-value {
    width: 66%;
}
