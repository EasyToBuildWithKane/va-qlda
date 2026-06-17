import axios from 'axios';
import XLSX from 'xlsx-js-style';

export const CREDENTIAL_IMPORT_MARKER = 'VA_CREDENTIAL_IMPORT_V1';

// ─── Brand palette (đồng bộ với RiskImportModal / ContractImport) ────────────
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

// ─── Enums (mirror backend PHP enums) ────────────────────────────────────────
const CREDENTIAL_TYPES = ['internal_system', 'cloud_service', 'database', 'vps', 'domain', 'email', 'other'];
const SYSTEM_CATEGORIES = ['cms', 'erp', 'crm', 'lms', 'hrm', 'devops', 'storage', 'analytics', 'communication', 'other'];
const ENVIRONMENTS = ['production', 'staging', 'development', 'testing'];
const STATUSES = ['active', 'inactive', 'locked', 'expired'];

export const CREDENTIAL_HEADERS = [
    'Tên tài khoản *',
    'Loại *',
    'Hệ thống *',
    'Username',
    'URL đăng nhập',
    'Nhà cung cấp',
    'Môi trường',
    'Trạng thái',
];

// ─── Helpers ─────────────────────────────────────────────────────────────────
function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((w) => ({ wch: w }));
}

function normalizeHeader(h) {
    return String(h || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

// ─── Template download (styled, 9-section guide) ─────────────────────────────
export function downloadCredentialTemplate() {
    const wb = XLSX.utils.book_new();

    // Sheet 1: Huong dan
    const guide = {};
    guide['A1'] = { v: 'HƯỚNG DẪN NHẬP TÀI KHOẢN — VA-QLDA', t: 's', s: S.title };
    guide['A2'] = { v: `Phiên bản: ${CREDENTIAL_IMPORT_MARKER} · Ngày: ${new Date().toLocaleDateString('vi-VN')}`, t: 's', s: S.note };
    guide['A3'] = { v: '', t: 's' };

    const guideRows = [
        ['1. CẤU TRÚC FILE', 'File gồm 2 sheet: "Huong dan" (chỉ đọc) và "Nhap lieu" (điền dữ liệu).'],
        ['2. QUY TRÌNH NHẬP', '① Tải mẫu → ② Điền từ dòng 8 (sheet Nhap lieu) → ③ Upload → ④ Kiểm tra preview → ⑤ Sửa lỗi inline → ⑥ Nhập'],
        ['3. CỘT BẮT BUỘC (*)', 'Tên tài khoản, Loại, Hệ thống — để trống sẽ bị lỗi.'],
        ['4. ENUM LOẠI', CREDENTIAL_TYPES.join(' | ')],
        ['5. ENUM HỆ THỐNG', SYSTEM_CATEGORIES.join(' | ')],
        ['6. ENUM MÔI TRƯỜNG', ENVIRONMENTS.join(' | ') + ' (mặc định: production)'],
        ['7. ENUM TRẠNG THÁI', STATUSES.join(' | ') + ' (mặc định: active)'],
        ['8. GIỚI HẠN', 'Tối đa 200 dòng mỗi lần nhập. Dòng trùng Tên + Hệ thống sẽ tạo mới thêm (không ghi đè).'],
        ['9. LỖI THƯỜNG GẶP', '• Sai enum → chép đúng giá trị từ mục 4-7\n• Ô trống bắt buộc → kiểm tra cột có dấu *\n• Dòng mẫu italic không bị import (dòng 6-7)'],
    ];

    guideRows.forEach(([sec, desc], i) => {
        const row = i + 4;
        guide[`A${row}`] = { v: sec, t: 's', s: S.subtitle };
        guide[`B${row}`] = { v: desc, t: 's', s: S.guide };
    });

    guide['!ref'] = `A1:B${guideRows.length + 3}`;
    guide['!cols'] = [{ wch: 22 }, { wch: 70 }];
    guide['!merges'] = [{ s: { r: 0, c: 0 }, e: { r: 0, c: 1 } }];
    XLSX.utils.book_append_sheet(wb, guide, 'Huong dan');

    // Sheet 2: Nhap lieu
    const nl = {};
    // Marker ẩn (dòng 1)
    nl['A1'] = { v: CREDENTIAL_IMPORT_MARKER, t: 's', s: { font: { sz: 7, color: { rgb: SLATE_200 } } } };
    nl['A2'] = { v: '', t: 's' };
    nl['A3'] = { v: '', t: 's' };
    nl['A4'] = { v: '', t: 's' };

    // Header dòng 5
    CREDENTIAL_HEADERS.forEach((h, i) => {
        const addr = `${String.fromCharCode(65 + i)}5`;
        nl[addr] = { v: h, t: 's', s: i < 3 ? S.required : S.header };
    });

    // 2 sample italic dòng 6-7
    const samples = [
        ['CMS Production', 'internal_system', 'cms', 'admin', 'https://cms.example.vn', 'Internal', 'production', 'active'],
        ['Database Staging', 'database', 'other', 'db_user', 'postgresql://db.example.vn', 'AWS', 'staging', 'active'],
    ];
    samples.forEach((row, ri) => {
        row.forEach((val, ci) => {
            nl[`${String.fromCharCode(65 + ci)}${ri + 6}`] = { v: val, t: 's', s: S.sample };
        });
    });

    // 50 ô trống từ dòng 8
    for (let r = 8; r <= 57; r += 1) {
        CREDENTIAL_HEADERS.forEach((_, ci) => {
            nl[`${String.fromCharCode(65 + ci)}${r}`] = { v: '', t: 's', s: r % 2 === 0 ? S.cell : S.cellAlt };
        });
    }

    nl['!ref'] = `A1:${String.fromCharCode(65 + CREDENTIAL_HEADERS.length - 1)}57`;
    setColWidths(nl, [30, 18, 16, 18, 35, 18, 14, 12]);
    XLSX.utils.book_append_sheet(wb, nl, 'Nhap lieu');

    XLSX.writeFile(wb, `VA_Credential_Mau_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

// ─── Parse ────────────────────────────────────────────────────────────────────
export function parseCredentialFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const wb = XLSX.read(e.target.result, { type: 'array' });
                const sheet = wb.Sheets['Nhap lieu'] || wb.Sheets.Nhap_lieu || wb.Sheets[wb.SheetNames[0]];
                const matrix = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });

                let headerIdx = matrix.findIndex((row) =>
                    row.some((c) => normalizeHeader(String(c)).includes('ten tai khoan')),
                );
                if (headerIdx < 0) {
                    const markerIdx = matrix.findIndex((row) =>
                        row.some((c) => String(c).trim() === CREDENTIAL_IMPORT_MARKER),
                    );
                    headerIdx = markerIdx >= 0 ? markerIdx + 1 : -1;
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
                    // Bỏ dòng mẫu italic (sample rows — thường có giá trị sample cố định)
                    const firstCell = String(row[0] || '').trim();
                    if (firstCell === 'CMS Production' || firstCell === 'Database Staging') continue;

                    const obj = {};
                    headers.forEach((h, idx) => {
                        const key = normalizeHeader(String(h));
                        const val = String(row[idx] ?? '').trim();
                        if (key.includes('ten')) obj.name = val;
                        else if (key.includes('loai')) obj.credential_type = val;
                        else if (key.includes('he thong')) obj.system_category = val;
                        else if (key.includes('username')) obj.username = val;
                        else if (key.includes('url')) obj.login_url = val;
                        else if (key.includes('nha cung cap')) obj.provider_name = val;
                        else if (key.includes('moi truong')) obj.environment = val || 'production';
                        else if (key.includes('trang thai')) obj.status = val || 'active';
                    });

                    if (!obj.name) {
                        errors.push(`Dòng ${i + 1}: thiếu tên tài khoản.`);
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

// ─── Preview rows (thêm _rowIdx, _errors, _valid cho inline edit) ─────────────
export function createPreviewRows(rawRows) {
    return rawRows.map((r, idx) => {
        const row = { ...r, _rowIdx: idx + 1, _errors: [], _valid: true };
        revalidateCredentialRow(row);
        return row;
    });
}

export function revalidateCredentialRow(row) {
    const errors = [];
    if (!row.name?.trim()) errors.push('Thiếu tên tài khoản');
    if (!row.credential_type?.trim()) {
        errors.push('Thiếu Loại');
    } else if (!CREDENTIAL_TYPES.includes(row.credential_type.trim())) {
        errors.push(`Loại không hợp lệ: "${row.credential_type}" — chọn: ${CREDENTIAL_TYPES.join(', ')}`);
    }
    if (!row.system_category?.trim()) {
        errors.push('Thiếu Hệ thống');
    } else if (!SYSTEM_CATEGORIES.includes(row.system_category.trim())) {
        errors.push(`Hệ thống không hợp lệ: "${row.system_category}"`);
    }
    if (row.environment && !ENVIRONMENTS.includes(row.environment.trim())) {
        errors.push(`Môi trường không hợp lệ: "${row.environment}"`);
    }
    if (row.status && !STATUSES.includes(row.status.trim())) {
        errors.push(`Trạng thái không hợp lệ: "${row.status}"`);
    }
    row._errors = errors;
    row._valid = errors.length === 0;
    return row;
}

export function credentialRowToPayload(row) {
    return Object.fromEntries(
        Object.entries(row).filter(([k]) => !k.startsWith('_')),
    );
}

// ─── Styled export workbook ───────────────────────────────────────────────────
export function exportCredentialWorkbook(rows, filenamePrefix = 'VA_Credential') {
    const wb = XLSX.utils.book_new();
    const now = new Date();

    // Sheet 1: Tong quan
    const tq = {};
    tq['A1'] = { v: 'BÁO CÁO TÀI KHOẢN & MẬT KHẨU', t: 's', s: S.title };
    tq['A2'] = { v: `Ngày xuất: ${now.toLocaleDateString('vi-VN')} ${now.toLocaleTimeString('vi-VN')}`, t: 's', s: S.note };
    tq['A3'] = { v: '', t: 's' };
    tq['A4'] = { v: 'Tổng bản ghi', t: 's', s: S.subtitle };
    tq['B4'] = { v: rows.length, t: 'n', s: { font: { bold: true, sz: 11 } } };
    tq['A5'] = { v: 'Đang hoạt động', t: 's', s: S.guide };
    tq['B5'] = { v: rows.filter((r) => (r.status?.value || r.status) === 'active').length, t: 'n' };
    tq['A6'] = { v: 'Không hoạt động', t: 's', s: S.guide };
    tq['B6'] = { v: rows.filter((r) => (r.status?.value || r.status) !== 'active').length, t: 'n' };
    tq['!ref'] = 'A1:B6';
    tq['!cols'] = [{ wch: 24 }, { wch: 12 }];
    XLSX.utils.book_append_sheet(wb, tq, 'Tong quan');

    // Sheet 2: Tai khoan (styled)
    const tk = {};
    CREDENTIAL_HEADERS.forEach((h, i) => {
        tk[`${String.fromCharCode(65 + i)}1`] = { v: h, t: 's', s: S.header };
    });

    rows.forEach((r, ri) => {
        const rowData = [
            r.name,
            r.credential_type?.value || r.credential_type || '',
            r.system_category?.value || r.system_category || '',
            r.username || '',
            r.login_url || '',
            r.provider_name || '',
            r.environment?.value || r.environment || '',
            r.status?.value || r.status || '',
        ];
        const rowStyle = ri % 2 === 0 ? S.cell : S.cellAlt;
        rowData.forEach((val, ci) => {
            tk[`${String.fromCharCode(65 + ci)}${ri + 2}`] = { v: val ?? '', t: 's', s: rowStyle };
        });
    });

    tk['!ref'] = `A1:${String.fromCharCode(65 + CREDENTIAL_HEADERS.length - 1)}${rows.length + 1}`;
    setColWidths(tk, [30, 18, 16, 18, 35, 18, 14, 12]);
    XLSX.utils.book_append_sheet(wb, tk, 'Tai khoan');

    XLSX.writeFile(wb, `${filenamePrefix}_${now.toISOString().slice(0, 10)}.xlsx`);
}

// ─── Export preview error rows ────────────────────────────────────────────────
export function exportPreviewErrorRows(errorRows) {
    const wb = XLSX.utils.book_new();
    const ws = {};

    const extHeaders = [...CREDENTIAL_HEADERS, 'Lỗi'];
    extHeaders.forEach((h, i) => {
        ws[`${String.fromCharCode(65 + i)}1`] = {
            v: h,
            t: 's',
            s: h === 'Lỗi' ? { font: { bold: true, color: { rgb: BRAND } }, fill: { fgColor: { rgb: BRAND_SOFT } } } : S.header,
        };
    });

    errorRows.forEach((r, ri) => {
        const rowData = [
            r.name || '',
            r.credential_type || '',
            r.system_category || '',
            r.username || '',
            r.login_url || '',
            r.provider_name || '',
            r.environment || '',
            r.status || '',
            r._errors?.join('; ') || '',
        ];
        rowData.forEach((val, ci) => {
            ws[`${String.fromCharCode(65 + ci)}${ri + 2}`] = {
                v: val,
                t: 's',
                s: ci === 8
                    ? { font: { sz: 9, color: { rgb: BRAND } }, fill: { fgColor: { rgb: BRAND_SOFT } } }
                    : S.cellAlt,
            };
        });
    });

    ws['!ref'] = `A1:${String.fromCharCode(65 + extHeaders.length - 1)}${errorRows.length + 1}`;
    setColWidths(ws, [30, 18, 16, 18, 35, 18, 14, 12, 50]);
    XLSX.utils.book_append_sheet(wb, ws, 'Dong loi');

    XLSX.writeFile(wb, `VA_Credential_Loi_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

// ─── Import logs ─────────────────────────────────────────────────────────────
export async function fetchImportLogs() {
    const res = await axios.get(route('api.credentials.import-logs'));
    return res.data?.data ?? [];
}

// ─── Export all (fetch from server then build workbook) ───────────────────────
export async function fetchAndExportAll(filters = {}) {
    const params = Object.fromEntries(
        Object.entries(filters).filter(([, v]) => v !== '' && v !== null && v !== undefined),
    );
    const res = await axios.get(route('api.credentials.export-data'), { params });
    const rows = res.data?.data ?? [];
    if (!rows.length) return 0;
    exportCredentialWorkbook(rows, 'VA_Credential_ToanBo');
    return rows.length;
}

// ─── Reconcile ────────────────────────────────────────────────────────────────
export function reconcileCredentials(rows) {
    const issues = [];
    const now = new Date();

    rows.forEach((r) => {
        const credentialId = r.id ?? null;
        const credentialName = r.name || 'Không tên';

        if (!r.owner && !r.owner_id) {
            issues.push({
                level: 'warning',
                code: 'no_owner',
                message: `«${credentialName}»: chưa gán người phụ trách.`,
                credentialId,
                credentialName,
            });
        }
        if (!r.mfa_enabled) {
            issues.push({
                level: 'info',
                code: 'no_mfa',
                message: `«${credentialName}»: chưa bật MFA.`,
                credentialId,
                credentialName,
            });
        }
        if (r.expires_at) {
            const expiresAt = new Date(r.expires_at);
            const daysLeft = Math.ceil((expiresAt - now) / (1000 * 60 * 60 * 24));
            if (daysLeft <= 0) {
                issues.push({
                    level: 'error',
                    code: 'expired',
                    message: `«${credentialName}»: đã hết hạn (${expiresAt.toLocaleDateString('vi-VN')}).`,
                    credentialId,
                    credentialName,
                });
            } else if (daysLeft <= 90) {
                issues.push({
                    level: 'warning',
                    code: 'expiring_soon',
                    message: `«${credentialName}»: còn ${daysLeft} ngày hết hạn.`,
                    credentialId,
                    credentialName,
                });
            }
        }
        if ((r.status?.value || r.status) === 'locked') {
            issues.push({
                level: 'warning',
                code: 'locked',
                message: `«${credentialName}»: tài khoản đang bị khóa.`,
                credentialId,
                credentialName,
            });
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
