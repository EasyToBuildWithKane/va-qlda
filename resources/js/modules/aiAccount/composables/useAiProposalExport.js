import XLSX from 'xlsx-js-style';
import { currency, datetime } from '@/composables/useFormat';

const BRAND = '9A0036';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';

const S = {
    title: {
        font: { bold: true, sz: 16, color: { rgb: BRAND } },
        alignment: { horizontal: 'left', vertical: 'center' },
    },
    subtitle: {
        font: { sz: 10, color: { rgb: SLATE_600 }, italic: true },
        alignment: { horizontal: 'left' },
    },
    header: {
        font: { bold: true, sz: 10, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    cell: {
        font: { sz: 10, color: { rgb: '334155' } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    cellAlt: {
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    money: {
        font: { sz: 10, color: { rgb: '334155' } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
    },
};

function borderThin() {
    return {
        top: { style: 'thin', color: { rgb: SLATE_200 } },
        bottom: { style: 'thin', color: { rgb: SLATE_200 } },
        left: { style: 'thin', color: { rgb: SLATE_200 } },
        right: { style: 'thin', color: { rgb: SLATE_200 } },
    };
}

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
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
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const dd = String(t.getDate()).padStart(2, '0');
    const yyyy = t.getFullYear();
    return { iso: `${yyyy}-${mm}-${dd}` };
}

function exportCellValue(row, key) {
    switch (key) {
        case 'proposal_code':
            return row.proposal_code ?? '—';
        case 'created_at':
            return row.created_at ? row.created_at.slice(0, 10) : '—';
        case 'proposer_name':
            return row.proposer_name ?? '—';
        case 'proposer_department':
            return row.proposer_department ?? '—';
        case 'proposal_type':
            return row.proposal_type_label ?? '—';
        case 'tool_name':
            return row.tool_name ?? '—';
        case 'vendor_name':
            return row.vendor_name ?? '—';
        case 'cost_amount':
            return row.cost_amount != null ? currency(row.cost_amount) : '—';
        case 'actual_cost':
            return row.actual_cost != null ? currency(row.actual_cost) : '—';
        case 'status':
            return row.status_label ?? '—';
        case 'overall_status':
            return row.overall_status?.label ?? '—';
        case 'reviewed_by_name':
            return row.reviewed_by_name ?? '—';
        case 'reviewed_at':
            return row.reviewed_at ? row.reviewed_at.slice(0, 10) : '—';
        case 'end_date':
            return row.end_date ?? '—';
        default:
            return row[key] ?? '—';
    }
}

function resolveColumns(allCols, visibleKeys) {
    return allCols.filter((c) => visibleKeys[c.key]);
}

function escapeCsvCell(val) {
    return `"${String(val ?? '').replace(/"/g, '""')}"`;
}

function buildProposalXlsx(list, cols, filterNote = '') {
    const lastCol = Math.max(cols.length - 1, 4);
    const ws = {};
    const exportedAt = datetime(new Date().toISOString());

    setCell(ws, 0, 0, 'PHIẾU ĐỀ XUẤT MUA SẮM — VAschools', S.title);
    mergeRow(ws, 0, 0, lastCol);

    const sub = filterNote
        ? `Xuất lúc ${exportedAt} · ${list.length} phiếu · ${filterNote}`
        : `Xuất lúc ${exportedAt} · ${list.length} phiếu`;
    setCell(ws, 1, 0, sub, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);

    const headerRow = 3;
    cols.forEach((c, i) => setCell(ws, headerRow, i, c.label, S.header));

    list.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        const rowStyle = idx % 2 === 0 ? S.cell : S.cellAlt;
        cols.forEach((c, ci) => {
            const val = exportCellValue(row, c.key);
            const style = ['cost_amount', 'actual_cost'].includes(c.key) ? S.money : rowStyle;
            setCell(ws, r, ci, val, style);
        });
    });

    const totalRow = headerRow + 1 + list.length;
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: cols.length - 1 } });
    setColWidths(ws, cols.map((c) => {
        if (c.key === 'tool_name') return 32;
        if (c.key === 'proposer_name') return 22;
        if (['cost_amount', 'actual_cost'].includes(c.key)) return 16;
        return 14;
    }));
    ws['!rows'] = [{ hpt: 28 }, { hpt: 18 }];

    return ws;
}

function buildProposalCsv(list, cols, filterNote = '') {
    const exportedAt = datetime(new Date().toISOString());
    const meta = [
        ['PHIẾU ĐỀ XUẤT MUA SẮM'],
        ['Đơn vị', 'VAschools'],
        ['Ngày xuất', exportedAt],
        ['Số phiếu', list.length],
    ];
    if (filterNote) meta.push(['Bộ lọc', filterNote]);
    meta.push([], cols.map((c) => c.label), ...list.map((row) => cols.map((c) => exportCellValue(row, c.key))));
    return meta.map((row) => row.map(escapeCsvCell).join(',')).join('\n');
}

/**
 * @param {{ list: object[], columns: {key:string,label:string}[], visibleKeys: Record<string,boolean>, filterNote?: string, format?: 'xlsx'|'csv' }} opts
 */
