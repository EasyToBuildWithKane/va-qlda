import XLSX from 'xlsx-js-style';
import { datetime } from '@/composables/useFormat';
import { ANALYTICS_REPORT_COLUMNS } from '@/modules/aiAccount/config/analyticsReportColumns';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';
const WARN = 'FEF3C7';
const DANGER = 'FEE2E2';

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
    warn: {
        font: { sz: 10, color: { rgb: '92400E' } },
        fill: { fgColor: { rgb: WARN } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    danger: {
        font: { sz: 10, color: { rgb: '991B1B' } },
        fill: { fgColor: { rgb: DANGER } },
        alignment: { vertical: 'center', wrapText: true },
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
    ws[ref] = { v: value ?? '', t: isNum ? 'n' : 's', s: style };
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
    return `${t.getFullYear()}-${String(t.getMonth() + 1).padStart(2, '0')}-${String(t.getDate()).padStart(2, '0')}`;
}

/** @param {string[]} visibleKeys */
function resolveExportColumns(visibleKeys) {
    const keys = (visibleKeys ?? []).filter(Boolean);
    if (keys.length > 0) {
        return keys;
    }
    return ANALYTICS_REPORT_COLUMNS.filter((c) => c.default).map((c) => c.key);
}

/** Chuẩn hoá dữ liệu sheet sản phẩm (dashboard vs stats footer). */
function normalizeProductRows(byProduct) {
    if (!Array.isArray(byProduct)) {
        return [];
    }
    return byProduct.map((row) => ({
        tool_name: row.tool_name ?? row.product ?? 'Khác',
        account_count: row.account_count ?? 0,
        cost_monthly: row.cost_monthly ?? 0,
        avg_cost_monthly: row.avg_cost_monthly
            ?? (row.account_count > 0
                ? Math.round((row.cost_monthly ?? 0) / row.account_count)
                : row.cost_monthly ?? 0),
    }));
}

function downloadWorkbook(wb, filename) {
    const buf = XLSX.write(wb, { bookType: 'xlsx', type: 'array' });
    const blob = new Blob(
        [buf],
        { type: 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet' },
    );
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.rel = 'noopener';
    document.body.appendChild(a);
    a.click();
    a.remove();
    setTimeout(() => URL.revokeObjectURL(a.href), 0);
    return filename;
}

function buildSummarySheet({ dashboard, filterNote, exporterName }) {
    const ws = {};
    const lastCol = 3;
    const exportedAt = datetime(new Date().toISOString());
    const kpis = dashboard?.kpis ?? {};

    setCell(ws, 0, 0, 'BÁO CÁO QUẢN TRỊ AI & CHI PHÍ — VAschools', S.title);
    mergeRow(ws, 0, 0, lastCol);
    setCell(ws, 1, 0, `Thời gian báo cáo: ${exportedAt}${filterNote ? ` · ${filterNote}` : ''}`, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);
    setCell(ws, 2, 0, `Người xuất: ${exporterName ?? '—'}`, S.subtitle);
    mergeRow(ws, 2, 0, lastCol);

    setCell(ws, 4, 0, 'KPI tổng quan', S.section);
    mergeRow(ws, 4, 0, lastCol);

    const rows = [
        ['Tài khoản đang sử dụng', kpis.accounts_in_use ?? 0],
        ['Sắp hết hạn (30 ngày)', kpis.accounts_expiring_soon ?? 0],
        ['Đã hết hạn', kpis.accounts_expired ?? 0],
        ['Chi phí tháng hiện tại', kpis.cost_current_month ?? 0],
        ['Chi phí năm hiện tại', kpis.cost_current_year ?? 0],
        ['Chi phí TB / người dùng', kpis.avg_cost_per_user ?? 0],
        ['Chi phí TB / phòng ban', kpis.avg_cost_per_department ?? 0],
        ['Tỷ lệ sử dụng (%)', kpis.usage_rate_percent ?? 0],
        ['Ngân sách đã duyệt', kpis.budget_approved_total ?? 0],
        ['Ngân sách đã thanh toán', kpis.budget_paid_total ?? 0],
        ['Ngân sách đã sử dụng', kpis.budget_used_total ?? 0],
    ];

    rows.forEach(([label, val], i) => {
        const r = 5 + i;
        setCell(ws, r, 0, label, S.cell);
        const money = i >= 3 && i <= 10 && i !== 7;
        setCell(ws, r, 1, val, money ? S.moneyBold : S.cell);
        if (money) setCell(ws, r, 2, 'VNĐ', S.cell);
    });

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 5 + rows.length, c: lastCol } });
    setColWidths(ws, [34, 16, 10, 8]);
    return ws;
}

function buildDetailSheet(rows, visibleKeys) {
    const keys = resolveExportColumns(visibleKeys);
    const cols = ANALYTICS_REPORT_COLUMNS.filter((c) => keys.includes(c.key));
    const headers = cols.map((c) => c.label);
    const ws = {};
    const lastCol = Math.max(0, headers.length - 1);
    headers.forEach((h, i) => setCell(ws, 3, i, h, S.header));

    rows.forEach((row, idx) => {
        const r = 4 + idx;
        const alt = idx % 2 === 0 ? S.cell : S.cellAlt;
        cols.forEach((col, ci) => {
            let val = row[col.key];
            if (col.key === 'cost_monthly' || col.key === 'cost_yearly' || col.key === 'actual_cost') {
                setCell(ws, r, ci, val ?? 0, S.money);
            } else {
                setCell(ws, r, ci, val ?? '—', alt);
            }
        });
    });

    setCell(ws, 0, 0, 'CHI TIẾT TÀI KHOẢN AI', S.title);
    mergeRow(ws, 0, 0, lastCol);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 4 + rows.length, c: lastCol } });
    if (rows.length > 0 && lastCol >= 0) {
        ws['!autofilter'] = {
            ref: XLSX.utils.encode_range({ s: { r: 3, c: 0 }, e: { r: 3 + rows.length, c: lastCol } }),
        };
    }
    if (lastCol >= 0) {
        ws['!views'] = [{
            state: 'frozen',
            xSplit: 1,
            ySplit: 4,
            topLeftCell: 'B5',
            activeCell: 'A1',
        }];
    }
    setColWidths(ws, cols.map(() => 16));
    return ws;
}

