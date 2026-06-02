import XLSX from 'xlsx-js-style';
import { COLUMNS, cellValue } from '@/modules/project/config/columns';
import { currency, date, datetime } from '@/composables/useFormat';

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

function fileStamp() {
    const t = new Date();
    const dd = String(t.getDate()).padStart(2, '0');
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const yyyy = t.getFullYear();
    return { dd, mm, yyyy, iso: t.toISOString().slice(0, 10) };
}

function exportColumns(visibleKeys) {
    const keys = visibleKeys?.length
        ? visibleKeys
        : COLUMNS.map((c) => c.key);
    const cols = [{ key: 'name', label: 'Tên dự án' }, { key: 'code', label: 'Mã dự án' }];
    COLUMNS.filter((c) => keys.includes(c.key)).forEach((c) => {
        if (c.key !== 'code') cols.push(c);
    });
    return cols;
}

function exportCellValue(p, key) {
    switch (key) {
        case 'name': return p.name ?? '—';
        case 'code': return p.code ?? '—';
        case 'progress': return Number(p.progress ?? 0);
        case 'budget':
        case 'actual_budget':
        case 'labor_cost':
            return currency(p[key]);
        case 'start_date':
        case 'due_date':
        case 'created_at':
            return date(p[key]) || '—';
        case 'updated_at':
            return datetime(p.updated_at) || '—';
        default:
            return cellValue(p, key) || '—';
    }
}

function rowToArray(p, cols) {
    return cols.map((c) => exportCellValue(p, c.key));
}

function computeKpis(list, summary = {}) {
    const active = summary.active ?? list.filter((p) => p.status?.value === 'active').length;
    const completed = summary.completed ?? list.filter((p) => p.status?.value === 'completed').length;
    const overdue = summary.overdue ?? list.filter((p) => p.is_overdue).length;
    return {
        total: list.length,
        active,
        completed,
        overdue,
    };
}

function buildProjectListXlsx(list, { visibleKeys, summary }) {
    const cols = exportColumns(visibleKeys);
    const COLS = cols.length - 1;
    const exportedAt = datetime(new Date().toISOString());
    const kpis = computeKpis(list, summary);
    const ws = {};

    setCell(ws, 0, 0, 'DANH MỤC DỰ ÁN — VAschools', S.title);
    mergeRow(ws, 0, 0, Math.max(COLS, 4));

    setCell(ws, 1, 0, `Xuất lúc ${exportedAt} · ${list.length} bản ghi · Mẫu chuẩn VA`, S.subtitle);
    mergeRow(ws, 1, 0, Math.max(COLS, 4));

    const kpiLabels = ['Tổng dự án', 'Đang thực hiện', 'Hoàn thành', 'Trễ hạn'];
    const kpiValues = [kpis.total, kpis.active, kpis.completed, kpis.overdue];
    kpiLabels.forEach((label, i) => {
        setCell(ws, 3, i * 2, label, S.kpiLabel);
        setCell(ws, 3, i * 2 + 1, kpiValues[i], S.kpiValue);
    });

    const headerRow = 5;
    cols.forEach((c, i) => setCell(ws, headerRow, i, c.label, S.header));

    list.forEach((p, idx) => {
        const r = headerRow + 1 + idx;
        const rowStyle = idx % 2 === 0 ? S.cell : S.cellAlt;
        rowToArray(p, cols).forEach((val, c) => setCell(ws, r, c, val, rowStyle));
    });

    const totalRow = headerRow + 1 + list.length;
    setCell(ws, totalRow, 0, 'Tổng', S.total);
    setCell(ws, totalRow, 1, list.length, S.total);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: cols.length - 1 } });
    setColWidths(ws, cols.map((c) => {
        if (c.key === 'name') return 36;
        if (c.key === 'manager') return 22;
        if (['budget', 'actual_budget', 'labor_cost'].includes(c.key)) return 16;
        return 14;
    }));
    ws['!rows'] = [{ hpt: 28 }, { hpt: 16 }, null, { hpt: 22 }];

    return ws;
}

function escapeCsvCell(val) {
    return `"${String(val ?? '').replace(/"/g, '""')}"`;
}

function buildProjectListCsv(list, { visibleKeys, summary }) {
    const cols = exportColumns(visibleKeys);
    const exportedAt = datetime(new Date().toISOString());
    const kpis = computeKpis(list, summary);
    const meta = [
        ['DANH MỤC DỰ ÁN'],
        ['Đơn vị', 'VAschools'],
        ['Ngày xuất', exportedAt],
        ['Số bản ghi', list.length],
        [],
        ['Tổng dự án', 'Đang thực hiện', 'Hoàn thành', 'Trễ hạn'],
        [kpis.total, kpis.active, kpis.completed, kpis.overdue],
        [],
        cols.map((c) => c.label),
        ...list.map((p) => rowToArray(p, cols)),
    ];
    return meta.map((row) => row.map(escapeCsvCell).join(',')).join('\n');
}

/**
 * @param {{ list: object[], visibleKeys?: string[], summary?: object, format?: 'xlsx'|'csv' }} opts
 */
export function exportProjectList({ list, visibleKeys, summary = {}, format = 'xlsx' }) {
    if (!list?.length) return false;

    const { dd, mm, yyyy } = fileStamp();
    const filenameBase = `VA_DuAn_${yyyy}-${mm}-${dd}`;

    if (format === 'csv') {
        const content = '\uFEFF' + buildProjectListCsv(list, { visibleKeys, summary });
        const blob = new Blob([content], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${filenameBase}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
        return `${filenameBase}.csv`;
    }

    const ws = buildProjectListXlsx(list, { visibleKeys, summary });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'VA - Du an');
    const filename = `${filenameBase}.xlsx`;
    XLSX.writeFile(wb, filename);
    return filename;
}
