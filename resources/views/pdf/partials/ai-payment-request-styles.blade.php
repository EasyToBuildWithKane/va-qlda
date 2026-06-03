/* ── Payment Request overrides ── */

.payment-request .pr-form-code {
    text-align: right;
    font-size: 9.5pt;
    font-style: italic;
    margin: 0 0 3pt 0;
    color: #444;
}

/* Left header cell: contains the logo image + dept label */
.payment-request .doc-header .pr-cell-img {
    padding: 4pt 6pt;
    vertical-align: middle;
    text-align: left;
}

.payment-request .pr-header-img {
    display: block;
    width: 100%;
    max-width: 100%;
    height: auto;
    margin-bottom: 2pt;
}

.payment-request .pr-dept-label {
    font-size: 9.5pt;
    font-weight: bold;
    text-align: center;
    text-transform: uppercase;
    letter-spacing: 0.2pt;
    line-height: 1.4;
    margin-top: 2pt;
}

/* Section 2 – detail fields table */
.payment-request table.pr-detail-fields {
    width: 100%;
    border-collapse: collapse;
    margin: 3pt 0 2pt 0;
    font-size: 11pt;
}
.payment-request table.pr-detail-fields td {
    padding: 5pt 4pt;
    vertical-align: top;
    line-height: 1.75;
    border-bottom: 0.5pt dotted #bbb;
}
.payment-request table.pr-detail-fields td.field-label {
    width: 36%;
    font-weight: bold;
    padding-right: 8pt;
    white-space: nowrap;
}
.payment-request table.pr-detail-fields td.field-value {
    width: 64%;
}
