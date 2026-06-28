import XLSX from 'xlsx-js-style';
import { date, datetime } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';

const CLOSED = new Set(['resolved', 'declined']);

const S = {
    title: {
        font: { bold: true, sz: 16, color: { rgb: BRAND } },
        alignment: { horizontal: 'left', vertical: 'center' },
    },
    subtitle: {
        font: { sz: 10, color: { rgb: SLATE_600 }, italic: true },
        alignment: { horizontal: 'left' },
    },
    kpiLabel: {
        font: { bold: true, sz: 9, color: { rgb: SLATE_600 } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
    kpiValue: {
        font: { bold: true, sz: 12, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
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
    total: {
        font: { bold: true, sz: 10, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
    },
};

const HEADERS = [
    'STT', 'Mã', 'Tiêu đề', 'Phân loại', 'Ưu tiên', 'Trạng thái', 'Người gửi', 'Người xử lý',
    'Đánh giá', 'Ngày tạo', 'Ngày xử lý xong', 'Mô tả', 'Cập nhật',
];

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
    const isFormula = value && typeof value === 'object' && value.f;
    ws[ref] = isFormula
        ? { f: value.f, t: 'n', s: style }
        : { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((wch) => ({ wch }));
}

function reporterLabel(row) {
    return row.reporter_display
        || row.reporter?.display_name
        || row.reporter?.name
        || row.reporter_name
        || EMPTY_LABELS.notUpdated;
}

function assigneeLabel(row) {
    return row.assignee?.display_name
        || row.assignee?.name
        || EMPTY_LABELS.notUpdated;
}

function ratingLabel(row) {
    if (row.rating == null || row.rating === '') return 'Chưa đánh giá';
    return `${row.rating} sao`;
}

function rowToArray(row, idx) {
    return [
        idx + 1,
        displayOrEmpty(row.code, EMPTY_LABELS.notUpdated),
        displayOrEmpty(row.title, EMPTY_LABELS.notUpdated),
        displayOrEmpty(row.category?.label, 'Chưa phân loại'),
        displayOrEmpty(row.priority?.label, 'Chưa đặt ưu tiên'),
        displayOrEmpty(row.status?.label, EMPTY_LABELS.notUpdated),
        reporterLabel(row),
        assigneeLabel(row),
        ratingLabel(row),
        date(row.created_at) || EMPTY_LABELS.notUpdated,
        date(row.resolved_at) || 'Chưa xử lý xong',
        (row.description ?? '').slice(0, 500) || EMPTY_LABELS.notUpdated,
        datetime(row.updated_at) || EMPTY_LABELS.notUpdated,
    ];
}

function computeKpis(list) {
    const open = list.filter((r) => !CLOSED.has(r.status?.value)).length;
    const closed = list.filter((r) => CLOSED.has(r.status?.value)).length;
    const rated = list.filter((r) => r.rating != null && r.rating !== '');
    const avgRating = rated.length
        ? Math.round((rated.reduce((s, r) => s + Number(r.rating), 0) / rated.length) * 10) / 10
        : null;
    return { total: list.length, open, closed, avgRating };
}

function fileStamp() {
    const t = new Date();
    const dd = String(t.getDate()).padStart(2, '0');
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const yyyy = t.getFullYear();
    return { dd, mm, yyyy };
}

function safeCode(code) {
    return String(code ?? 'DA').replace(/[:\\/?*[\]]/g, '_');
}

function buildFeedbackXlsx(list, { projectCode, projectName }) {
    const ws = {};
    const COLS = HEADERS.length - 1;
    const exportedAt = datetime(new Date().toISOString());
    const kpis = computeKpis(list);

    setCell(ws, 0, 0, `PHẢN HỒI — ${projectName || projectCode}`, S.title);
    mergeRow(ws, 0, 0, COLS);

    setCell(ws, 1, 0, `Mã dự án: ${projectCode} · Xuất lúc ${exportedAt} · Mẫu VAschools`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);

    const kpiLabels = ['Tổng', 'Đang mở', 'Đã đóng', 'TB đánh giá'];
    const kpiValues = [kpis.total, kpis.open, kpis.closed, kpis.avgRating ?? 'Chưa có'];
    kpiLabels.forEach((label, i) => {
        setCell(ws, 3, i * 2, label, S.kpiLabel);
        setCell(ws, 3, i * 2 + 1, kpiValues[i], S.kpiValue);
    });

    const headerRow = 5;
    HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h, S.header));

    list.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        const rowStyle = idx % 2 === 0 ? S.cell : S.cellAlt;
        rowToArray(row, idx).forEach((val, c) => setCell(ws, r, c, val, rowStyle));
    });

    const totalRow = headerRow + 1 + list.length;
    setCell(ws, totalRow, 0, 'Tổng', S.total);
    setCell(ws, totalRow, 1, list.length, S.total);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: COLS } });
    setColWidths(ws, [5, 12, 36, 16, 12, 16, 22, 22, 12, 14, 16, 36, 18]);
    ws['!rows'] = [{ hpt: 28 }, { hpt: 16 }, null, { hpt: 22 }];

    return ws;
}

function escapeCsvCell(val) {
    return `"${String(val ?? '').replace(/"/g, '""')}"`;
}

function buildFeedbackCsv(list, { projectCode, projectName }) {
    const exportedAt = datetime(new Date().toISOString());
    const kpis = computeKpis(list);
    const meta = [
        ['PHẢN HỒI'],
        [`Dự án: ${displayOrEmpty(projectName, EMPTY_LABELS.notUpdated)}`],
        [`Mã dự án: ${projectCode}`],
        [`Ngày xuất: ${exportedAt}`],
        [],
        ['Tổng', 'Đang mở', 'Đã đóng', 'TB đánh giá'],
        [kpis.total, kpis.open, kpis.closed, kpis.avgRating ?? 'Chưa có'],
        [],
        HEADERS,
        ...list.map((row, idx) => rowToArray(row, idx)),
    ];
    return meta.map((row) => row.map(escapeCsvCell).join(',')).join('\n');
}

/**
 * @param {{ list: object[], projectCode?: string, projectName?: string, format: 'xlsx'|'csv' }} opts
 */
export function exportFeedbackRows({ list, projectCode = 'DA', projectName = '', format = 'xlsx' }) {
    if (!list?.length) return;

    const code = safeCode(projectCode);
    const { dd, mm, yyyy } = fileStamp();

    if (format === 'csv') {
        const content = `\uFEFF${buildFeedbackCsv(list, { projectCode: code, projectName })}`;
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `VA_PhanHoi_${code}_${dd}${mm}${yyyy}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
        return;
    }

    const ws = buildFeedbackXlsx(list, { projectCode: code, projectName });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'VA - Phan hoi');
    XLSX.writeFile(wb, `VA_PhanHoi_${code}_${dd}${mm}${yyyy}.xlsx`);
}
