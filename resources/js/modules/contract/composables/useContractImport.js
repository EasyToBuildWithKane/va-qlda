import XLSX from 'xlsx-js-style';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';
const AMBER_SOFT = 'FFFBEB';
const AMBER_TEXT = 'B45309';

export const IMPORT_TEMPLATE_MARKER = 'VA_CLM_IMPORT_V1';

export const IMPORT_HEADERS = [
    { key: 'name', label: 'Tên hợp đồng *', width: 36 },
    { key: 'vendor', label: 'Nhà cung cấp', width: 22 },
    { key: 'category', label: 'Nhóm dịch vụ', width: 22 },
    { key: 'using_unit', label: 'Đơn vị sử dụng', width: 20 },
    { key: 'owner', label: 'Người phụ trách', width: 20 },
    { key: 'annual_cost', label: 'Chi phí năm', width: 16 },
    { key: 'lifecycle_cost', label: 'Chi phí vòng đời', width: 18 },
    { key: 'payment_status', label: 'Thanh toán', width: 16 },
    { key: 'effective_date', label: 'Ngày hiệu lực (DD/MM/YYYY)', width: 20 },
    { key: 'expiry_date', label: 'Ngày hết hạn (DD/MM/YYYY)', width: 20 },
    { key: 'status', label: 'Trạng thái', width: 16 },
];

const PAYMENT_ALIASES = {
    unpaid: ['unpaid', 'chưa thanh toán', 'chua thanh toan', 'chưa tt'],
    partial: ['partial', 'một phần', 'mot phan', 'thanh toán một phần'],
    paid: ['paid', 'đã thanh toán', 'da thanh toan', 'đã tt'],
};

const STATUS_ALIASES = {
    draft: ['draft', 'nháp', 'nhap'],
    active: ['active', 'đang hiệu lực', 'dang hieu luc', 'hiệu lực'],
    expiring_soon: ['expiring_soon', 'sắp hết hạn', 'sap het han'],
    expired: ['expired', 'đã hết hạn', 'da het han', 'hết hạn'],
    pending_renewal: ['pending_renewal', 'chờ gia hạn', 'cho gia han'],
    terminated: ['terminated', 'đã thanh lý', 'da thanh ly', 'thanh lý'],
};

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
    subtitle: { font: { sz: 10, color: { rgb: SLATE_600 }, italic: true }, alignment: { horizontal: 'left', wrapText: true } },
    section: { font: { bold: true, sz: 11, color: { rgb: WHITE } }, fill: { fgColor: { rgb: BRAND } }, alignment: { horizontal: 'left', vertical: 'center' } },
    guide: { font: { sz: 10, color: { rgb: '334155' } }, alignment: { vertical: 'top', wrapText: true }, border: borderThin() },
    guideBold: { font: { bold: true, sz: 10, color: { rgb: BRAND } }, alignment: { vertical: 'top', wrapText: true } },
    header: { font: { bold: true, sz: 10, color: { rgb: WHITE } }, fill: { fgColor: { rgb: BRAND } }, alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: borderThin() },
    required: { font: { bold: true, sz: 10, color: { rgb: BRAND } }, fill: { fgColor: { rgb: BRAND_SOFT } }, alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: borderThin() },
    sample: { font: { sz: 10, color: { rgb: SLATE_600 }, italic: true }, fill: { fgColor: { rgb: SLATE_50 } }, alignment: { vertical: 'center', wrapText: true }, border: borderThin() },
    note: { font: { sz: 9, color: { rgb: AMBER_TEXT } }, fill: { fgColor: { rgb: AMBER_SOFT } }, alignment: { vertical: 'center', wrapText: true } },
    input: { font: { sz: 10, color: { rgb: '1E293B' } }, alignment: { vertical: 'center', wrapText: true }, border: borderThin() },
};

