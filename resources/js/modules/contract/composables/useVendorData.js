import axios from 'axios';
import XLSX from 'xlsx-js-style';
import { formatMoneyShort } from '@/modules/contract/composables/useContractFormat.js';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay.js';

export const VENDOR_IMPORT_MARKER = 'VA_VENDOR_IMPORT_V1';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_700 = '334155';

const S = {
    title: { font: { bold: true, sz: 13, color: { rgb: BRAND } }, fill: { fgColor: { rgb: BRAND_SOFT } }, alignment: { horizontal: 'left' } },
    subtitle: { font: { bold: true, sz: 10, color: { rgb: SLATE_700 } }, fill: { fgColor: { rgb: SLATE_50 } } },
    header: { font: { bold: true, sz: 10, color: { rgb: 'FFFFFF' } }, fill: { fgColor: { rgb: BRAND } }, alignment: { horizontal: 'center', wrapText: true } },
    required: { font: { bold: true, sz: 10, color: { rgb: BRAND } }, fill: { fgColor: { rgb: BRAND_SOFT } } },
    sample: { font: { italic: true, sz: 9, color: { rgb: '64748B' } }, fill: { fgColor: { rgb: SLATE_50 } } },
    guide: { font: { sz: 9, color: { rgb: SLATE_700 } }, alignment: { wrapText: true } },
    note: { font: { italic: true, sz: 9, color: { rgb: '94A3B8' } } },
    cell: { font: { sz: 10 }, fill: { fgColor: { rgb: 'FFFFFF' } }, alignment: { wrapText: false } },
    cellAlt: { font: { sz: 10 }, fill: { fgColor: { rgb: SLATE_50 } }, alignment: { wrapText: false } },
};

const SAMPLE_ROW_NAMES = [
    'Công ty TNHH Dịch vụ Mẫu A',
    'Công ty CP Phần mềm Mẫu B',
];

const ACTIVE_LABELS = ['Đang hoạt động', 'Hoạt động', '1', 'true', 'active', 'Có'];
const INACTIVE_LABELS = ['Ngừng hoạt động', 'Ngừng', '0', 'false', 'inactive', 'Không'];

export const VENDOR_IMPORT_HEADERS = [
    'Tên NCC *',
    'Mã NCC',
    'Mã số thuế',
    'Người liên hệ',
    'Email',
    'Điện thoại',
    'Website',
    'Địa chỉ',
    'Đánh giá (1-5)',
    'Ghi chú',
    'Trạng thái',
];

const EXPORT_HEADERS = [
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

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((w) => ({ wch: w }));
}

function colLetter(i) {
    return String.fromCharCode(65 + i);
}

