import XLSX from 'xlsx-js-style';
import { datetime } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

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

/** Cột tùy chọn có thể bật/tắt khi xuất — khóa khớp `EVALUATION_TABLE_COLUMNS`. */
export const OPTIONAL_EXPORT_COLUMNS = ['allow_half_score', 'description', 'creator', 'created_at', 'updated_at', 'status'];

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

const HEADERS_CORE_BEFORE_SCORES = [
    'STT',
    'Phạm vi',
    'Mã phòng',
    'Phòng ban',
    'Mã tiêu chí',
    'Tên tiêu chí',
    'Loại',
];

/** Cột tùy chọn: nhãn cột + hàm lấy giá trị dòng. */
const OPTIONAL_COLUMN_DEFS = {
    allow_half_score: {
        label: 'Chấm 0.5',
        value: (row) => (row.allow_half_score ? 'Có' : 'Không'),
    },
    status: {
        label: 'Trạng thái',
        value: (row) => (row.is_active ? 'Hoạt động' : 'Ngưng hoạt động'),
    },
    creator: {
        label: 'Người tạo',
        value: (row) => displayOrEmpty(row.creator?.display_name, EMPTY_LABELS.notUpdated),
    },
    description: {
        label: 'Mô tả',
        value: (row) => displayOrEmpty(row.description, EMPTY_LABELS.notUpdated),
    },
    created_at: {
        label: 'Ngày tạo',
        value: (row) => (row.created_at ? datetime(row.created_at) : EMPTY_LABELS.notUpdated),
    },
    updated_at: {
        label: 'Cập nhật',
        value: (row) => (row.updated_at ? datetime(row.updated_at) : EMPTY_LABELS.notUpdated),
    },
};

function formatScoreWeight(weight) {
    const n = Number(weight);
    if (!Number.isFinite(n)) return '';
    return n > 0 ? `+${n}` : String(n);
}

function maxScoreLevelCount(rows) {
    let max = 0;
    for (const row of rows) {
        const n = Array.isArray(row?.score_levels) ? row.score_levels.length : 0;
        if (n > max) max = n;
    }
    return max;
}

function scoreLevelHeaders(count) {
    return Array.from({ length: count }, (_, index) => `Mức ${index + 1}`);
}

function formatScoreLevelCell(level) {
    if (!level) return EMPTY_LABELS.notUpdated;
    const weight = formatScoreWeight(level.weight) || '0';
    const label = (level.label || '').trim() || EMPTY_LABELS.notUpdated;
    const desc = (level.description || '').trim();
    const parts = [label];
    if (desc) parts.push(desc);
    parts.push(weight);
    return parts.join(' · ');
}

/** @param {string[]|null} optionalColumns — null = tất cả (mặc định, tương thích ngược) */
function resolveOptionalColumns(optionalColumns) {
    return optionalColumns == null ? OPTIONAL_EXPORT_COLUMNS : OPTIONAL_EXPORT_COLUMNS.filter((k) => optionalColumns.includes(k));
}

function buildHeaders(rows, optionalColumns) {
    const scoreCount = maxScoreLevelCount(rows);
    const optional = resolveOptionalColumns(optionalColumns);
    return [
        ...HEADERS_CORE_BEFORE_SCORES,
        ...scoreLevelHeaders(scoreCount),
        ...optional.map((key) => OPTIONAL_COLUMN_DEFS[key].label),
    ];
}

function rowValues(row, index, scoreCount, optionalColumns) {
    const levels = Array.isArray(row.score_levels) ? row.score_levels : [];
    const scoreCells = Array.from({ length: scoreCount }, (_, i) => (
        formatScoreLevelCell(levels[i])
    ));
    const optional = resolveOptionalColumns(optionalColumns);

    return [
        index + 1,
        row.scope_label ?? row.scope ?? '',
        row.department_code ?? '',
        row.department_name ?? (row.scope === 'general' ? 'Tiêu chí chung' : ''),
        row.criteria_code ?? '',
        row.criteria_name ?? '',
        row.category ?? '',
        ...scoreCells,
        ...optional.map((key) => OPTIONAL_COLUMN_DEFS[key].value(row)),
    ];
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
    if (filters.scope) parts.push(`Phạm vi: ${filters.scope}`);
    if (filters.department_code) parts.push(`Phòng: ${filters.department_code}`);
    if (filters.category) parts.push(`Loại: ${filters.category}`);
    if (filters.status) parts.push(`Trạng thái: ${filters.status}`);
    return parts.length ? parts.join(' · ') : 'Không lọc';
}

function optionalColumnWidth(key) {
    return { allow_half_score: 10, status: 14, creator: 16, description: 28, created_at: 16, updated_at: 16 }[key] ?? 16;
}

/**
 * @param {Array<Record<string, unknown>>} rows
 * @param {Record<string, unknown>} [filters]
 * @param {{ total?: number }} [summary]
 * @param {{ columns?: string[]|null, scopeLabel?: string }} [options]
 */
export function exportEvaluationWorkbook(rows, filters = {}, summary = {}, options = {}) {
    const list = Array.isArray(rows) ? rows : [];
    const scoreCount = maxScoreLevelCount(list);
    const headers = buildHeaders(list, options.columns);
    const ws = {};
    const lastCol = Math.max(headers.length - 1, 0);

    setCell(ws, 0, 0, 'VA-Workspace — Cấu hình tiêu chí đánh giá', S.title);
    mergeRow(ws, 0, 0, lastCol);

    const exportedAt = datetime(new Date().toISOString());
    const noteText = options.scopeLabel ?? filterNote(filters);
    setCell(ws, 1, 0, `Xuất ngày ${exportedAt} · ${noteText}`, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);

    const kpis = [
        ['Tổng', summary.total ?? list.length],
        ['Chung', summary.general ?? ''],
        ['Theo PB', summary.department ?? ''],
        ['Hoạt động', summary.active ?? ''],
        ['Ngưng', summary.inactive ?? ''],
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
        rowValues(row, i, scoreCount, options.columns).forEach((val, c) => setCell(ws, r, c, val, style));
    });

    const lastRow = headerRow + Math.max(list.length, 1);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: lastRow, c: lastCol } });
    const optional = resolveOptionalColumns(options.columns);
    const widths = [
        5, 14, 10, 18, 12, 32, 14,
        ...Array.from({ length: scoreCount }, () => 28),
        ...optional.map(optionalColumnWidth),
    ];
    setColWidths(ws, widths);

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Tieu chi');
    const stamp = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `VA_TieuChiDanhGia_${stamp}.xlsx`);
}

/** @param {{ columns?: string[]|null }} [options] */
export function exportEvaluationCsv(rows, options = {}) {
    const list = Array.isArray(rows) ? rows : [];
    const scoreCount = maxScoreLevelCount(list);
    const headers = buildHeaders(list, options.columns);
    const lines = [headers.join(',')];
    list.forEach((row, i) => {
        const vals = rowValues(row, i, scoreCount, options.columns).map((v) => {
            const s = String(v ?? '');
            return /[",\n]/.test(s) ? `"${s.replace(/"/g, '""')}"` : s;
        });
        lines.push(vals.join(','));
    });
    const blob = new Blob([`\uFEFF${lines.join('\n')}`], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `VA_TieuChiDanhGia_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