function setCell(ws, r, c, value, style) {
    ws[XLSX.utils.encode_cell({ r, c })] = { v: value ?? '', t: 's', s: style };
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
    return {
        dd: String(t.getDate()).padStart(2, '0'),
        mm: String(t.getMonth() + 1).padStart(2, '0'),
        yyyy: t.getFullYear(),
    };
}
function normalizeKey(val) {
    return String(val ?? '').trim().toLowerCase()
        .replace(/đ/g, 'd').replace(/Đ/g, 'd')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/\s+/g, ' ');
}
function mapAlias(value, aliasMap) {
    const key = normalizeKey(value);
    if (!key) return null;
    for (const [canonical, aliases] of Object.entries(aliasMap)) {
        if (aliases.some((a) => normalizeKey(a) === key)) return canonical;
    }
    return null;
}
function parseExcelDate(val) {
    if (val == null || val === '') return null;
    if (typeof val === 'number') {
        const parsed = XLSX.SSF.parse_date_code(val);
        if (!parsed) return null;
        return `${parsed.y}-${String(parsed.m).padStart(2, '0')}-${String(parsed.d).padStart(2, '0')}`;
    }
    const s = String(val).trim();
    const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
    const vn = s.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})/);
    if (vn) return `${vn[3]}-${String(vn[2]).padStart(2, '0')}-${String(vn[1]).padStart(2, '0')}`;
    const parsed = new Date(s);
    if (!Number.isNaN(parsed.getTime())) {
        return `${parsed.getFullYear()}-${String(parsed.getMonth() + 1).padStart(2, '0')}-${String(parsed.getDate()).padStart(2, '0')}`;
    }
    return null;
}
function parseNumber(val) {
    if (val == null || val === '') return null;
    const n = Number(String(val).replace(/[^\d.-]/g, ''));
    return Number.isNaN(n) ? null : n;
}
function resolveByName(name, list) {
    const key = normalizeKey(name);
    if (!key) return { id: null };
    const exact = list.find((e) => normalizeKey(e.name) === key);
    if (exact) return { id: exact.id, name: exact.name };
    const partial = list.find((e) => normalizeKey(e.name).includes(key) || key.includes(normalizeKey(e.name)));
    if (partial) return { id: partial.id, name: partial.name };
    return { id: null, error: `Không tìm thấy: "${name}"` };
}

// ── Template ────────────────────────────────────────────────────────────

function buildGuideSheet({ vendors, statusOptions, paymentOptions }) {
    const ws = {};
    const COLS = 5;
    let row = 0;

    setCell(ws, row, 0, 'HƯỚNG DẪN NHẬP HỢP ĐỒNG', S.title);
    mergeRow(ws, row, 0, COLS); row++;
    setCell(ws, row, 0, 'Mẫu chuẩn VAschools · Module Quản lý Hợp đồng (CLM)', S.subtitle);
    mergeRow(ws, row, 0, COLS); row += 2;

    const steps = [
        '1. Mở sheet "Nhap lieu" và điền dữ liệu từ dòng 8 trở đi (sau 2 dòng mẫu, không sửa dòng tiêu đề cột).',
        '2. Cột có dấu * là bắt buộc: Tên hợp đồng.',
        '3. Nhà cung cấp / Người phụ trách: ghi đúng tên như trong hệ thống (xem sheet Tham chiếu).',
        '4. Chi phí: nhập số (VND), không dấu phân cách. Vd: 12000000.',
        '5. Thanh toán: unpaid | partial | paid (hoặc tiếng Việt).',
        '6. Trạng thái: để trống = Đang hiệu lực. Trạng thái sắp/đã hết hạn sẽ tự cập nhật theo ngày.',
        '7. Ngày: định dạng DD/MM/YYYY (vd: 15/06/2026).',
        '8. Xóa dòng mẫu (in nghiêng) trước khi tải lên. Tối đa 200 dòng/lần.',
        '9. Trên web: bấm Dữ liệu → tab Nhập → chọn file → xem trước → Xác nhận.',
    ];
    steps.forEach((text) => { setCell(ws, row, 0, text, S.guide); mergeRow(ws, row, 0, COLS); row++; });
    row++;

    setCell(ws, row, 0, 'GIÁ TRỊ TRẠNG THÁI', S.section);
    mergeRow(ws, row, 0, 2);
    setCell(ws, row, 3, 'GIÁ TRỊ THANH TOÁN', S.section);
    mergeRow(ws, row, 3, COLS); row++;

    (statusOptions || []).forEach((opt, i) => {
        setCell(ws, row + i, 0, opt.label ?? '', S.guide);
        setCell(ws, row + i, 1, opt.value ?? '', S.guideBold);
    });
    (paymentOptions || []).forEach((opt, i) => {
        setCell(ws, row + i, 3, opt.label ?? '', S.guide);
        setCell(ws, row + i, 4, opt.value ?? '', S.guideBold);
    });
    row += Math.max(statusOptions?.length || 0, paymentOptions?.length || 0) + 1;

    setCell(ws, row, 0, 'DANH SÁCH NHÀ CUNG CẤP', S.section);
    mergeRow(ws, row, 0, COLS); row++;
    (vendors || []).slice(0, 40).forEach((v, i) => {
        const col = (i % 3) * 2;
        const r = row + Math.floor(i / 3);
        setCell(ws, r, col, v.name, S.guide);
    });
    row += Math.ceil(((vendors || []).length || 1) / 3) + 1;

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: row, c: COLS } });
    setColWidths(ws, [20, 16, 4, 20, 16]);
    ws['!rows'] = [{ hpt: 28 }, { hpt: 18 }];
    return ws;
}

