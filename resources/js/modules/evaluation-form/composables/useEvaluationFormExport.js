import XLSX from 'xlsx-js-style';
import { datetime } from '@/composables/useFormat';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const BRAND = '9A0036';
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
};

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((w) => ({ wch: w }));
}

/**
 * @param {Array<Record<string, any>>} rows
 * @param {{ filename?: string }} [opts]
 */
export function exportEvaluationFormsWorkbook(rows, opts = {}) {
    const wb = XLSX.utils.book_new();
    const ws = {};
    const headers = [
        'Mã phiếu',
        'Tên phiếu đánh giá',
        'Trạng thái',
        'Số tiêu chí',
        'Số nhân sự',
        'Kỳ đánh giá',
        'Hạn đánh giá',
        'Loại',
        'Mẫu',
        'Ngày tạo',
        'Người tạo',
    ];

    setCell(ws, 0, 0, 'Danh sách phiếu đánh giá', S.title);
    setCell(ws, 1, 0, `Xuất lúc ${datetime(new Date())}`, S.subtitle);
    ws['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: headers.length - 1 } },
        { s: { r: 1, c: 0 }, e: { r: 1, c: headers.length - 1 } },
    ];

    headers.forEach((h, c) => setCell(ws, 3, c, h, S.header));

    rows.forEach((row, i) => {
        const r = 4 + i;
        const style = i % 2 === 0 ? S.cell : S.cellAlt;
        const values = [
            row.form_code ?? '',
            row.name ?? '',
            row.status_label ?? row.status ?? '',
            row.criteria_count ?? 0,
            row.assignees_count ?? 0,
            displayOrEmpty(row.period_label, EMPTY_LABELS.notUpdated),
            row.deadline ?? '',
            displayOrEmpty(row.type_name, EMPTY_LABELS.notUpdated),
            displayOrEmpty(row.template_name, EMPTY_LABELS.notUpdated),
            row.created_at ? datetime(row.created_at) : '',
            displayOrEmpty(row.creator_name, EMPTY_LABELS.notUpdated),
        ];
        values.forEach((v, c) => setCell(ws, r, c, v, style));
    });

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: Math.max(3, 3 + rows.length), c: headers.length - 1 },
    });
    setColWidths(ws, [12, 36, 12, 12, 12, 18, 14, 18, 24, 18, 18]);

    XLSX.utils.book_append_sheet(wb, ws, 'Phieu danh gia');
    const filename = opts.filename
        || `VA_PhieuDanhGia_${new Date().toISOString().slice(0, 10)}.xlsx`;
    XLSX.writeFile(wb, filename);
}
