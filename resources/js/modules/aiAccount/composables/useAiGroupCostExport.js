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
        alignment: { horizontal: 'left', wrapText: true },
    },
    section: {
        font: { bold: true, sz: 11, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { vertical: 'center' },
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
    money: {
        font: { sz: 10, color: { rgb: '334155' } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
        numFmt: '#,##0',
    },
    moneyBold: {
        font: { bold: true, sz: 10, color: { rgb: BRAND } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
        numFmt: '#,##0',
    },
    totalLabel: {
        font: { bold: true, sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'left', vertical: 'center' },
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
    const isNum = typeof value === 'number' && Number.isFinite(value);
    ws[ref] = {
        v: value ?? '',
        t: isNum ? 'n' : 's',
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

function fileStamp() {
    const t = new Date();
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const dd = String(t.getDate()).padStart(2, '0');
    return `${t.getFullYear()}-${mm}-${dd}`;
}

function groupLabel(row, options) {
    const opt = options?.group_function?.find((o) => o.value === row.group);
    return opt?.label ?? row.group_label ?? row.group;
}

function buildOverviewSheet(rows, cards, filterNote) {
    const ws = {};
    const lastCol = 3;
    const exportedAt = datetime(new Date().toISOString());
    const totalMonthly = cards?.monthly_cost_running ?? rows.reduce((s, r) => s + (r.cost_monthly ?? 0), 0);
    const totalAccounts = rows.reduce((s, r) => s + (r.total_accounts ?? 0), 0);
    const totalPending = rows.reduce((s, r) => s + (r.proposal_monthly_pending_sync ?? 0), 0);

    setCell(ws, 0, 0, 'BÁO CÁO CHI PHÍ AI THEO NHÓM — VAschools', S.title);
    mergeRow(ws, 0, 0, lastCol);

    const sub = filterNote
        ? `Xuất lúc ${exportedAt} · ${filterNote}`
        : `Xuất lúc ${exportedAt}`;
    setCell(ws, 1, 0, sub, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);

    setCell(ws, 3, 0, 'Chỉ số tổng hợp', S.section);
    mergeRow(ws, 3, 0, lastCol);

    const kpis = [
        ['Chi phí / tháng (phiếu đã duyệt)', totalMonthly],
        ['Ước tính / năm', totalMonthly * 12],
        ['Tổng tài khoản AI', totalAccounts],
        ['Chi phí phiếu chưa lập TK', totalPending],
        ['Số nhóm có phát sinh', rows.length],
    ];
    kpis.forEach(([label, val], i) => {
        const r = 4 + i;
        setCell(ws, r, 0, label, S.cell);
        const isMoney = i <= 1;
        setCell(ws, r, 1, isMoney ? val : val, isMoney ? S.moneyBold : S.cell);
        if (isMoney) setCell(ws, r, 2, 'VNĐ', S.cell);
    });

    setCell(ws, 10, 0, 'Ghi chú', S.section);
    mergeRow(ws, 10, 0, lastCol);
    setCell(ws, 11, 0, 'Chi phí tính từ phiếu đề xuất ở trạng thái Đã duyệt / Đã mua / Đang sử dụng. '
        + 'Cột «Phiếu chưa lập TK» là chi phí đã duyệt nhưng chưa tạo tài khoản trên hệ thống.', S.subtitle);
    mergeRow(ws, 11, 0, lastCol);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 11, c: lastCol } });
    setColWidths(ws, [36, 18, 10, 8]);
    ws['!rows'] = [{ hpt: 28 }, { hpt: 22 }];

    return ws;
}

function buildGroupSheet(rows, options, filterNote) {
    const headers = [
        'Mã nhóm',
        'Tên nhóm',
        'Tổng TK',
        'Đang hoạt động',
        'Sắp hết hạn',
        'Đã hết hạn',
        'Đã huỷ',
        'Chi phí / tháng (VNĐ)',
        'Phiếu chưa lập TK (VNĐ)',
        'Tỷ trọng (%)',
        'Ước tính / năm (VNĐ)',
    ];
    const ws = {};
    const lastCol = headers.length - 1;
    const exportedAt = datetime(new Date().toISOString());

    setCell(ws, 0, 0, 'CHI PHÍ THEO NHÓM CHỨC NĂNG', S.title);
    mergeRow(ws, 0, 0, lastCol);
    setCell(ws, 1, 0, filterNote ? `${exportedAt} · ${filterNote}` : exportedAt, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);

    const headerRow = 3;
    headers.forEach((h, i) => setCell(ws, headerRow, i, h, S.header));

    rows.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        const alt = idx % 2 === 0 ? S.cell : S.cellAlt;
        const monthly = row.cost_monthly ?? 0;
        const values = [
            row.group,
            groupLabel(row, options),
            row.total_accounts ?? 0,
            row.active_accounts ?? 0,
            row.expiring_soon ?? 0,
            row.expired ?? 0,
            row.cancelled ?? 0,
            monthly,
            row.proposal_monthly_pending_sync ?? 0,
            row.cost_share_percent ?? 0,
            monthly * 12,
        ];
        values.forEach((val, ci) => {
            const moneyCols = [7, 8, 10];
            const pctCol = ci === 9;
            let style = alt;
            if (moneyCols.includes(ci)) style = S.money;
            if (pctCol) style = { ...alt, alignment: { horizontal: 'center', vertical: 'center' } };
            setCell(ws, r, ci, val, style);
        });
    });

    const totalRow = headerRow + 1 + rows.length;
    const totalMonthly = rows.reduce((s, r) => s + (r.cost_monthly ?? 0), 0);
    setCell(ws, totalRow, 0, 'TỔNG CỘNG', S.totalLabel);
    mergeRow(ws, totalRow, 0, 1);
    setCell(ws, totalRow, 2, rows.reduce((s, r) => s + (r.total_accounts ?? 0), 0), S.totalLabel);
    setCell(ws, totalRow, 3, rows.reduce((s, r) => s + (r.active_accounts ?? 0), 0), S.totalLabel);
    setCell(ws, totalRow, 7, totalMonthly, S.moneyBold);
    setCell(ws, totalRow, 8, rows.reduce((s, r) => s + (r.proposal_monthly_pending_sync ?? 0), 0), S.moneyBold);
    setCell(ws, totalRow, 9, 100, S.totalLabel);
    setCell(ws, totalRow, 10, totalMonthly * 12, S.moneyBold);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: lastCol } });
    setColWidths(ws, [10, 22, 10, 12, 12, 12, 10, 18, 18, 12, 18]);
    ws['!rows'] = [{ hpt: 26 }, { hpt: 18 }, { hpt: 8 }, { hpt: 22 }];

    return ws;
}

