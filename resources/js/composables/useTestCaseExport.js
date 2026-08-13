import XLSX from 'xlsx-js-style';
import { datetime } from '@/composables/useFormat';

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

const HEADERS = [
    'STT', 'Mã', 'Tiêu đề', 'Nhóm kiểm thử', 'Mức độ ưu tiên', 'Trạng thái',
    'Kết quả cuối', 'Người phụ trách', 'Điều kiện tiên quyết', 'Kết quả mong đợi', 'Cập nhật',
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
    ws[ref] = { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((wch) => ({ wch }));
}

function safeCode(code) {
    return String(code ?? 'DA').replace(/[:\\/?*[\]]/g, '_');
}

function fileStamp() {
    const t = new Date();
    const dd = String(t.getDate()).padStart(2, '0');
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const yyyy = t.getFullYear();
    return `${yyyy}-${mm}-${dd}`;
}

function priorityStyle(ws, r, c, priority, baseStyle) {
    const ref = XLSX.utils.encode_cell({ r, c });
    if (priority === 'critical') {
        ws[ref].s = { ...baseStyle, font: { bold: true, sz: 10, color: { rgb: 'BE123C' } }, fill: { fgColor: { rgb: 'FFE4E6' } } };
    } else if (priority === 'high') {
        ws[ref].s = { ...baseStyle, font: { bold: true, sz: 10, color: { rgb: 'C2410C' } }, fill: { fgColor: { rgb: 'FFEDD5' } } };
    }
}

function resultStyle(ws, r, c, result, baseStyle) {
    const ref = XLSX.utils.encode_cell({ r, c });
    if (result === 'pass') {
        ws[ref].s = { ...baseStyle, font: { bold: true, sz: 10, color: { rgb: '047857' } }, fill: { fgColor: { rgb: 'ECFDF5' } } };
    } else if (result === 'fail') {
        ws[ref].s = { ...baseStyle, font: { bold: true, sz: 10, color: { rgb: 'BE123C' } }, fill: { fgColor: { rgb: 'FFE4E6' } } };
    }
}

function rowToArray(row, idx) {
    return [
        idx + 1,
        row.code ?? '',
        row.title ?? '',
        row.suite?.name ?? '',
        row.priority?.label ?? '',
        row.status?.label ?? '',
        row.last_result?.label ?? 'Chưa chạy',
        row.owner?.name ?? '',
        (row.preconditions ?? '').slice(0, 500),
        (row.expected_result ?? '').slice(0, 500),
        datetime(row.updated_at) || '',
    ];
}

function computeKpis(list) {
    return {
        total: list.length,
        ready: list.filter((r) => r.status?.value === 'ready').length,
        pass: list.filter((r) => r.last_result?.value === 'pass').length,
        fail: list.filter((r) => r.last_result?.value === 'fail').length,
        not_run: list.filter((r) => !r.last_result?.value).length,
    };
}

function buildTestCaseXlsx(list, { projectCode, projectName }) {
    const ws = {};
    const COLS = HEADERS.length - 1;
    const exportedAt = datetime(new Date().toISOString());
    const kpis = computeKpis(list);

    setCell(ws, 0, 0, `QA / TEST CASE — ${projectName || projectCode}`, S.title);
    mergeRow(ws, 0, 0, COLS);

    setCell(ws, 1, 0, `Mã dự án: ${projectCode} · Xuất lúc ${exportedAt} · VAschools`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);

    const kpiLabels = ['Tổng', 'Sẵn sàng', 'Đạt', 'Không đạt', 'Chưa chạy'];
    const kpiValues = [kpis.total, kpis.ready, kpis.pass, kpis.fail, kpis.not_run];
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
        priorityStyle(ws, r, 4, row.priority?.value, rowStyle);
        resultStyle(ws, r, 6, row.last_result?.value, rowStyle);
    });

    const totalRow = headerRow + 1 + list.length;
    setCell(ws, totalRow, 0, `Tổng: ${list.length} test case`, S.total);
    mergeRow(ws, totalRow, 0, COLS);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: COLS } });
    setColWidths(ws, [6, 10, 36, 18, 16, 16, 14, 20, 30, 30, 18]);

    return ws;
}

/**
 * Export test cases to a styled XLSX workbook.
 * @param {{ list: object[], projectCode: string, projectName: string }} opts
 */
export function exportTestCasesWorkbook({ list, projectCode, projectName }) {
    const ws = buildTestCaseXlsx(list, { projectCode, projectName });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Test case');

    const stamp = fileStamp();
    const code = safeCode(projectCode);
    XLSX.writeFile(wb, `VA_TestCase_${code}_${stamp}.xlsx`);
}

export const useTestCaseExport = () => ({ exportTestCasesWorkbook });
