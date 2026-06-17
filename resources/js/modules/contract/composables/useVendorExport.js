import XLSX from 'xlsx-js-style';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

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

const HEADERS = [
    'Mã NCC',
    'Tên NCC',
    'Mã số thuế',
    'Người liên hệ',
    'Email',
    'Điện thoại',
    'Số hợp đồng',
    'Chi phí / năm',
    'Điểm đánh giá',
    'Trạng thái',
];

function vendorRow(v) {
    return [
        v.code ?? '',
        v.name ?? '',
        displayOrEmpty(v.tax_code, EMPTY_LABELS.notUpdated),
        displayOrEmpty(v.contact_name, EMPTY_LABELS.notUpdated),
        displayOrEmpty(v.email, EMPTY_LABELS.notUpdated),
        displayOrEmpty(v.phone, EMPTY_LABELS.notUpdated),
        v.contracts_count ?? 0,
        formatMoneyShort(v.total_annual_cost ?? 0),
        v.review_score != null ? `${v.review_score}/10` : 'Chưa đánh giá',
        v.is_active ? 'Đang hoạt động' : 'Ngừng hoạt động',
    ];
}

function downloadBlob(blob, name) {
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    a.click();
    URL.revokeObjectURL(url);
}

/**
 * Xuất danh sách NCC đang hiển thị trên trang.
 *
 * @param {object[]} vendors
 * @param {'xlsx'|'csv'} format
 */
export function exportVendorPage(vendors, format = 'xlsx') {
    const rows = vendors.map(vendorRow);
    const stamp = new Date().toISOString().slice(0, 10);
    const filename = `VA_NhaCungCap_${stamp}`;

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
        return vendors.length;
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
    ws['!cols'] = [
        { wch: 12 }, { wch: 28 }, { wch: 14 }, { wch: 18 }, { wch: 24 },
        { wch: 14 }, { wch: 12 }, { wch: 16 }, { wch: 14 }, { wch: 16 },
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Nha cung cap');
    XLSX.writeFile(wb, `${filename}.xlsx`);
    return vendors.length;
}
