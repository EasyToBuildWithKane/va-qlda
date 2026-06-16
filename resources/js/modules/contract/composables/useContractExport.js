import XLSX from 'xlsx-js-style';

const BRAND = '9A0036';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';
const DANGER = 'FEE2E2';
const DANGER_TEXT = 'B91C1C';
const WARN = 'FEF3C7';
const WARN_TEXT = 'B45309';

function borderThin() {
    return {
        top: { style: 'thin', color: { rgb: SLATE_200 } },
        bottom: { style: 'thin', color: { rgb: SLATE_200 } },
        left: { style: 'thin', color: { rgb: SLATE_200 } },
        right: { style: 'thin', color: { rgb: SLATE_200 } },
    };
}

const S = {
    title: { font: { bold: true, sz: 16, color: { rgb: BRAND } }, alignment: { horizontal: 'left', vertical: 'center' } },
    subtitle: { font: { sz: 10, color: { rgb: SLATE_600 }, italic: true }, alignment: { horizontal: 'left' } },
    header: { font: { bold: true, sz: 10, color: { rgb: WHITE } }, fill: { fgColor: { rgb: BRAND } }, alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: borderThin() },
    cell: { font: { sz: 10, color: { rgb: '1E293B' } }, alignment: { vertical: 'center', wrapText: true }, border: borderThin() },
    cellAlt: { font: { sz: 10, color: { rgb: '1E293B' } }, fill: { fgColor: { rgb: SLATE_50 } }, alignment: { vertical: 'center', wrapText: true }, border: borderThin() },
    num: { font: { sz: 10, color: { rgb: '1E293B' } }, alignment: { horizontal: 'right', vertical: 'center' }, border: borderThin() },
    danger: { font: { sz: 10, color: { rgb: DANGER_TEXT } }, fill: { fgColor: { rgb: DANGER } }, alignment: { horizontal: 'center', vertical: 'center' }, border: borderThin() },
    warn: { font: { sz: 10, color: { rgb: WARN_TEXT } }, fill: { fgColor: { rgb: WARN } }, alignment: { horizontal: 'center', vertical: 'center' }, border: borderThin() },
    kpiLabel: { font: { sz: 10, color: { rgb: SLATE_600 } }, border: borderThin() },
    kpiValue: { font: { bold: true, sz: 12, color: { rgb: BRAND } }, alignment: { horizontal: 'right' }, border: borderThin() },
};

function setCell(ws, r, c, value, style, type = 's') {
    ws[XLSX.utils.encode_cell({ r, c })] = { v: value ?? '', t: type, s: style };
}
function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}
function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((wch) => ({ wch }));
}
function fileStamp() {
    const t = new Date();
    return { dd: String(t.getDate()).padStart(2, '0'), mm: String(t.getMonth() + 1).padStart(2, '0'), yyyy: t.getFullYear() };
}
function num(v) {
    return v == null || v === '' ? 0 : Number(v) || 0;
}

function buildSummarySheet(contracts) {
    const ws = {};
    const totalValue = contracts.reduce((s, c) => s + num(c.lifecycle_cost), 0);
    const totalAnnual = contracts.reduce((s, c) => s + num(c.annual_cost), 0);
    const expiring = contracts.filter((c) => c.days_until_expiry != null && c.days_until_expiry >= 0 && c.days_until_expiry <= 90).length;
    const expired = contracts.filter((c) => c.is_expired).length;

    setCell(ws, 0, 0, 'BÁO CÁO HỢP ĐỒNG — TỔNG QUAN', S.title);
    mergeRow(ws, 0, 0, 3);
    setCell(ws, 1, 0, `Xuất ngày ${new Date().toLocaleDateString('vi-VN')} · VAschools QLDA`, S.subtitle);
    mergeRow(ws, 1, 0, 3);

    const kpis = [
        ['Tổng số hợp đồng', contracts.length],
        ['Tổng giá trị (vòng đời)', totalValue],
        ['Tổng chi phí năm', totalAnnual],
        ['Sắp hết hạn (≤90 ngày)', expiring],
        ['Đã hết hạn', expired],
    ];
    kpis.forEach(([label, value], i) => {
        const r = 3 + i;
        setCell(ws, r, 0, label, S.kpiLabel);
        mergeRow(ws, r, 0, 1);
        setCell(ws, r, 2, value, S.kpiValue, typeof value === 'number' ? 'n' : 's');
        mergeRow(ws, r, 2, 3);
    });

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 3 + kpis.length, c: 3 } });
    setColWidths(ws, [28, 16, 18, 16]);
    ws['!rows'] = [{ hpt: 26 }];
    return ws;
}