function buildGuideSheet() {
    const ws = {};
    const lines = [
        ['HƯỚNG DẪN ĐỌC BÁO CÁO'],
        [''],
        ['1. Chi phí / tháng', 'Tổng từ phiếu đề xuất đã duyệt (approved, purchased, active).'],
        ['2. Phiếu chưa lập TK', 'Đã duyệt nhưng chưa tạo tài khoản AI — vẫn tính vào ngân sách nhóm.'],
        ['3. Tỷ trọng', 'Phần trăm chi phí nhóm so với tổng toàn công ty.'],
        ['4. Thống kê TK', 'Số tài khoản thực tế trên VA QLDA (có thể ít hơn số phiếu đã duyệt).'],
    ];
    lines.forEach((row, r) => {
        row.forEach((cell, c) => {
            const style = r === 0 ? S.title : r === 1 ? S.cell : S.cell;
            setCell(ws, r, c, cell, style);
        });
    });
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: lines.length - 1, c: 1 } });
    setColWidths(ws, [28, 52]);
    return ws;
}

function buildCsv(rows, options, cards, filterNote) {
    const exportedAt = datetime(new Date().toISOString());
    const headers = [
        'Mã nhóm', 'Tên nhóm', 'Tổng TK', 'Active', 'Sắp HH', 'Hết hạn', 'Huỷ',
        'Chi phí tháng', 'Phiếu chưa lập TK', 'Tỷ trọng %', 'Ước tính năm',
    ];
    const escape = (v) => `"${String(v ?? '').replace(/"/g, '""')}"`;
    const meta = [
        ['BÁO CÁO CHI PHÍ AI THEO NHÓM'],
        ['Ngày xuất', exportedAt],
        ['Chi phí tổng / tháng', cards?.monthly_cost_running ?? ''],
    ];
    if (filterNote) meta.push(['Bộ lọc', filterNote]);
    const body = rows.map((row) => [
        row.group,
        groupLabel(row, options),
        row.total_accounts ?? 0,
        row.active_accounts ?? 0,
        row.expiring_soon ?? 0,
        row.expired ?? 0,
        row.cancelled ?? 0,
        row.cost_monthly ?? 0,
        row.proposal_monthly_pending_sync ?? '',
        row.cost_share_percent ?? 0,
        (row.cost_monthly ?? 0) * 12,
    ]);
    return '\uFEFF' + [...meta, [], headers, ...body]
        .map((row) => row.map(escape).join(','))
        .join('\n');
}

/**
 * @param {{ rows: object[], cards?: object, options?: object, filterNote?: string, format?: 'xlsx'|'csv' }} opts
 */
export function exportAiGroupCost({ rows, cards = null, options = null, filterNote = '', format = 'xlsx' }) {
    if (!rows?.length) return false;

    const iso = fileStamp();
    const filenameBase = `VA_ChiPhiAI_Nhom_${iso}`;

    if (format === 'csv') {
        const blob = new Blob([buildCsv(rows, options, cards, filterNote)], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${filenameBase}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
        return `${filenameBase}.csv`;
    }

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildOverviewSheet(rows, cards, filterNote), 'Tong quan');
    XLSX.utils.book_append_sheet(wb, buildGroupSheet(rows, options, filterNote), 'Chi phi theo nhom');
    XLSX.utils.book_append_sheet(wb, buildGuideSheet(), 'Huong dan');
    const filename = `${filenameBase}.xlsx`;
    XLSX.writeFile(wb, filename);
    return filename;
}
