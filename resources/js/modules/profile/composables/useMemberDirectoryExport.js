import XLSX from 'xlsx-js-style';

const BRAND = '9A0036';
const WHITE = 'FFFFFF';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';

function borderThin() {
    return {
        top: { style: 'thin', color: { rgb: SLATE_200 } },
        bottom: { style: 'thin', color: { rgb: SLATE_200 } },
        left: { style: 'thin', color: { rgb: SLATE_200 } },
        right: { style: 'thin', color: { rgb: SLATE_200 } },
    };
}

const headerStyle = {
    font: { bold: true, sz: 10, color: { rgb: WHITE } },
    fill: { fgColor: { rgb: BRAND } },
    alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
    border: borderThin(),
};

const cellStyle = {
    font: { sz: 10, color: { rgb: '334155' } },
    alignment: { vertical: 'center', wrapText: true },
    border: borderThin(),
};

const cellAltStyle = {
    ...cellStyle,
    fill: { fgColor: { rgb: SLATE_50 } },
};

function memberRow(m) {
    return [
        m.code ?? '',
        m.name ?? '',
        m.role_title ?? '',
        m.email ?? '',
        m.seniority?.label ?? '',
        m.is_active ? 'Đang hoạt động' : 'Ngừng',
        m.projects_count ?? 0,
        (m.skills_preview ?? []).join(', '),
    ];
}

const HEADERS = ['Mã', 'Họ tên', 'Chức danh', 'Email', 'Cấp bậc', 'Trạng thái', 'Số dự án', 'Kỹ năng (xem trước)'];

/**
 * Xuất trang danh bạ hiện tại (dữ liệu đã tải).
 *
 * @param {object[]} members
 * @param {'xlsx'|'csv'} format
 */
export function exportMemberDirectoryPage(members, format = 'xlsx') {
    const rows = members.map(memberRow);
    const stamp = new Date().toISOString().slice(0, 10);
    const filename = `VA_DanhBaThanhVien_${stamp}`;

    if (format === 'csv') {
        const escape = (v) => {
            const s = String(v ?? '');
            return s.includes(',') || s.includes('"') || s.includes('\n')
                ? `"${s.replace(/"/g, '""')}"`
                : s;
        };
        const lines = [HEADERS.map(escape).join(',')];
        rows.forEach((r) => lines.push(r.map(escape).join(',')));
        const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        downloadBlob(blob, `${filename}.csv`);
        return members.length;
    }

    const ws = {};
    HEADERS.forEach((h, c) => {
        const ref = XLSX.utils.encode_cell({ r: 0, c });
        ws[ref] = { v: h, t: 's', s: headerStyle };
    });
    rows.forEach((row, ri) => {
        const style = ri % 2 === 1 ? cellAltStyle : cellStyle;
        row.forEach((val, ci) => {
            const ref = XLSX.utils.encode_cell({ r: ri + 1, c: ci });
            ws[ref] = {
                v: val ?? '',
                t: typeof val === 'number' ? 'n' : 's',
                s: style,
            };
        });
    });
    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: rows.length, c: HEADERS.length - 1 },
    });
    ws['!cols'] = [{ wch: 12 }, { wch: 28 }, { wch: 22 }, { wch: 26 }, { wch: 14 }, { wch: 16 }, { wch: 10 }, { wch: 36 }];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Danh ba');
    XLSX.writeFile(wb, `${filename}.xlsx`);
    return members.length;
}

function downloadBlob(blob, name) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    a.click();
    URL.revokeObjectURL(url);
}