export function normalizeHeader(h) {
    return String(h || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function parseIsActive(raw) {
    const s = String(raw ?? '').trim();
    if (!s) return true;
    const n = normalizeHeader(s);
    if (INACTIVE_LABELS.some((l) => normalizeHeader(l) === n || n.includes('ngung'))) {
        return false;
    }
    if (ACTIVE_LABELS.some((l) => normalizeHeader(l) === n) || n.includes('dang hoat dong')) {
        return true;
    }
    return null;
}

function parseRating(raw) {
    const s = String(raw ?? '').trim();
    if (!s) return null;
    const n = parseInt(s, 10);
    if (Number.isNaN(n) || n < 1 || n > 5) return null;
    return n;
}

function mapRowFromHeaders(headers, row) {
    const obj = {
        is_active: true,
    };
    headers.forEach((h, idx) => {
        const key = normalizeHeader(String(h));
        const val = String(row[idx] ?? '').trim();
        if (!key) return;
        if (key.includes('ten ncc') || (key.includes('ten') && key.includes('ncc'))) {
            obj.name = val;
        } else if (key.includes('ma ncc') && !key.includes('thue')) {
            obj.code = val;
        } else if (key.includes('ma so thue') || key.includes('mst')) {
            obj.tax_code = val;
        } else if (key.includes('nguoi lien he') || key.includes('lien he')) {
            obj.contact_name = val;
        } else if (key.includes('email')) {
            obj.email = val;
        } else if (key.includes('dien thoai') || key.includes('phone')) {
            obj.phone = val;
        } else if (key.includes('website')) {
            obj.website = val;
        } else if (key.includes('dia chi')) {
            obj.address = val;
        } else if (key.includes('danh gia') || key.includes('rating')) {
            obj.rating = val;
        } else if (key.includes('ghi chu') || key.includes('notes')) {
            obj.notes = val;
        } else if (key.includes('trang thai')) {
            obj.is_active_raw = val;
        }
    });
    if (obj.is_active_raw !== undefined) {
        const parsed = parseIsActive(obj.is_active_raw);
        if (parsed !== null) obj.is_active = parsed;
        delete obj.is_active_raw;
    }
    if (obj.rating !== undefined && obj.rating !== null && obj.rating !== '') {
        const r = parseRating(obj.rating);
        obj.rating = r;
    } else {
        obj.rating = null;
    }
    return obj;
}

export function downloadVendorTemplate() {
    const wb = XLSX.utils.book_new();

    const guide = {};
    guide.A1 = { v: 'HƯỚNG DẪN NHẬP NHÀ CUNG CẤP — VA-QLDA', t: 's', s: S.title };
    guide.A2 = { v: `Phiên bản: ${VENDOR_IMPORT_MARKER} · Ngày: ${new Date().toLocaleDateString('vi-VN')}`, t: 's', s: S.note };
    guide.A3 = { v: '', t: 's' };

    const guideRows = [
        ['1. CẤU TRÚC FILE', 'File gồm sheet "Huong dan" (chỉ đọc) và "Nhap lieu" (điền dữ liệu).'],
        ['2. QUY TRÌNH NHẬP', '① Tải mẫu → ② Điền từ dòng 8 → ③ Upload → ④ Preview → ⑤ Sửa lỗi inline → ⑥ Nhập'],
        ['3. CỘT BẮT BUỘC (*)', 'Tên NCC — để trống sẽ bị lỗi.'],
        ['4. MÃ NCC', 'Để trống: hệ thống tự sinh (NCC-001, …). Nếu điền: tối đa 40 ký tự, không trùng mã có sẵn.'],
        ['5. TRẠNG THÁI', `${ACTIVE_LABELS.slice(0, 2).join(' | ')} hoặc ${INACTIVE_LABELS.slice(0, 2).join(' | ')} (mặc định: Đang hoạt động)`],
        ['6. ĐÁNH GIÁ', 'Số nguyên từ 1 đến 5 (sao nội bộ, tùy chọn).'],
        ['7. GHI ĐÈ', 'Bật «Ghi đè» khi nhập: khớp theo Mã NCC → Mã số thuế → Tên (không phân biệt hoa thường).'],
        ['8. GIỚI HẠN', 'Tối đa 200 dòng mỗi lần nhập.'],
        ['9. LỖI THƯỜNG GẶP', '• Email sai định dạng\n• Trạng thái không nhận diện\n• Mã NCC trùng\n• Dòng mẫu italic (6–7) không import'],
    ];

    guideRows.forEach(([sec, desc], i) => {
        const row = i + 4;
        guide[`A${row}`] = { v: sec, t: 's', s: S.subtitle };
        guide[`B${row}`] = { v: desc, t: 's', s: S.guide };
    });

    guide['!ref'] = `A1:B${guideRows.length + 3}`;
    guide['!cols'] = [{ wch: 22 }, { wch: 72 }];
    guide['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 1 } }];
    XLSX.utils.book_append_sheet(wb, guide, 'Huong dan');

    const nl = {};
    nl.A1 = { v: VENDOR_IMPORT_MARKER, t: 's', s: { font: { sz: 7, color: { rgb: SLATE_200 } } } };

    VENDOR_IMPORT_HEADERS.forEach((h, i) => {
        nl[`${colLetter(i)}5`] = { v: h, t: 's', s: i === 0 ? S.required : S.header };
    });

    const samples = [
        ['Công ty TNHH Dịch vụ Mẫu A', 'NCC-MAU-01', '0123456789', 'Nguyễn Văn A', 'contact@mau-a.vn', '0901234567', 'https://mau-a.vn', 'Hà Nội', '4', 'Dòng mẫu — không import', 'Đang hoạt động'],
        ['Công ty CP Phần mềm Mẫu B', '', '9876543210', 'Trần Thị B', 'sales@mau-b.vn', '0912345678', '', 'TP. HCM', '5', '', 'Đang hoạt động'],
    ];
    samples.forEach((row, ri) => {
        row.forEach((val, ci) => {
            nl[`${colLetter(ci)}${ri + 6}`] = { v: val, t: 's', s: S.sample };
        });
    });

    for (let r = 8; r <= 57; r += 1) {
        VENDOR_IMPORT_HEADERS.forEach((_, ci) => {
            nl[`${colLetter(ci)}${r}`] = { v: '', t: 's', s: r % 2 === 0 ? S.cell : S.cellAlt };
        });
    }

    nl['!ref'] = `A1:${colLetter(VENDOR_IMPORT_HEADERS.length - 1)}57`;
    setColWidths(nl, [28, 14, 14, 18, 24, 14, 22, 24, 12, 24, 16]);
    XLSX.utils.book_append_sheet(wb, nl, 'Nhap lieu');

    XLSX.writeFile(wb, `VA_NhaCungCap_Mau_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

export function parseVendorFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const wb = XLSX.read(e.target.result, { type: 'array' });
                const sheet = wb.Sheets['Nhap lieu'] || wb.Sheets.Nhap_lieu || wb.Sheets[wb.SheetNames[0]];
                const matrix = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

                let headerIdx = matrix.findIndex((row) =>
                    row.some((c) => normalizeHeader(String(c)).includes('ten ncc')),
                );
                if (headerIdx < 0) {
                    const markerIdx = matrix.findIndex((row) =>
                        row.some((c) => String(c).trim() === VENDOR_IMPORT_MARKER),
                    );
                    headerIdx = markerIdx >= 0 ? markerIdx + 4 : -1;
                }
                if (headerIdx < 0) {
                    return resolve({ rows: [], errors: ['Không tìm thấy header. Hãy dùng file mẫu chính thức.'] });
                }

                const headers = matrix[headerIdx] || [];
                const rows = [];
                const errors = [];

                for (let i = headerIdx + 1; i < matrix.length; i += 1) {
                    const row = matrix[i];
                    if (!row || !row.some((c) => String(c).trim())) continue;
                    const firstName = String(row[0] ?? '').trim();
                    if (SAMPLE_ROW_NAMES.includes(firstName)) continue;

                    const obj = mapRowFromHeaders(headers, row);
                    if (!obj.name) {
                        errors.push(`Dòng ${i + 1}: thiếu tên nhà cung cấp.`);
                        continue;
                    }
                    rows.push(obj);
                }

                if (rows.length > 200) {
                    errors.unshift('Tối đa 200 dòng mỗi lần nhập. Chỉ lấy 200 dòng đầu.');
                }
                resolve({ rows: rows.slice(0, 200), errors });
            } catch (err) {
                reject(err);
            }
        };
        reader.onerror = reject;
        reader.readAsArrayBuffer(file);
    });
}

export function createPreviewRows(rawRows) {
    const mapped = rawRows.map((r, idx) => ({
        ...r,
        _rowIdx: idx + 1,
        _errors: [],
        _valid: true,
    }));
    mapped.forEach((row) => revalidateVendorRow(row, mapped));
    return mapped;
}

export function revalidateVendorRow(row, allRows = null) {
    const errors = [];
    if (!row.name?.trim()) errors.push('Thiếu tên NCC');
    if (row.code?.trim() && row.code.trim().length > 40) errors.push('Mã NCC tối đa 40 ký tự');
    if (row.email?.trim()) {
        const emailRe = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRe.test(row.email.trim())) errors.push('Email không hợp lệ');
    }
    if (row.rating != null && row.rating !== '' && (row.rating < 1 || row.rating > 5)) {
        errors.push('Đánh giá phải từ 1 đến 5');
    }
    if (row.is_active_raw !== undefined) {
        const parsed = parseIsActive(row.is_active_raw);
        if (parsed === null) errors.push('Trạng thái không hợp lệ');
        else row.is_active = parsed;
    }

    const peers = allRows || [];
    const nameKey = row.name?.trim().toLowerCase();
    const taxKey = row.tax_code?.trim();
    const codeKey = row.code?.trim();
    if (nameKey && peers.filter((p) => p !== row && p.name?.trim().toLowerCase() === nameKey).length) {
        errors.push('Tên NCC trùng trong file');
    }
    if (taxKey && peers.filter((p) => p !== row && p.tax_code?.trim() === taxKey).length) {
        errors.push('Mã số thuế trùng trong file');
    }
    if (codeKey && peers.filter((p) => p !== row && p.code?.trim() === codeKey).length) {
        errors.push('Mã NCC trùng trong file');
    }

    row._errors = errors;
    row._valid = errors.length === 0;
    return row;
}

export function vendorRowToPayload(row) {
    const payload = {
        name: row.name?.trim(),
        code: row.code?.trim() || undefined,
        tax_code: row.tax_code?.trim() || undefined,
        contact_name: row.contact_name?.trim() || undefined,
        email: row.email?.trim() || undefined,
        phone: row.phone?.trim() || undefined,
        website: row.website?.trim() || undefined,
        address: row.address?.trim() || undefined,
        notes: row.notes?.trim() || undefined,
        is_active: row.is_active !== false,
    };
    if (row.rating != null && row.rating !== '') payload.rating = row.rating;
    return payload;
}

function vendorExportRow(v) {
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

/** Xuất nhanh trang hiện tại (CSV hoặc Excel đơn giản). */
export function exportVendorPage(vendors, format = 'xlsx') {
    const rows = vendors.map(vendorExportRow);
    const stamp = new Date().toISOString().slice(0, 10);
    const filename = `VA_NhaCungCap_${stamp}`;

    if (format === 'csv') {
        const escape = (v) => {
            const s = String(v ?? '');
            return s.includes(',') || s.includes('"') || s.includes('\n')
                ? `"${s.replace(/"/g, '""')}"`
                : s;
        };
        const lines = [EXPORT_HEADERS.map(escape).join(',')];
        rows.forEach((r) => lines.push(r.map(escape).join(',')));
        const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8;' });
        downloadBlob(blob, `${filename}.csv`);
        return vendors.length;
    }

    exportVendorWorkbook(vendors, filename);
    return vendors.length;
}