function buildDataSheet() {
    const ws = {};
    const COLS = IMPORT_HEADERS.length - 1;

    setCell(ws, 0, 0, 'NHẬP HỢP ĐỒNG', S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, 'Điền từ dòng 8 (sau 2 dòng mẫu) · Xóa dòng mẫu trước khi nhập', S.subtitle);
    mergeRow(ws, 1, 0, COLS);
    setCell(ws, 2, 0, '⚠ Không đổi tên cột. Cột * là bắt buộc.', S.note);
    mergeRow(ws, 2, 0, COLS);
    setCell(ws, 3, 0, IMPORT_TEMPLATE_MARKER, S.subtitle);
    mergeRow(ws, 3, 0, COLS);

    const headerRow = 4;
    IMPORT_HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h.label, h.key === 'name' ? S.required : S.header));

    const samples = [
        ['Microsoft 365 E3 (50 license)', 'Microsoft', 'Microsoft 365', 'Toàn trường', '', '120000000', '360000000', 'paid', '01/01/2026', '31/12/2026', 'active'],
        ['Google Workspace Business', 'Google', 'Workspace', 'Khối văn phòng', '', '60000000', '180000000', 'unpaid', '01/03/2026', '28/02/2027', 'active'],
    ];
    samples.forEach((r, idx) => r.forEach((val, c) => setCell(ws, headerRow + 1 + idx, c, val, S.sample)));

    const inputStart = headerRow + 1 + samples.length;
    for (let r = inputStart; r < inputStart + 50; r++) {
        IMPORT_HEADERS.forEach((_, c) => setCell(ws, r, c, '', S.input));
    }

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: inputStart + 49, c: COLS } });
    setColWidths(ws, IMPORT_HEADERS.map((h) => h.width));
    ws['!rows'] = [{ hpt: 26 }, { hpt: 16 }, { hpt: 20 }];
    return ws;
}

export function downloadContractImportTemplate({ vendors = [], statusOptions = [], paymentOptions = [] } = {}) {
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildGuideSheet({ vendors, statusOptions, paymentOptions }), 'Huong dan');
    XLSX.utils.book_append_sheet(wb, buildDataSheet(), 'Nhap lieu');
    const t = fileStamp();
    XLSX.writeFile(wb, `VA_MauNhap_HopDong_${t.dd}${t.mm}${t.yyyy}.xlsx`);
}