function buildProductSheet(byProduct) {
    const ws = {};
    const headers = ['Tên AI', 'Số lượng TK', 'Chi phí / tháng', 'Chi phí TB / TK', 'Tỷ lệ (%)'];
    headers.forEach((h, i) => setCell(ws, 3, i, h, S.header));
    const total = (byProduct ?? []).reduce((s, r) => s + (r.cost_monthly ?? 0), 0);
    (byProduct ?? []).forEach((row, idx) => {
        const r = 4 + idx;
        const share = total > 0 ? Math.round(((row.cost_monthly ?? 0) / total) * 100) : 0;
        setCell(ws, r, 0, row.tool_name, idx % 2 ? S.cellAlt : S.cell);
        setCell(ws, r, 1, row.account_count ?? 0, S.cell);
        setCell(ws, r, 2, row.cost_monthly ?? 0, S.money);
        setCell(ws, r, 3, row.avg_cost_monthly ?? 0, S.money);
        setCell(ws, r, 4, share, S.cell);
    });
    setCell(ws, 0, 0, 'CHI PHÍ THEO SẢN PHẨM AI', S.title);
    mergeRow(ws, 0, 0, 4);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 4 + (byProduct?.length ?? 0), c: 4 } });
    setColWidths(ws, [22, 12, 16, 16, 10]);
    return ws;
}

function expiryStyle(days) {
    if (days <= 7) return S.danger;
    if (days <= 30) return S.warn;
    return S.cell;
}

function buildExpiringSheet(rows) {
    const ws = {};
    const headers = ['Sản phẩm', 'Người dùng', 'Hết hạn', 'Còn (ngày)', 'Trạng thái'];
    headers.forEach((h, i) => setCell(ws, 3, i, h, S.header));

    const sorted = [...rows].sort((a, b) => (a.expiry_date ?? '').localeCompare(b.expiry_date ?? ''));
    sorted.forEach((row, idx) => {
        const r = 4 + idx;
        const days = row.days_until_expiry ?? 999;
        const style = expiryStyle(days);
        setCell(ws, r, 0, row.tool_name, style);
        setCell(ws, r, 1, row.allocated_to_name ?? '—', style);
        setCell(ws, r, 2, row.expiry_date, style);
        setCell(ws, r, 3, days, style);
        setCell(ws, r, 4, row.status_label ?? row.status, style);
    });

    setCell(ws, 0, 0, 'TÀI KHOẢN SẮP HẾT HẠN', S.title);
    mergeRow(ws, 0, 0, 4);
    setCell(ws, 1, 0, 'Tô màu: ≤7 ngày (đỏ), ≤30 ngày (vàng), ≤60 ngày (trắng)', S.subtitle);
    mergeRow(ws, 1, 0, 4);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 4 + sorted.length, c: 4 } });
    setColWidths(ws, [20, 22, 12, 10, 14]);
    return ws;
}

/**
 * @param {{
 *   rows: object[],
 *   stats?: object,
 *   dashboard?: object,
 *   visibleColumnKeys?: string[],
 *   filterNote?: string,
 *   exporterName?: string,
 * }} opts
 */
export function exportAiAnalyticsWorkbook(opts) {
    const {
        rows = [],
        stats = null,
        dashboard = null,
        visibleColumnKeys = ANALYTICS_REPORT_COLUMNS.filter((c) => c.default).map((c) => c.key),
        filterNote = '',
        exporterName = '',
    } = opts;

    if (!rows?.length) {
        return false;
    }

    const iso = fileStamp();
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildSummarySheet({ dashboard, filterNote, exporterName }), 'Tong quan');
    XLSX.utils.book_append_sheet(wb, buildDetailSheet(rows, visibleColumnKeys), 'Chi tiet TK');
    const productSource = dashboard?.by_product ?? stats?.by_product;
    XLSX.utils.book_append_sheet(wb, buildProductSheet(normalizeProductRows(productSource)), 'Chi phi SP');
    const expiring = (dashboard?.top?.expiring_soon ?? rows
        .filter((r) => r.expiry_date)
        .map((r) => {
            const expiry = new Date(r.expiry_date);
            const days = Number.isNaN(expiry.getTime())
                ? 999
                : Math.ceil((expiry - new Date()) / 86400000);
            return {
                tool_name: r.tool_name,
                allocated_to_name: r.user_name ?? r.allocated_to_name,
                expiry_date: r.expiry_date,
                days_until_expiry: days,
                status_label: r.status_label,
            };
        }))
        .filter((r) => r.days_until_expiry <= 60);
    XLSX.utils.book_append_sheet(wb, buildExpiringSheet(expiring), 'Sap het han');

    const filename = `VA_AI_Analytics_${iso}.xlsx`;
    return downloadWorkbook(wb, filename);
}