export function exportVendorWorkbook(vendors, filenamePrefix = 'VA_NhaCungCap') {
    const wb = XLSX.utils.book_new();
    const now = new Date();

    const tq = {};
    tq.A1 = { v: 'BÁO CÁO NHÀ CUNG CẤP', t: 's', s: S.title };
    tq.A2 = { v: `Ngày xuất: ${now.toLocaleDateString('vi-VN')} ${now.toLocaleTimeString('vi-VN')}`, t: 's', s: S.note };
    tq.A3 = { v: '', t: 's' };
    tq.A4 = { v: 'Tổng bản ghi', t: 's', s: S.subtitle };
    tq.B4 = { v: vendors.length, t: 'n', s: { font: { bold: true, sz: 11 } } };
    tq['!ref'] = 'A1:B4';
    tq['!cols'] = [{ wch: 24 }, { wch: 12 }];
    XLSX.utils.book_append_sheet(wb, tq, 'Tong quan');

    const ws = {};
    EXPORT_HEADERS.forEach((h, i) => {
        ws[`${colLetter(i)}1`] = { v: h, t: 's', s: S.header };
    });
    vendors.forEach((v, ri) => {
        const rowData = vendorExportRow(v);
        const style = ri % 2 === 1 ? S.cellAlt : S.cell;
        rowData.forEach((val, ci) => {
            ws[`${colLetter(ci)}${ri + 2}`] = {
                v: val ?? '',
                t: typeof val === 'number' ? 'n' : 's',
                s: style,
            };
        });
    });
    ws['!ref'] = `A1:${colLetter(EXPORT_HEADERS.length - 1)}${vendors.length + 1}`;
    setColWidths(ws, [12, 28, 14, 18, 24, 14, 12, 16, 14, 16]);
    XLSX.utils.book_append_sheet(wb, ws, 'Nha cung cap');

    XLSX.writeFile(wb, `${filenamePrefix}_${now.toISOString().slice(0, 10)}.xlsx`);
}