// ── Parse ───────────────────────────────────────────────────────────────

function cellText(cell) {
    if (!cell) return '';
    if (cell.w != null && cell.w !== '') return String(cell.w);
    if (cell.v != null) return String(cell.v);
    return '';
}
function readSheetMatrix(sheet) {
    if (!sheet?.['!ref']) return [];
    const range = XLSX.utils.decode_range(sheet['!ref']);
    const rows = [];
    for (let R = range.s.r; R <= range.e.r; R++) {
        const r = [];
        for (let C = range.s.c; C <= range.e.c; C++) r.push(cellText(sheet[XLSX.utils.encode_cell({ r: R, c: C })]));
        rows.push(r);
    }
    return rows;
}
function pickImportSheet(wb) {
    const names = wb.SheetNames ?? [];
    const norm = (n) => normalizeKey(n).replace(/\s/g, '');
    return names.find((n) => norm(n) === 'nhaplieu')
        ?? names.find((n) => /nhap|nhập/i.test(n) && !/huong|guide|dan$/i.test(n))
        ?? (names.length > 1 ? names[names.length - 1] : names[0]);
}
function columnIndexMap(headerRow) {
    const map = {};
    headerRow.forEach((cell, idx) => {
        const k = normalizeKey(cell).replace(/\*/g, '').trim();
        if (k.includes('ten hop dong') || k === 'name') map.name = idx;
        else if (k.includes('nha cung cap') || k.includes('vendor')) map.vendor = idx;
        else if (k.includes('nhom dich vu') || k.includes('category')) map.category = idx;
        else if (k.includes('don vi su dung') || k.includes('using')) map.using_unit = idx;
        else if (k.includes('nguoi phu trach') || k.includes('owner')) map.owner = idx;
        else if (k.includes('chi phi vong doi') || k.includes('lifecycle')) map.lifecycle_cost = idx;
        else if (k.includes('chi phi nam') || k.includes('annual')) map.annual_cost = idx;
        else if (k.includes('thanh toan') || k.includes('payment')) map.payment_status = idx;
        else if (k.includes('ngay hieu luc') || k.includes('effective')) map.effective_date = idx;
        else if (k.includes('ngay het han') || k.includes('expiry')) map.expiry_date = idx;
        else if (k.includes('trang thai') || k.includes('status')) map.status = idx;
    });
    return map;
}
function findHeaderIndex(rows) {
    for (let i = 0; i < Math.min(rows.length, 40); i++) {
        if (Array.isArray(rows[i]) && rows[i].some((c) => String(c).trim() === IMPORT_TEMPLATE_MARKER)) return i + 1;
    }
    for (let i = 0; i < Math.min(rows.length, 40); i++) {
        const m = columnIndexMap(rows[i] || []);
        if (m.name != null) return i;
    }
    return -1;
}
function cellVal(row, idx) {
    if (idx == null || idx < 0) return '';
    const v = row[idx];
    return v == null ? '' : String(v).trim();
}

