import XLSX from 'xlsx-js-style';
import { datetime } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';

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

const HEADERS = [
    'STT',
    'Phạm vi',
    'Mã phòng',
    'Phòng ban',
    'Mã tiêu chí',
    'Tên tiêu chí',
    'Loại',
    'Chấm 0.5',
    'Điểm 1',
    'Điểm 2',
    'Điểm 3',
    'Điểm 4',
    'Điểm 5',
    'Trạng thái',
    'Người tạo',
    'Mô tả',
    'Ngày tạo',
    'Cập nhật',
];

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

function rowValues(row, index) {
    return [
        index + 1,
        row.scope_label ?? row.scope ?? '',
        row.department_code ?? '',
        row.department_name ?? (row.scope === 'general' ? 'Tiêu chí chung' : ''),
        row.criteria_code ?? '',
        row.criteria_name ?? '',
        row.category ?? '',
        row.allow_half_score ? 'Có' : 'Không',
        row.score_1 ?? '',
        row.score_2 ?? '',
        row.score_3 ?? '',
        row.score_4 ?? '',
        row.score_5 ?? '',
        row.is_active ? 'Hoạt động' : 'Ngưng hoạt động',
        displayOrEmpty(row.creator?.display_name, EMPTY_LABELS.notUpdated),
        displayOrEmpty(row.description, EMPTY_LABELS.notUpdated),
        row.created_at ? datetime(row.created_at) : EMPTY_LABELS.notUpdated,
        row.updated_at ? datetime(row.updated_at) : EMPTY_LABELS.notUpdated,
    ];
}

/**
 * @param {Array<Record<string, unknown>>} rows
 * @param {Record<string, unknown>} [filters]
 * @param {{ total?: number }} [summary]
 */
export function exportEvaluationWorkbook(rows, filters = {}, summary = {}) {
    const list = Array.isArray(rows) ? rows : [];
    const ws = {};
    const lastCol = HEADERS.length - 1;

    setCell(ws, 0, 0, 'VA-Workspace — Cấu hình tiêu chí đánh giá', S.title);
    mergeRow(ws, 0, 0, lastCol);

    const exportedAt = datetime(new Date().toISOString());
    setCell(ws, 1, 0, `Xuất ngày ${exportedAt} · ${filterNote(filters)}`, S.subtitle);
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
    HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h, S.header));

    list.forEach((row, i) => {
        const r = headerRow + 1 + i;
        const style = i % 2 === 0 ? S.cell : S.cellAlt;
        rowValues(row, i).forEach((val, c) => setCell(ws, r, c, val, style));
    });

    const lastRow = headerRow + Math.max(list.length, 1);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: lastRow, c: lastCol } });
    setColWidths(ws, [5, 14, 10, 18, 10, 32, 14, 10, 16, 16, 16, 14, 14, 14, 16, 28, 16, 16]);

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Tieu chi');
    const stamp = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `VA_TieuChiDanhGia_${stamp}.xlsx`);
}

export function exportEvaluationCsv(rows) {
    const list = Array.isArray(rows) ? rows : [];
    const lines = [HEADERS.join(',')];
    list.forEach((row, i) => {
        const vals = rowValues(row, i).map((v) => {
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
