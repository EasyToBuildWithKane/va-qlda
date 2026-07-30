import XLSX from 'xlsx-js-style';
import { date, datetime } from '@/composables/useFormat';
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
    'Mã phòng',
    'Phòng ban',
    'Tên cấu hình',
    'Loại',
    'Từ ngày',
    'Đến ngày',
    'Số tiêu chí',
    'Điểm gốc',
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

function formatRange(from, to) {
    const a = from ? date(from) : null;
    const b = to ? date(to) : null;
    if (!a && !b) return EMPTY_LABELS.period;
    if (a && !b) return `${a} trở đi`;
    if (!a && b) return `đến ${b}`;
    return `${a} – ${b}`;
}

function filterNote(filters = {}) {
    const parts = [];
    if (filters.q) parts.push(`Từ khóa: ${filters.q}`);
    if (filters.department_code) parts.push(`Phòng: ${filters.department_code}`);
    if (filters.template_type) parts.push(`Loại: ${filters.template_type}`);
    if (filters.status) parts.push(`Trạng thái: ${filters.status}`);
    if (filters.effective_from || filters.effective_to) {
        parts.push(`Hiệu lực: ${formatRange(filters.effective_from, filters.effective_to)}`);
    }
    return parts.length ? parts.join(' · ') : 'Không lọc';
}

function rowValues(row, index) {
    return [
        index + 1,
        row.department_code ?? '',
        row.department_name ?? '',
        row.config_name ?? '',
        row.template_type_label ?? row.template_type ?? '',
        row.effective_from ? date(row.effective_from) : EMPTY_LABELS.period,
        row.effective_to ? date(row.effective_to) : 'Không giới hạn',
        row.criteria_count ?? 0,
        row.base_score ?? '',
        row.is_active ? 'Đang bật' : 'Đã tắt',
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
    const range = { s: { r: 0, c: 0 }, e: { r: 0, c: lastCol } };

    setCell(ws, 0, 0, 'VA-Workspace — Cấu hình đánh giá', S.title);
    mergeRow(ws, 0, 0, lastCol);

    const exportedAt = datetime(new Date().toISOString());
    setCell(ws, 1, 0, `Xuất ngày ${exportedAt} · ${filterNote(filters)}`, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);

    const kpis = [
        ['Tổng', summary.total ?? list.length],
        ['Đang bật', summary.active ?? ''],
        ['Hiệu lực', summary.effective ?? ''],
        ['Điểm cộng/trừ', summary.point_system ?? ''],
        ['Phiếu tiêu chí', summary.scorecard ?? ''],
    ];
    kpis.forEach(([label, value], i) => {
        setCell(ws, 3, i, label, S.kpiLabel);
        setCell(ws, 4, i, value === '' || value == null ? 0 : value, S.kpiValue);
    });

    const headerRow = 6;
    HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h, S.header));

    list.forEach((row, i) => {
        const r = headerRow + 1 + i;
        const style = i % 2 === 0 ? S.cell : S.cellAlt;
        rowValues(row, i).forEach((v, c) => setCell(ws, r, c, v, style));
        range.e.r = r;
    });

    if (list.length === 0) {
        range.e.r = headerRow;
    }

    ws['!ref'] = XLSX.utils.encode_range(range);
    setColWidths(ws, [5, 12, 22, 28, 16, 12, 12, 10, 10, 12, 18, 28, 16, 16]);

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Cau hinh DG');
    const stamp = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `VA_CauHinhDanhGia_${stamp}.xlsx`);
}