export async function parseContractImportFile(file) {
    const buf = await file.arrayBuffer();
    const wb = XLSX.read(buf, { type: 'array', cellDates: true });
    const matrix = readSheetMatrix(wb.Sheets[pickImportSheet(wb)]);
    const headerIdx = findHeaderIndex(matrix);

    if (headerIdx < 0) {
        return { rows: [], errors: ['Không tìm thấy dòng tiêu đề (cột "Tên hợp đồng"). Hãy dùng file mẫu VA.'] };
    }

    const colMap = columnIndexMap(matrix[headerIdx]);
    const rows = [];
    const SAMPLE = new Set([normalizeKey('Microsoft 365 E3 (50 license)'), normalizeKey('Google Workspace Business')]);

    for (let i = headerIdx + 1; i < matrix.length; i++) {
        const row = matrix[i];
        if (!Array.isArray(row)) continue;
        const name = cellVal(row, colMap.name);
        const vendorRaw = cellVal(row, colMap.vendor);
        const allEmpty = !name && !vendorRaw && !cellVal(row, colMap.annual_cost);
        if (allEmpty) continue;
        if (SAMPLE.has(normalizeKey(name))) continue;

        rows.push({
            line: i + 1,
            name,
            vendor_raw: vendorRaw,
            category_raw: cellVal(row, colMap.category),
            using_unit: cellVal(row, colMap.using_unit) || null,
            owner_raw: cellVal(row, colMap.owner),
            annual_cost_raw: cellVal(row, colMap.annual_cost),
            lifecycle_cost_raw: cellVal(row, colMap.lifecycle_cost),
            payment_status_raw: cellVal(row, colMap.payment_status),
            effective_date_raw: cellVal(row, colMap.effective_date),
            expiry_date_raw: cellVal(row, colMap.expiry_date),
            status_raw: cellVal(row, colMap.status),
        });
    }

    const errors = rows.length ? [] : ['Chưa có dòng dữ liệu để nhập. Hãy thêm ít nhất một dòng trong sheet "Nhap lieu".'];
    return { rows, errors };
}

// ── Validate ────────────────────────────────────────────────────────────

function ctxOf({ vendors = [], categories = [], employees = [], statusOptions = [], paymentOptions = [] } = {}) {
    return { vendors, categories, employees, statusOptions, paymentOptions };
}

export function validatePreviewEdit(edit, ctx) {
    const errors = [];
    const name = edit.name?.trim() ?? '';
    if (!name) errors.push('Thiếu tên hợp đồng');
    else if (name.length > 255) errors.push('Tên tối đa 255 ký tự');

    const vendor = edit.vendor_id ? ctx.vendors.find((v) => v.id === Number(edit.vendor_id)) : null;
    const owner = edit.owner_id ? ctx.employees.find((e) => e.id === Number(edit.owner_id)) : null;

    let payment = edit.payment_status || 'unpaid';
    if (!ctx.paymentOptions.some((o) => o.value === payment)) payment = mapAlias(payment, PAYMENT_ALIASES) || 'unpaid';

    let status = edit.status || 'active';
    if (!ctx.statusOptions.some((o) => o.value === status)) status = mapAlias(status, STATUS_ALIASES) || 'active';

    let effective = edit.effective_date || null;
    if (effective && !/^\d{4}-\d{2}-\d{2}$/.test(effective)) effective = parseExcelDate(effective);
    if (edit.effective_date && !effective) errors.push('Ngày hiệu lực không hợp lệ');

    let expiry = edit.expiry_date || null;
    if (expiry && !/^\d{4}-\d{2}-\d{2}$/.test(expiry)) expiry = parseExcelDate(expiry);
    if (edit.expiry_date && !expiry) errors.push('Ngày hết hạn không hợp lệ');
    if (effective && expiry && expiry < effective) errors.push('Ngày hết hạn phải sau ngày hiệu lực');

    const annual = edit.annual_cost === '' || edit.annual_cost == null ? null : Number(edit.annual_cost);
    if (annual != null && (Number.isNaN(annual) || annual < 0)) errors.push('Chi phí năm không hợp lệ');
    const lifecycle = edit.lifecycle_cost === '' || edit.lifecycle_cost == null ? null : Number(edit.lifecycle_cost);
    if (lifecycle != null && (Number.isNaN(lifecycle) || lifecycle < 0)) errors.push('Chi phí vòng đời không hợp lệ');

    return {
        name,
        vendor_id: vendor?.id ?? null,
        vendor_name: vendor?.name ?? (edit.vendor_name || '—'),
        category_id: edit.category_id ? Number(edit.category_id) : null,
        using_unit: edit.using_unit?.trim() || null,
        owner_id: owner?.id ?? null,
        owner_name: owner?.name ?? (edit.owner_name || '—'),
        annual_cost: annual,
        lifecycle_cost: lifecycle,
        payment_status: payment,
        effective_date: effective,
        expiry_date: expiry,
        status,
        valid: errors.length === 0,
        errors,
    };
}

