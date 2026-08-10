import XLSX from 'xlsx-js-style';
import { date, datetime } from '@/composables/useFormat';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
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

const TERMINAL = new Set(['resolved', 'closed']);

const HEADERS = [
    'STT', 'Mã', 'Tiêu đề', 'Mức độ', 'Trạng thái', 'Người phụ trách', 'Người ghi nhận',
    'Ngày phát hiện', 'Hạn xử lý', 'Quá hạn', 'Nguyên nhân', 'Hướng xử lý', 'Mô tả', 'Cập nhật',
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

function isOverdue(row) {
    if (!row.due_date || TERMINAL.has(row.status?.value)) return false;
    return new Date(`${row.due_date}T00:00:00`) < new Date().setHours(0, 0, 0, 0);
}

function rowToArray(row, idx) {
    return [
        idx + 1,
        row.code ?? '—',
        row.title ?? '—',
        row.severity?.label ?? '—',
        row.status?.label ?? '—',
        row.owner?.name ?? '—',
        row.raised_by?.name ?? '—',
        date(row.raised_at) || '—',
        date(row.due_date) || '—',
        isOverdue(row) ? 'Có' : 'Không',
        row.root_cause ?? '—',
        row.resolution ?? '—',
        (row.description ?? '').slice(0, 500) || '—',
        datetime(row.updated_at) || '—',
    ];
}

function computeKpis(list) {
    const open = list.filter((r) => r.status?.value === 'open').length;
    const inProgress = list.filter((r) => ['in_progress', 'blocked'].includes(r.status?.value)).length;
    const resolved = list.filter((r) => TERMINAL.has(r.status?.value)).length;
    const overdue = list.filter((r) => isOverdue(r)).length;
    return { total: list.length, open, inProgress, resolved, overdue };
}

function fileStamp() {
    const t = new Date();
    const dd = String(t.getDate()).padStart(2, '0');
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const yyyy = t.getFullYear();
    return { dd, mm, yyyy, iso: t.toISOString().slice(0, 10) };
}

function safeCode(code) {
    return String(code ?? 'DA').replace(/[:\\/?*[\]]/g, '_');
}

function applySeverityStyle(ws, r, c, sev, baseStyle) {
    const ref = XLSX.utils.encode_cell({ r, c });
    if (sev === 'critical') {
        ws[ref].s = { ...baseStyle, font: { bold: true, sz: 10, color: { rgb: 'BE123C' } }, fill: { fgColor: { rgb: 'FFE4E6' } } };
    } else if (sev === 'high') {
        ws[ref].s = { ...baseStyle, font: { bold: true, sz: 10, color: { rgb: 'C2410C' } }, fill: { fgColor: { rgb: 'FFEDD5' } } };
    } else if (sev === 'medium') {
        ws[ref].s = { ...baseStyle, font: { sz: 10, color: { rgb: 'B45309' } }, fill: { fgColor: { rgb: 'FEF3C7' } } };
    }
}

function buildRiskXlsx(list, { projectCode, projectName }) {
    const ws = {};
    const COLS = HEADERS.length - 1;
    const exportedAt = datetime(new Date().toISOString());
    const kpis = computeKpis(list);

    setCell(ws, 0, 0, `TRƯỜNG HỢP KIỂM THỬ — ${projectName || projectCode}`, S.title);
    mergeRow(ws, 0, 0, COLS);

    setCell(ws, 1, 0, `Mã dự án: ${projectCode} · Xuất lúc ${exportedAt} · Mẫu VAschools`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);

    const kpiLabels = ['Tổng mục', 'Đang mở', 'Đang xử lý', 'Đã đóng', 'Quá hạn'];
    const kpiValues = [kpis.total, kpis.open, kpis.inProgress, kpis.resolved, kpis.overdue];
    kpiLabels.forEach((label, i) => {
        setCell(ws, 3, i * 2, label, S.kpiLabel);
        setCell(ws, 3, i * 2 + 1, kpiValues[i], S.kpiValue);
    });

    const headerRow = 5;
    HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h, S.header));

    list.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        const rowStyle = idx % 2 === 0 ? S.cell : S.cellAlt;
        const values = rowToArray(row, idx);
        values.forEach((val, c) => setCell(ws, r, c, val, rowStyle));
        applySeverityStyle(ws, r, 3, row.severity?.value ?? 'medium', rowStyle);
        if (isOverdue(row)) {
            const ref = XLSX.utils.encode_cell({ r, c: 9 });
            ws[ref].s = { ...rowStyle, font: { bold: true, sz: 10, color: { rgb: 'BE123C' } } };
        }
    });

    const totalRow = headerRow + 1 + list.length;
    const dataStart = headerRow + 2;
    const dataEnd = Math.max(headerRow + 1 + list.length, dataStart);
    setCell(ws, totalRow, 0, 'Tổng', S.total);
    setCell(ws, totalRow, 1, { f: `COUNTA(B${dataStart}:B${dataEnd})` }, S.total);
    setCell(ws, totalRow, 3, 'Đang mở:', S.total);
    setCell(ws, totalRow, 4, kpis.open, S.total);
    setCell(ws, totalRow, 5, 'Quá hạn:', S.total);
    setCell(ws, totalRow, 6, kpis.overdue, S.total);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: COLS } });
    setColWidths(ws, [5, 12, 36, 14, 14, 20, 20, 14, 12, 10, 28, 28, 32, 18]);
    ws['!rows'] = [{ hpt: 28 }, { hpt: 16 }, null, { hpt: 22 }];

    return ws;
}

function escapeCsvCell(val) {
    return `"${String(val ?? '').replace(/"/g, '""')}"`;
}

function buildRiskCsv(list, { projectCode, projectName }) {
    const exportedAt = datetime(new Date().toISOString());
    const kpis = computeKpis(list);
    const meta = [
        ['RỦI RO & VƯỚNG MẮC'],
        [`Dự án: ${projectName || '—'}`],
        [`Mã dự án: ${projectCode}`],
        [`Ngày xuất: ${exportedAt}`],
        [],
        ['Tổng mục', 'Đang mở', 'Đang xử lý', 'Đã đóng', 'Quá hạn'],
        [kpis.total, kpis.open, kpis.inProgress, kpis.resolved, kpis.overdue],
        [],
        HEADERS,
        ...list.map((row, idx) => rowToArray(row, idx)),
    ];
    return meta.map((row) => row.map(escapeCsvCell).join(',')).join('\n');
}

/**
 * @param {{ list: object[], projectCode?: string, projectName?: string, format: 'xlsx'|'csv' }} opts
 */
export function exportRiskBlockers({ list, projectCode = 'DA', projectName = '', format = 'xlsx' }) {
    if (!list?.length) return;

    const code = safeCode(projectCode);
    const { dd, mm, yyyy } = fileStamp();

    if (format === 'csv') {
        const content = '\uFEFF' + buildRiskCsv(list, { projectCode: code, projectName });
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `VA_TestCase_${code}_${dd}${mm}${yyyy}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
        return;
    }

    const ws = buildRiskXlsx(list, { projectCode: code, projectName });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'VA - Test case');
    XLSX.writeFile(wb, `VA_TestCase_${code}_${dd}${mm}${yyyy}.xlsx`);
}
