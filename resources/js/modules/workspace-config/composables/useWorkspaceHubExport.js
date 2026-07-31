import XLSX from 'xlsx-js-style';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';
const EMERALD = '059669';

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
    ok: {
        font: { bold: true, sz: 10, color: { rgb: EMERALD } },
        fill: { fgColor: { rgb: 'ECFDF5' } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
    empty: {
        font: { sz: 10, color: { rgb: SLATE_600 } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
};

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((wch) => ({ wch }));
}

function todayLabel() {
    return new Intl.DateTimeFormat('vi-VN', {
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date());
}

/**
 * @param {{
 *   workspaces: Array<Record<string, any>>,
 *   summary: Record<string, any>,
 *   coverage?: { modules: Array<{key:string,label:string}>, rows: Array<Record<string, any>> },
 * }} payload
 */
export function exportWorkspaceHubWorkbook(payload) {
    const workspaces = payload.workspaces ?? [];
    const summary = payload.summary ?? {};
    const coverage = payload.coverage ?? { modules: [], rows: [] };
    const wb = XLSX.utils.book_new();

    // --- Tong quan ---
    const overview = {};
    overview['!merges'] = [
        { s: { r: 0, c: 0 }, e: { r: 0, c: 5 } },
        { s: { r: 1, c: 0 }, e: { r: 1, c: 5 } },
    ];
    setCell(overview, 0, 0, 'VA-Workspace — Tổng quan cấu hình workspace', S.title);
    setCell(overview, 1, 0, `Xuất lúc ${todayLabel()}`, S.subtitle);

    const kpis = [
        ['Tổng phòng ban', summary.total ?? 0],
        ['Đang dùng', summary.active ?? 0],
        ['Chưa kích hoạt', summary.missing ?? 0],
        ['Đã sẵn sàng', summary.ready ?? 0],
        ['Đang cấu hình', summary.partial ?? 0],
        ['Tiêu chí đánh giá', summary.criteria_total ?? 0],
    ];
    kpis.forEach(([label, value], i) => {
        setCell(overview, 3, i, label, S.kpiLabel);
        setCell(overview, 4, i, value, S.kpiValue);
    });
    overview['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 4, c: 5 } });
    setColWidths(overview, [18, 16, 16, 16, 16, 18]);
    XLSX.utils.book_append_sheet(wb, overview, 'Tong quan');

    // --- Phong ban ---
    const headers = [
        'STT',
        'Mã phòng',
        'Phòng ban',
        'Trạng thái profile',
        'Mức sẵn sàng',
        '% sẵn sàng',
        'Tiêu chí PB',
        'Module đã cấu hình',
        'Module live',
        'Nguồn',
        'Ghi chú',
    ];
    const sheet = {};
    headers.forEach((h, c) => setCell(sheet, 0, c, h, S.header));
    workspaces.forEach((ws, i) => {
        const r = i + 1;
        const style = i % 2 ? S.cellAlt : S.cell;
        const vals = [
            i + 1,
            ws.department_code,
            ws.department_name,
            ws.status_label,
            ws.readiness?.label ?? EMPTY_LABELS.generic,
            ws.readiness?.percent ?? 0,
            ws.criteria_count ?? 0,
            ws.modules_configured ?? 0,
            ws.modules_live ?? 0,
            displayOrEmpty(ws.source_label, EMPTY_LABELS.notUpdated),
            displayOrEmpty(ws.notes, EMPTY_LABELS.notUpdated),
        ];
        vals.forEach((v, c) => setCell(sheet, r, c, v, style));
    });
    const lastRow = Math.max(1, workspaces.length);
    sheet['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: lastRow, c: headers.length - 1 },
    });
    setColWidths(sheet, [6, 12, 28, 16, 16, 12, 12, 14, 12, 16, 28]);
    XLSX.utils.book_append_sheet(wb, sheet, 'Phong ban');

    // --- Ma tran ---
    const mods = coverage.modules ?? [];
    const rows = coverage.rows ?? [];
    const matrixHeaders = ['STT', 'Mã phòng', 'Phòng ban', 'Trạng thái', ...mods.map((m) => m.label)];
    const matrix = {};
    matrixHeaders.forEach((h, c) => setCell(matrix, 0, c, h, S.header));
    rows.forEach((row, i) => {
        const r = i + 1;
        const base = i % 2 ? S.cellAlt : S.cell;
        setCell(matrix, r, 0, i + 1, base);
        setCell(matrix, r, 1, row.department_code, base);
        setCell(matrix, r, 2, row.department_name, base);
        setCell(matrix, r, 3, row.status_label, base);
        mods.forEach((mod, mi) => {
            const cell = row.cells?.[mod.key];
            const configured = Boolean(cell?.configured);
            const label = configured
                ? (cell.count != null ? `Có (${cell.count})` : 'Đã cấu hình')
                : 'Chưa cấu hình';
            setCell(matrix, r, 4 + mi, label, configured ? S.ok : S.empty);
        });
    });
    const mLast = Math.max(1, rows.length);
    matrix['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: mLast, c: matrixHeaders.length - 1 },
    });
    setColWidths(matrix, [6, 12, 28, 14, ...mods.map(() => 18)]);
    XLSX.utils.book_append_sheet(wb, matrix, 'Ma tran');

    const stamp = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `VA_Workspace_Config_${stamp}.xlsx`);
}