const DETAIL_HEADERS = [
    { label: 'Mã', width: 14 },
    { label: 'Tên hợp đồng', width: 36 },
    { label: 'Nhà cung cấp', width: 22 },
    { label: 'Nhóm dịch vụ', width: 20 },
    { label: 'Đơn vị sử dụng', width: 20 },
    { label: 'Chi phí năm', width: 16 },
    { label: 'Chi phí vòng đời', width: 18 },
    { label: 'Thanh toán', width: 16 },
    { label: 'Hiệu lực', width: 14 },
    { label: 'Hết hạn', width: 14 },
    { label: 'Trạng thái', width: 16 },
];

function buildDetailSheet(contracts) {
    const ws = {};
    const COLS = DETAIL_HEADERS.length - 1;

    setCell(ws, 0, 0, 'CHI TIẾT HỢP ĐỒNG', S.title);
    mergeRow(ws, 0, 0, COLS);
    DETAIL_HEADERS.forEach((h, c) => setCell(ws, 2, c, h.label, S.header));

    contracts.forEach((c, idx) => {
        const r = 3 + idx;
        const base = idx % 2 === 0 ? S.cell : S.cellAlt;
        setCell(ws, r, 0, c.code ?? '', base);
        setCell(ws, r, 1, c.name ?? '', base);
        setCell(ws, r, 2, c.vendor?.name ?? '—', base);
        setCell(ws, r, 3, c.category?.name ?? '—', base);
        setCell(ws, r, 4, c.using_unit ?? '—', base);
        setCell(ws, r, 5, num(c.annual_cost), S.num, 'n');
        setCell(ws, r, 6, num(c.lifecycle_cost), S.num, 'n');
        setCell(ws, r, 7, c.payment_status?.label ?? '—', base);
        setCell(ws, r, 8, c.effective_date ?? '—', base);
        setCell(ws, r, 9, c.expiry_date ?? '—', base);
        setCell(ws, r, 10, c.status?.label ?? '—', base);
    });

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 3 + contracts.length, c: COLS } });
    setColWidths(ws, DETAIL_HEADERS.map((h) => h.width));
    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: 2, c: 0 }, e: { r: 2 + contracts.length, c: COLS } }) };
    ws['!freeze'] = { xSplit: 0, ySplit: 3 };
    return ws;
}

function buildExpiringSheet(contracts) {
    const ws = {};
    const rows = contracts
        .filter((c) => c.days_until_expiry != null && c.days_until_expiry <= 90)
        .sort((a, b) => num(a.days_until_expiry) - num(b.days_until_expiry));

    const headers = ['Mã', 'Tên hợp đồng', 'Nhà cung cấp', 'Hết hạn', 'Còn lại (ngày)', 'Chi phí năm'];
    const COLS = headers.length - 1;

    setCell(ws, 0, 0, 'HỢP ĐỒNG SẮP / ĐÃ HẾT HẠN', S.title);
    mergeRow(ws, 0, 0, COLS);
    headers.forEach((h, c) => setCell(ws, 2, c, h, S.header));

    rows.forEach((c, idx) => {
        const r = 3 + idx;
        const days = num(c.days_until_expiry);
        const dayStyle = days < 7 ? S.danger : (days <= 30 ? S.warn : S.cell);
        setCell(ws, r, 0, c.code ?? '', S.cell);
        setCell(ws, r, 1, c.name ?? '', S.cell);
        setCell(ws, r, 2, c.vendor?.name ?? '—', S.cell);
        setCell(ws, r, 3, c.expiry_date ?? '—', S.cell);
        setCell(ws, r, 4, days, dayStyle, 'n');
        setCell(ws, r, 5, num(c.annual_cost), S.num, 'n');
    });

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 3 + rows.length, c: COLS } });
    setColWidths(ws, [14, 36, 22, 14, 16, 16]);
    return ws;
}

/**
 * Xuất file Excel báo cáo hợp đồng (3 sheet: Tổng quan, Chi tiết, Sắp hết hạn).
 * @param {Array} contracts — mảng ContractResource đã resolve.
 */
export function downloadContractExport(contracts = []) {
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildSummarySheet(contracts), 'Tong quan');
    XLSX.utils.book_append_sheet(wb, buildDetailSheet(contracts), 'Chi tiet');
    XLSX.utils.book_append_sheet(wb, buildExpiringSheet(contracts), 'Sap het han');
    const t = fileStamp();
    XLSX.writeFile(wb, `VA_BaoCao_HopDong_${t.dd}${t.mm}${t.yyyy}.xlsx`);
}
