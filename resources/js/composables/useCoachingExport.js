import XLSX from 'xlsx-js-style';
import { datetime } from '@/composables/useFormat';

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

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    const isNum = typeof value === 'number' && Number.isFinite(value);
    ws[ref] = { v: value ?? '', t: isNum ? 'n' : 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

const S = {
    title: {
        font: { bold: true, sz: 16, color: { rgb: BRAND } },
        alignment: { vertical: 'center' },
    },
    subtitle: {
        font: { sz: 10, color: { rgb: SLATE_600 }, italic: true },
    },
    section: {
        font: { bold: true, sz: 11, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        border: borderThin(),
    },
    header: {
        font: { bold: true, sz: 10, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
    cell: (alt) => ({
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: alt ? SLATE_50 : WHITE } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    }),
    money: (alt, bold = false) => ({
        font: { sz: 10, color: { rgb: bold ? BRAND : '334155' }, bold },
        fill: { fgColor: { rgb: alt ? SLATE_50 : WHITE } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
        numFmt: '#,##0',
    }),
    hours: (alt) => ({
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: alt ? SLATE_50 : WHITE } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
        numFmt: '#,##0.0',
    }),
};

function buildOverviewSheet({ month, monthly, summary }) {
    const ws = {};
    let row = 0;

    setCell(ws, row, 0, 'Báo cáo Coaching / Mentoring', S.title);
    mergeRow(ws, row, 0, 3);
    row += 1;
    setCell(ws, row, 0, `Kỳ báo cáo: tháng ${month} · Xuất lúc ${datetime(new Date().toISOString())}`, S.subtitle);
    mergeRow(ws, row, 0, 3);
    row += 2;

    if (summary) {
        setCell(ws, row, 0, 'Tổng quan hệ thống', S.section);
        mergeRow(ws, row, 0, 3);
        row += 1;
        const kpi = [
            ['Tổng khóa học', summary.courses_total],
            ['Khóa đang diễn ra', summary.courses_active],
            ['Tổng buổi học', summary.sessions_total],
            ['Tổng giờ đào tạo (hoàn thành)', summary.hours_total],
        ];
        kpi.forEach(([label, val], i) => {
            const alt = i % 2 === 1;
            setCell(ws, row, 0, label, S.cell(alt));
            setCell(ws, row, 1, val, typeof val === 'number' && label.includes('giờ') ? S.hours(alt) : S.cell(alt));
            mergeRow(ws, row, 1, 3);
            row += 1;
        });
        row += 1;
    }

    setCell(ws, row, 0, `Chỉ số tháng ${month}`, S.section);
    mergeRow(ws, row, 0, 3);
    row += 1;

    setCell(ws, row, 0, 'Chỉ số', S.header);
    setCell(ws, row, 1, 'Giá trị', S.header);
    mergeRow(ws, row, 1, 3);
    row += 1;

    const rows = [
        ['Tổng buổi học', monthly.sessions_total, 'int'],
        ['Buổi hoàn thành', monthly.sessions_completed, 'int'],
        ['Buổi hủy', monthly.sessions_cancelled, 'int'],
        ['Tổng giờ dạy', monthly.hours_total, 'hours'],
        ['Doanh thu (VNĐ)', monthly.revenue_total, 'money'],
        ['TB / giờ (VNĐ)', monthly.avg_per_hour, 'money'],
        ['TB / buổi (VNĐ)', monthly.avg_per_session, 'money'],
        ['Học viên (distinct)', monthly.students_distinct, 'int'],
    ];

    rows.forEach(([label, val, kind], i) => {
        const alt = i % 2 === 1;
        setCell(ws, row, 0, label, S.cell(alt));
        let style = S.cell(alt);
        let cellVal = val ?? '—';
        if (val != null && val !== '—') {
            if (kind === 'money') {
                style = S.money(alt, label.includes('Doanh thu'));
                cellVal = val;
            } else if (kind === 'hours') {
                style = S.hours(alt);
                cellVal = val;
            }
        }
        setCell(ws, row, 1, cellVal, style);
        mergeRow(ws, row, 1, 3);
        row += 1;
    });

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: row, c: 3 } });
    ws['!cols'] = [{ wch: 32 }, { wch: 18 }, { wch: 12 }, { wch: 12 }];
    return ws;
}

function buildSeriesSheet(revenueSeries) {
    const ws = {};
    setCell(ws, 0, 0, 'Tháng', S.header);
    setCell(ws, 0, 1, 'Doanh thu (VNĐ)', S.header);
    setCell(ws, 0, 2, 'Giờ dạy', S.header);

    revenueSeries.forEach((row, i) => {
        const r = i + 1;
        const alt = r % 2 === 0;
        setCell(ws, r, 0, row.month, S.cell(alt));
        setCell(ws, r, 1, row.revenue ?? 0, S.money(alt));
        setCell(ws, r, 2, row.hours ?? 0, S.hours(alt));
    });

    const last = Math.max(1, revenueSeries.length);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: last, c: 2 } });
    ws['!cols'] = [{ wch: 14 }, { wch: 18 }, { wch: 12 }];
    return ws;
}

/**
 * @param {{ month: string, monthly: object, revenueSeries: array, summary?: object }} payload
 */
export function exportCoachingMonthlyWorkbook(payload) {
    const { month, monthly, revenueSeries = [], summary = null } = payload;
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildOverviewSheet({ month, monthly, summary }), 'Tong quan');
    XLSX.utils.book_append_sheet(wb, buildSeriesSheet(revenueSeries), '12 thang');

    const safeMonth = month.replace(/[^\d-]/g, '');
    XLSX.writeFile(wb, `VA_Coaching_${safeMonth}.xlsx`);
}