const PAYMENT_COLS = [
    { key: 'payment_request_code', label: 'Mã ĐNTT' },
    { key: 'proposal_code', label: 'Mã PĐX' },
    { key: 'tool_name', label: 'Nội dung' },
    { key: 'amount', label: 'Số tiền' },
    { key: 'status_label', label: 'Trạng thái' },
    { key: 'created_at', label: 'Ngày tạo' },
    { key: 'created_by_name', label: 'Người tạo' },
    { key: 'reviewed_by_name', label: 'Người duyệt' },
    { key: 'reviewed_at', label: 'Ngày duyệt' },
    { key: 'paid_at', label: 'Ngày thanh toán' },
];

function paymentRowsFromProposals(list) {
    return (list ?? [])
        .filter((p) => p.payment_request)
        .map((p) => ({
            ...p.payment_request,
            proposal_code: p.proposal_code,
            tool_name: p.tool_name,
        }));
}

function paymentCellValue(row, key) {
    switch (key) {
        case 'amount':
            return row.amount != null ? currency(row.amount) : '—';
        case 'created_at':
        case 'reviewed_at':
        case 'paid_at':
            return row[key] ? String(row[key]).slice(0, 16) : '—';
        default:
            return row[key] ?? '—';
    }
}

function buildPaymentXlsx(rows, filterNote = '') {
    const cols = PAYMENT_COLS;
    const lastCol = cols.length - 1;
    const ws = {};
    const exportedAt = datetime(new Date().toISOString());
    setCell(ws, 0, 0, 'ĐỀ NGHỊ THANH TOÁN — VAschools', S.title);
    mergeRow(ws, 0, 0, lastCol);
    const sub = filterNote
        ? `Xuất lúc ${exportedAt} · ${rows.length} ĐNTT · ${filterNote}`
        : `Xuất lúc ${exportedAt} · ${rows.length} ĐNTT`;
    setCell(ws, 1, 0, sub, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);
    const headerRow = 3;
    cols.forEach((c, i) => setCell(ws, headerRow, i, c.label, S.header));
    rows.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        const rowStyle = idx % 2 === 0 ? S.cell : S.cellAlt;
        cols.forEach((c, ci) => {
            const val = paymentCellValue(row, c.key);
            const style = c.key === 'amount' ? S.money : rowStyle;
            setCell(ws, r, ci, val, style);
        });
    });
    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: headerRow + rows.length, c: lastCol },
    });
    setColWidths(ws, cols.map((c) => (c.key === 'tool_name' ? 28 : 14)));
    return ws;
}

/**
 * @param {{ list: object[], filterNote?: string, format?: 'xlsx'|'csv' }} opts
 */
export function exportAiPaymentRequests({ list, filterNote = '', format = 'xlsx' }) {
    const rows = paymentRowsFromProposals(list);
    if (!rows.length) return false;

    const { iso } = fileStamp();
    const filenameBase = `VA_DeNghiThanhToan_${iso}`;

    if (format === 'csv') {
        const cols = PAYMENT_COLS;
        const exportedAt = datetime(new Date().toISOString());
        const meta = [
            ['ĐỀ NGHỊ THANH TOÁN'],
            ['Ngày xuất', exportedAt],
            ['Số ĐNTT', rows.length],
        ];
        if (filterNote) meta.push(['Bộ lọc', filterNote]);
        meta.push([], cols.map((c) => c.label), ...rows.map((row) => cols.map((c) => paymentCellValue(row, c.key))));
        const content = '\uFEFF' + meta.map((r) => r.map(escapeCsvCell).join(',')).join('\n');
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${filenameBase}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
        return `${filenameBase}.csv`;
    }

    const ws = buildPaymentXlsx(rows, filterNote);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'De nghi TT');
    const filename = `${filenameBase}.xlsx`;
    XLSX.writeFile(wb, filename);
    return filename;
}

/**
 * Báo cáo thanh toán: chỉ ĐNTT đã thanh toán.
 */
export function exportAiPaymentReport({ list, filterNote = '', format = 'xlsx' }) {
    const paid = paymentRowsFromProposals(list).filter((r) => r.status === 'paid');
    if (!paid.length) return false;

    const { iso } = fileStamp();
    const filenameBase = `VA_BaoCaoThanhToan_${iso}`;

    if (format === 'csv') {
        return exportAiPaymentRequests({ list: list.filter((p) => p.payment_request?.status === 'paid'), filterNote, format: 'csv' });
    }

    const ws = buildPaymentXlsx(paid, filterNote);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Bao cao TT');
    XLSX.writeFile(wb, `${filenameBase}.xlsx`);
    return `${filenameBase}.xlsx`;
}

export function exportAiProposals({ list, columns, visibleKeys, filterNote = '', format = 'xlsx' }) {
    if (!list?.length) return false;

    const cols = resolveColumns(columns, visibleKeys);
    if (!cols.length) return false;

    const { iso } = fileStamp();
    const filenameBase = `VA_PhieuDeXuat_${iso}`;

    if (format === 'csv') {
        const content = '\uFEFF' + buildProposalCsv(list, cols, filterNote);
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${filenameBase}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
        return `${filenameBase}.csv`;
    }

    const ws = buildProposalXlsx(list, cols, filterNote);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Phieu de xuat');
    const filename = `${filenameBase}.xlsx`;
    XLSX.writeFile(wb, filename);
    return filename;
}
