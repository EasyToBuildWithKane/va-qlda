import XLSX from 'xlsx-js-style';

const BRAND = '9A0036';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
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
    ws[ref] = { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

const headerStyle = {
    font: { bold: true, sz: 10, color: { rgb: WHITE } },
    fill: { fgColor: { rgb: BRAND } },
    border: borderThin(),
};

const cellStyle = (alt) => ({
    font: { sz: 10 },
    fill: { fgColor: { rgb: alt ? SLATE_50 : WHITE } },
    border: borderThin(),
});

/**
 * @param {{ month: string, monthly: object, revenueSeries: array }} payload
 */
export function exportCoachingMonthlyWorkbook(payload) {
    const { month, monthly, revenueSeries = [] } = payload;
    const wb = XLSX.utils.book_new();
    const ws = {};

    setCell(ws, 0, 0, 'Báo cáo Coaching / Mentoring', {
        font: { bold: true, sz: 16, color: { rgb: BRAND } },
    });
    setCell(ws, 1, 0, `Tháng ${month}`, { font: { sz: 10, italic: true } });

    const rows = [
        ['Chỉ số', 'Giá trị'],
        ['Tổng buổi học', monthly.sessions_total],
        ['Buổi hoàn thành', monthly.sessions_completed],
        ['Buổi hủy', monthly.sessions_cancelled],
        ['Tổng giờ dạy', monthly.hours_total],
        ['Doanh thu (VNĐ)', monthly.revenue_total],
        ['TB / giờ (VNĐ)', monthly.avg_per_hour ?? '—'],
        ['TB / buổi (VNĐ)', monthly.avg_per_session ?? '—'],
        ['Học viên (distinct)', monthly.students_distinct],
    ];

    rows.forEach((row, i) => {
        const r = 3 + i;
        const alt = i % 2 === 1;
        setCell(ws, r, 0, row[0], i === 0 ? headerStyle : cellStyle(alt));
        setCell(ws, r, 1, row[1], i === 0 ? headerStyle : cellStyle(alt));
    });

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 12, c: 1 } });
    ws['!cols'] = [{ wch: 28 }, { wch: 18 }];
    XLSX.utils.book_append_sheet(wb, ws, 'Tong quan');

    const ws2 = {};
    setCell(ws2, 0, 0, 'Tháng', headerStyle);
    setCell(ws2, 0, 1, 'Doanh thu', headerStyle);
    setCell(ws2, 0, 2, 'Giờ dạy', headerStyle);
    revenueSeries.forEach((row, i) => {
        const r = i + 1;
        setCell(ws2, r, 0, row.month, cellStyle(r % 2 === 0));
        setCell(ws2, r, 1, row.revenue, cellStyle(r % 2 === 0));
        setCell(ws2, r, 2, row.hours, cellStyle(r % 2 === 0));
    });
    ws2['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: Math.max(1, revenueSeries.length), c: 2 },
    });
    XLSX.utils.book_append_sheet(wb, ws2, 'Theo thang');

    const safeMonth = month.replace(/[^\d-]/g, '');
    XLSX.writeFile(wb, `VA_Coaching_${safeMonth}.xlsx`);
}