export function exportPreviewErrorRows(errorRows) {
    const wb = XLSX.utils.book_new();
    const ws = {};
    const extHeaders = [...VENDOR_IMPORT_HEADERS, 'Lỗi'];
    extHeaders.forEach((h, i) => {
        ws[`${colLetter(i)}1`] = {
            v: h,
            t: 's',
            s: h === 'Lỗi' ? { font: { bold: true, color: { rgb: BRAND } }, fill: { fgColor: { rgb: BRAND_SOFT } } } : S.header,
        };
    });

    errorRows.forEach((r, ri) => {
        const rowData = [
            r.name || '',
            r.code || '',
            r.tax_code || '',
            r.contact_name || '',
            r.email || '',
            r.phone || '',
            r.website || '',
            r.address || '',
            r.rating ?? '',
            r.notes || '',
            r.is_active === false ? 'Ngừng hoạt động' : 'Đang hoạt động',
            r._errors?.join('; ') || '',
        ];
        rowData.forEach((val, ci) => {
            ws[`${colLetter(ci)}${ri + 2}`] = {
                v: val,
                t: 's',
                s: ci === extHeaders.length - 1 ? S.guide : S.cellAlt,
            };
        });
    });

    ws['!ref'] = `A1:${colLetter(extHeaders.length - 1)}${errorRows.length + 1}`;
    setColWidths(ws, [28, 14, 14, 18, 24, 14, 22, 24, 12, 24, 16, 48]);
    XLSX.utils.book_append_sheet(wb, ws, 'Dong loi');
    XLSX.writeFile(wb, `VA_NhaCungCap_Loi_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

export async function fetchImportLogs() {
    const res = await axios.get(route('api.contracts.vendors.import-logs'));
    return res.data?.data ?? [];
}

export async function fetchAndExportAll(filters = {}) {
    const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined),
    );
    const res = await axios.get(route('api.contracts.vendors.export-data'), { params });
    const rows = res.data?.data ?? [];
    if (!rows.length) return 0;
    exportVendorWorkbook(rows, 'VA_NhaCungCap_ToanBo');
    return rows.length;
}

export function reconcileVendors(rows) {
    const issues = [];
    const taxSeen = new Map();

    rows.forEach((v) => {
        const vendorId = v.id ?? null;
        const name = v.name || 'Không tên';

        if (!v.contact_name && !v.email && !v.phone) {
            issues.push({
                level: 'warning',
                code: 'no_contact',
                message: `«${name}»: chưa có thông tin liên hệ.`,
                vendorId,
                vendorName: name,
            });
        }
        if (!v.tax_code?.trim()) {
            issues.push({
                level: 'info',
                code: 'no_tax_code',
                message: `«${name}»: chưa có mã số thuế.`,
                vendorId,
                vendorName: name,
            });
        }
        if (v.is_active === false && (v.contracts_count ?? 0) > 0) {
            issues.push({
                level: 'error',
                code: 'inactive_with_contracts',
                message: `«${name}»: đang ngừng hoạt động nhưng vẫn có hợp đồng.`,
                vendorId,
                vendorName: name,
            });
        }
        if (v.review_score == null) {
            issues.push({
                level: 'info',
                code: 'not_reviewed',
                message: `«${name}»: chưa có đánh giá NCC.`,
                vendorId,
                vendorName: name,
            });
        } else if (v.review_score < 7) {
            issues.push({
                level: 'warning',
                code: 'low_score',
                message: `«${name}»: điểm đánh giá ${v.review_score}/10 (dưới 7).`,
                vendorId,
                vendorName: name,
            });
        }

        const tax = v.tax_code?.trim();
        if (tax) {
            if (taxSeen.has(tax)) {
                issues.push({
                    level: 'error',
                    code: 'duplicate_tax_code',
                    message: `Mã số thuế «${tax}» trùng giữa «${taxSeen.get(tax)}» và «${name}».`,
                    vendorId,
                    vendorName: name,
                });
            } else {
                taxSeen.set(tax, name);
            }
        }
    });

    return {
        issues,
        summary: {
            total: rows.length,
            errors: issues.filter((i) => i.level === 'error').length,
            warnings: issues.filter((i) => i.level === 'warning').length,
            info: issues.filter((i) => i.level === 'info').length,
        },
    };
}

export function exportReconcileWorkbook(reconcile, filenamePrefix = 'VA_NhaCungCap_DoiSoat') {
    const wb = XLSX.utils.book_new();
    const ws = {};
    ['Mức', 'Mã', 'Nội dung'].forEach((h, i) => {
        ws[`${colLetter(i)}1`] = { v: h, t: 's', s: S.header };
    });
    reconcile.issues.forEach((issue, ri) => {
        const levelLabel = issue.level === 'error' ? 'Lỗi' : issue.level === 'warning' ? 'Cảnh báo' : 'Gợi ý';
        [levelLabel, issue.code, issue.message].forEach((val, ci) => {
            ws[`${colLetter(ci)}${ri + 2}`] = { v: val, t: 's', s: ri % 2 ? S.cellAlt : S.cell };
        });
    });
    ws['!ref'] = `A1:C${Math.max(1, reconcile.issues.length + 1)}`;
    setColWidths(ws, [12, 22, 64]);
    XLSX.utils.book_append_sheet(wb, ws, 'Doi soat');
    XLSX.writeFile(wb, `${filenamePrefix}_${new Date().toISOString().slice(0, 10)}.xlsx`);
}
