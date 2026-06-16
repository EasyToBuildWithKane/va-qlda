import XLSX from 'xlsx-js-style';

export const CREDENTIAL_IMPORT_MARKER = 'VA_CREDENTIAL_IMPORT_V1';

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

export function downloadCredentialTemplate() {
    const wb = XLSX.utils.book_new();
    const guide = XLSX.utils.aoa_to_sheet([
        ['Hướng dẫn nhập tài khoản VA-QLDA'],
        ['1. Tải mẫu · 2. Điền từ dòng 8 · 3. Upload · 4. Xem trước · 5. Nhập'],
    ]);
    XLSX.utils.book_append_sheet(wb, guide, 'Huong dan');

    const data = [
        [CREDENTIAL_IMPORT_MARKER],
        [],
        [],
        [],
        CREDENTIAL_HEADERS,
        ['CMS Production', 'internal_system', 'cms', 'admin', 'https://cms.example', 'Internal', 'production', 'active'],
    ];
    const sheet = XLSX.utils.aoa_to_sheet(data);
    XLSX.utils.book_append_sheet(wb, sheet, 'Nhap lieu');
    XLSX.writeFile(wb, `VA_Credential_Mau_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

function normalizeHeader(h) {
    return String(h || '')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

export function parseCredentialFile(file) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const wb = XLSX.read(e.target.result, { type: 'array' });
                const sheet = wb.Sheets.Nhap_lieu || wb.Sheets['Nhap lieu'] || wb.Sheets[wb.SheetNames[0]];
                const matrix = XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
                let headerIdx = matrix.findIndex((row) => row.some((c) => normalizeHeader(c).includes('ten tai khoan')));
                if (headerIdx < 0) {
                    headerIdx = matrix.findIndex((row) => row[0] === CREDENTIAL_IMPORT_MARKER) + 1;
                }
                const headers = matrix[headerIdx] || [];
                const rows = [];
                const errors = [];
                for (let i = headerIdx + 1; i < matrix.length; i += 1) {
                    const row = matrix[i];
                    if (!row || !row.some((c) => String(c).trim())) continue;
                    const obj = {};
                    headers.forEach((h, idx) => {
                        const key = normalizeHeader(h);
                        if (key.includes('ten')) obj.name = String(row[idx] || '').trim();
                        if (key.includes('loai')) obj.credential_type = String(row[idx] || '').trim();
                        if (key.includes('he thong')) obj.system_category = String(row[idx] || '').trim();
                        if (key.includes('username')) obj.username = String(row[idx] || '').trim();
                        if (key.includes('url')) obj.login_url = String(row[idx] || '').trim();
                        if (key.includes('nha cung cap')) obj.provider_name = String(row[idx] || '').trim();
                        if (key.includes('moi truong')) obj.environment = String(row[idx] || '').trim() || 'production';
                        if (key.includes('trang thai')) obj.status = String(row[idx] || '').trim() || 'active';
                    });
                    if (!obj.name) {
                        errors.push(`Dòng ${i + 1}: thiếu tên tài khoản.`);
                        continue;
                    }
                    rows.push(obj);
                }
                if (rows.length > 200) {
                    errors.push('Tối đa 200 dòng mỗi lần nhập.');
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

export function exportCredentialWorkbook(rows, filenamePrefix = 'VA_Credential') {
    const header = CREDENTIAL_HEADERS;
    const body = rows.map((r) => [
        r.name,
        r.credential_type?.value || r.credential_type,
        r.system_category?.value || r.system_category,
        r.username,
        r.login_url,
        r.provider_name,
        r.environment?.value || r.environment,
        r.status?.value || r.status,
    ]);
    const ws = XLSX.utils.aoa_to_sheet([header, ...body]);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Tai khoan');
    XLSX.writeFile(wb, `${filenamePrefix}_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

export function reconcileCredentials(rows) {
    const issues = [];
    rows.forEach((r, idx) => {
        if (!r.owner && !r.owner_id) {
            issues.push({
                level: 'warning',
                code: 'no_owner',
                message: `Dòng ${idx + 1}: chưa gán người phụ trách.`,
            });
        }
        if (!r.mfa_enabled) {
            issues.push({
                level: 'info',
                code: 'no_mfa',
                message: `${r.name}: chưa bật MFA.`,
            });
        }
    });
    const errors = issues.filter((i) => i.level === 'error').length;
    return {
        issues,
        summary: {
            total: rows.length,
            errors,
            warnings: issues.filter((i) => i.level === 'warning').length,
            info: issues.filter((i) => i.level === 'info').length,
        },
    };
}