export function validateImportRows(rawRows, opts = {}) {
    const ctx = ctxOf(opts);
    return rawRows.map((raw) => {
        const vendorRes = resolveByName(raw.vendor_raw, ctx.vendors);
        const ownerRes = resolveByName(raw.owner_raw, ctx.employees);
        const catList = vendorRes.id ? ctx.categories.filter((c) => c.vendor_id === vendorRes.id || c.vendor_id == null) : ctx.categories;
        const catRes = raw.category_raw ? resolveByName(raw.category_raw, catList) : { id: null };

        const edit = {
            name: raw.name ?? '',
            vendor_id: vendorRes.id,
            vendor_name: vendorRes.name ?? raw.vendor_raw ?? '',
            category_id: catRes.id,
            using_unit: raw.using_unit ?? '',
            owner_id: ownerRes.id,
            owner_name: ownerRes.name ?? raw.owner_raw ?? '',
            annual_cost: parseNumber(raw.annual_cost_raw) ?? '',
            lifecycle_cost: parseNumber(raw.lifecycle_cost_raw) ?? '',
            payment_status: raw.payment_status_raw ? (mapAlias(raw.payment_status_raw, PAYMENT_ALIASES) ?? raw.payment_status_raw) : 'unpaid',
            effective_date: parseExcelDate(raw.effective_date_raw) ?? '',
            expiry_date: parseExcelDate(raw.expiry_date_raw) ?? '',
            status: raw.status_raw ? (mapAlias(raw.status_raw, STATUS_ALIASES) ?? raw.status_raw) : 'active',
        };

        const extra = [];
        if (raw.vendor_raw && vendorRes.error) extra.push(`Nhà cung cấp: ${vendorRes.error}`);
        if (raw.owner_raw && ownerRes.error) extra.push(`Người phụ trách: ${ownerRes.error}`);

        const validated = validatePreviewEdit(edit, ctx);
        return {
            line: raw.line,
            edit,
            ...validated,
            errors: [...extra, ...validated.errors],
            valid: extra.length === 0 && validated.valid,
        };
    });
}

export function revalidatePreviewRow(row, opts = {}) {
    const validated = validatePreviewEdit(row.edit, ctxOf(opts));
    Object.assign(row, validated);
    return row;
}

export function createPreviewRows(validatedRows, opts = {}) {
    return validatedRows.map((r) => {
        const row = {
            line: r.line,
            edit: {
                name: r.edit?.name ?? '',
                vendor_id: r.edit?.vendor_id ?? null,
                vendor_name: r.edit?.vendor_name ?? '',
                category_id: r.edit?.category_id ?? null,
                using_unit: r.edit?.using_unit ?? '',
                owner_id: r.edit?.owner_id ?? null,
                owner_name: r.edit?.owner_name ?? '',
                annual_cost: r.edit?.annual_cost ?? '',
                lifecycle_cost: r.edit?.lifecycle_cost ?? '',
                payment_status: r.edit?.payment_status ?? 'unpaid',
                effective_date: r.edit?.effective_date ?? '',
                expiry_date: r.edit?.expiry_date ?? '',
                status: r.edit?.status ?? 'active',
            },
            valid: r.valid,
            errors: [...(r.errors ?? [])],
        };
        return revalidatePreviewRow(row, opts);
    });
}

export function rowsToPayload(validRows) {
    return validRows.map((r) => ({
        name: r.name ?? r.edit?.name?.trim(),
        vendor_id: r.vendor_id,
        category_id: r.category_id,
        using_unit: r.using_unit,
        owner_id: r.owner_id,
        annual_cost: r.annual_cost,
        lifecycle_cost: r.lifecycle_cost,
        payment_status: r.payment_status,
        effective_date: r.effective_date || null,
        expiry_date: r.expiry_date || null,
        status: r.status,
    }));
}
