import XLSX from 'xlsx-js-style';
import { datetime } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import { TEMPLATE_EXPORT_COLUMN_OPTIONS } from '@/modules/evaluation-template/config/columns.js';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';

export const EXPORT_FORMATS = [
    { value: 'xlsx', label: 'Excel (.xlsx)', description: 'Có định dạng màu, dùng để lưu trữ hoặc in ấn.' },
    { value: 'csv', label: 'CSV (.csv)', description: 'Dữ liệu thô, dùng để nhập vào hệ thống khác.' },
];

function borderThin() {
    return {
        top: { style: 'thin', color: { rgb: SLATE_200 } },
        bottom: { style: 'thin', color: { rgb: SLATE_200 } },
        left: { style: 'thin', color: { rgb: SLATE_200 } },
        right: { style: 'thin', color: { rgb: SLATE_200 } },
    };
}

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
};

const COLUMN_DEFS = {
    template_code: {
        label: 'Mã mẫu',
        width: 12,
        value: (row) => row.template_code ?? '',
    },
    name: {
        label: 'Tên mẫu đánh giá',
        width: 32,
        value: (row) => row.name ?? '',
    },
    position: {
        label: 'Vị trí đánh giá',
        width: 24,
        value: (row) => displayOrEmpty(row.position_name, EMPTY_LABELS.notUpdated),
    },
    criteria_count: {
        label: 'Số tiêu chí',
        width: 12,
        value: (row) => row.criteria_count ?? (Array.isArray(row.criteria) ? row.criteria.length : 0),
    },
    criteria: {
        label: 'Tiêu chí đánh giá',
        width: 40,
        value: (row) => {
            const labels = Array.isArray(row.criteria_labels) ? row.criteria_labels : [];
            if (labels.length) return labels.join('; ');
            const lines = Array.isArray(row.criteria) ? row.criteria : [];
            return lines.map((c) => c.criteria_name).filter(Boolean).join('; ') || EMPTY_LABELS.notUpdated;
        },
    },
    creator: {
        label: 'Người tạo',
        width: 18,
        value: (row) => displayOrEmpty(row.creator?.display_name, EMPTY_LABELS.notUpdated),
    },
    created_at: {
        label: 'Ngày tạo',
        width: 16,
        value: (row) => (row.created_at ? datetime(row.created_at) : EMPTY_LABELS.notUpdated),
    },
    updated_at: {
        label: 'Ngày cập nhật',
        width: 16,
        value: (row) => (row.updated_at ? datetime(row.updated_at) : EMPTY_LABELS.notUpdated),
    },
    status: {
        label: 'Trạng thái',
        width: 14,
        value: (row) => (row.is_active ? 'Hoạt động' : 'Ngưng hoạt động'),
    },
    description: {
        label: 'Mô tả',
        width: 28,
        value: (row) => displayOrEmpty(row.description, EMPTY_LABELS.notUpdated),
    },
};

function resolveColumns(selectedKeys) {
    const allowed = new Set(TEMPLATE_EXPORT_COLUMN_OPTIONS.map((c) => c.key));
    const keys = Array.isArray(selectedKeys) && selectedKeys.length
        ? selectedKeys.filter((k) => allowed.has(k) && COLUMN_DEFS[k])
        : TEMPLATE_EXPORT_COLUMN_OPTIONS.filter((c) => c.core).map((c) => c.key);

    return ['stt', ...keys.filter((k) => k !== 'stt')];
}

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = {
        v: value ?? '',
        t: typeof value === 'number' ? 'n' : 's',
        s: style,
    };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((wch) => ({ wch }));
}

function filterNote(filters = {}) {
    const parts = [];
    if (filters.q) parts.push(`Từ khóa: ${filters.q}`);
    if (filters.position_code) parts.push(`Vị trí: ${filters.position_code}`);
    if (filters.status) parts.push(`Trạng thái: ${filters.status}`);
    return parts.length ? parts.join(' · ') : 'Không lọc';
}

function buildHeaders(columnKeys) {
    return columnKeys.map((key) => (key === 'stt' ? 'STT' : COLUMN_DEFS[key].label));
}

function rowValues(row, index, columnKeys) {
    return columnKeys.map((key) => {
        if (key === 'stt') return index + 1;
        return COLUMN_DEFS[key].value(row);
    });
}

function stamp() {
    return new Date().toISOString().slice(0, 10);
}

/**
 * @returns {{ filename: string, rowCount: number }}
 */
export function exportTemplateWorkbook(rows, filters = {}, summary = {}, options = {}) {
    const list = Array.isArray(rows) ? rows : [];
    const columnKeys = resolveColumns(options.columns);
    const headers = buildHeaders(columnKeys);
    const ws = {};
    const lastCol = Math.max(headers.length - 1, 0);

    setCell(ws, 0, 0, 'VA-Workspace — Danh sách mẫu đánh giá', S.title);
    mergeRow(ws, 0, 0, lastCol);

    const exportedAt = datetime(new Date().toISOString());
    const noteText = options.scopeLabel ?? filterNote(filters);
    setCell(ws, 1, 0, `Xuất ngày ${exportedAt} · ${noteText}`, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);

    const kpis = [
        ['Tổng', summary.total ?? list.length],
        ['Hoạt động', summary.active ?? ''],
        ['Ngưng', summary.inactive ?? ''],
        ['Có vị trí', summary.with_position ?? ''],
        ['Có tiêu chí', summary.with_criteria ?? ''],
    ];
    kpis.forEach(([label, value], i) => {
        setCell(ws, 3, i, label, S.kpiLabel);
        setCell(ws, 4, i, value, S.kpiValue);
    });

    const headerRow = 6;
    headers.forEach((h, c) => setCell(ws, headerRow, c, h, S.header));

    list.forEach((row, i) => {
        const r = headerRow + 1 + i;
        const style = i % 2 === 0 ? S.cell : S.cellAlt;
        rowValues(row, i, columnKeys).forEach((val, c) => setCell(ws, r, c, val, style));
    });

    const lastRow = headerRow + Math.max(list.length, 1);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: lastRow, c: lastCol } });
    setColWidths(ws, columnKeys.map((key) => (key === 'stt' ? 5 : (COLUMN_DEFS[key]?.width ?? 16))));

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Mau danh gia');
    const filename = `VA_MauDanhGia_${stamp()}.xlsx`;
    XLSX.writeFile(wb, filename);

    return { filename, rowCount: list.length };
}

/**
 * @returns {{ filename: string, rowCount: number }}
 */
export function exportTemplateCsv(rows, options = {}) {
    const list = Array.isArray(rows) ? rows : [];
    const columnKeys = resolveColumns(options.columns);
    const headers = buildHeaders(columnKeys);
    const lines = [headers.join(',')];
    list.forEach((row, i) => {
        const vals = rowValues(row, i, columnKeys).map((v) => {
            const s = String(v ?? '');
            return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
        });
        lines.push(vals.join(','));
    });
    const blob = new Blob([`\uFEFF${lines.join('\n')}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const filename = `VA_MauDanhGia_${stamp()}.csv`;
    a.download = filename;
    a.click();
    URL.revokeObjectURL(url);

    return { filename, rowCount: list.length };
}
