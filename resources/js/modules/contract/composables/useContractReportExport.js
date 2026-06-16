import XLSX from 'xlsx-js-style';

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
    title: { font: { bold: true, sz: 16, color: { rgb: BRAND } }, alignment: { horizontal: 'left', vertical: 'center' } },
    subtitle: { font: { sz: 10, color: { rgb: SLATE_600 }, italic: true } },
    header: { font: { bold: true, sz: 10, color: { rgb: WHITE } }, fill: { fgColor: { rgb: BRAND } }, alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: borderThin() },
    cell: { font: { sz: 10, color: { rgb: '1E293B' } }, alignment: { vertical: 'center' }, border: borderThin() },
    cellAlt: { font: { sz: 10, color: { rgb: '1E293B' } }, fill: { fgColor: { rgb: SLATE_50 } }, alignment: { vertical: 'center' }, border: borderThin() },
    num: { font: { sz: 10, color: { rgb: '1E293B' } }, alignment: { horizontal: 'right', vertical: 'center' }, border: borderThin() },
    totalLabel: { font: { bold: true, sz: 10, color: { rgb: BRAND } }, border: borderThin() },
    totalNum: { font: { bold: true, sz: 10, color: { rgb: BRAND } }, alignment: { horizontal: 'right' }, border: borderThin() },
};

function setCell(ws, r, c, value, style, type = 's') {
    ws[XLSX.utils.encode_cell({ r, c })] = { v: value ?? '', t: type, s: style };
}
function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}
function fileStamp() {
    const t = new Date();
    return `${String(t.getDate()).padStart(2, '0')}${String(t.getMonth() + 1).padStart(2, '0')}${t.getFullYear()}`;
}

/**
 * Xuất báo cáo hợp đồng theo chiều (rows: [{label,count,annual_cost,lifecycle_cost}]).
 */
export function downloadReportExcel(rows, { dimensionLabel = 'Tổng hợp' } = {}) {
    const ws = {};
    const headers = [dimensionLabel, 'Số HĐ', 'Chi phí năm', 'Chi phí vòng đời'];
    const COLS = headers.length - 1;

    setCell(ws, 0, 0, `BÁO CÁO HỢP ĐỒNG — ${dimensionLabel.toUpperCase()}`, S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, `Xuất ngày ${new Date().toLocaleDateString('vi-VN')} · VAschools QLDA`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);

    headers.forEach((h, c) => setCell(ws, 3, c, h, S.header));

    rows.forEach((row, idx) => {
        const r = 4 + idx;
        const base = idx % 2 === 0 ? S.cell : S.cellAlt;
        setCell(ws, r, 0, row.label, base);
        setCell(ws, r, 1, row.count, S.num, 'n');
        setCell(ws, r, 2, Number(row.annual_cost) || 0, S.num, 'n');
        setCell(ws, r, 3, Number(row.lifecycle_cost) || 0, S.num, 'n');
    });

    const totalRow = 4 + rows.length;
    setCell(ws, totalRow, 0, 'Tổng cộng', S.totalLabel);
    setCell(ws, totalRow, 1, rows.reduce((s, r) => s + (r.count || 0), 0), S.totalNum, 'n');
    setCell(ws, totalRow, 2, rows.reduce((s, r) => s + (Number(r.annual_cost) || 0), 0), S.totalNum, 'n');
    setCell(ws, totalRow, 3, rows.reduce((s, r) => s + (Number(r.lifecycle_cost) || 0), 0), S.totalNum, 'n');

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: COLS } });
    ws['!cols'] = [{ wch: 32 }, { wch: 10 }, { wch: 18 }, { wch: 20 }];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Bao cao');
    XLSX.writeFile(wb, `VA_BaoCao_HopDong_${fileStamp()}.xlsx`);
}

/** Xuất CSV (UTF-8 BOM để Excel mở đúng tiếng Việt). */
export function downloadReportCsv(rows, { dimensionLabel = 'Nhóm' } = {}) {
    const headers = [dimensionLabel, 'So HD', 'Chi phi nam', 'Chi phi vong doi'];
    const lines = [headers.join(',')];
    for (const r of rows) {
        const cells = [
            `"${String(r.label).replace(/"/g, '""')}"`,
            r.count ?? 0,
            Number(r.annual_cost) || 0,
            Number(r.lifecycle_cost) || 0,
        ];
        lines.push(cells.join(','));
    }
    const blob = new Blob(['﻿' + lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `VA_BaoCao_HopDong_${fileStamp()}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}
