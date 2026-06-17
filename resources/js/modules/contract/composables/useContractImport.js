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

/** Danh mục nhóm dịch vụ (dedupe theo tên). */
export function serviceGroupOptions(categories = []) {
    const byName = new Map();
    for (const c of categories) {
        if (!byName.has(c.name)) byName.set(c.name, c);
    }
    return [...byName.values()].sort((a, b) => {
        const order = (a.sort_order ?? 0) - (b.sort_order ?? 0);
        if (order !== 0) return order;
        return String(a.name).localeCompare(String(b.name), 'vi');
    });
}

/** @deprecated Nhóm DV không lọc theo NCC — dùng serviceGroupOptions. */
export function categoriesForVendor(categories = [], _vendorId) {
    return serviceGroupOptions(categories);
}

export const IMPORT_HEADERS = [
    { key: 'code', label: 'Mã HĐ', width: 14 },
    { key: 'vendor', label: 'Tên NCC', width: 28 },
    { key: 'category', label: 'Nhóm DV', width: 16 },
    { key: 'service_name', label: 'Tên DV *', width: 20 },
    { key: 'using_unit', label: 'Phòng Ban', width: 22 },
    { key: 'owner', label: 'Người Phụ Trách', width: 24 },
    { key: 'manager', label: 'Người quản lý', width: 24 },
    { key: 'effective_date', label: 'Ngày Bắt Đầu', width: 16 },
    { key: 'expiry_date', label: 'Ngày Hết Hạn', width: 16 },
    { key: 'billing_cycle', label: 'Chu Kỳ', width: 14 },
    { key: 'status', label: 'Trạng Thái', width: 16 },
    { key: 'link', label: 'Link File', width: 28 },
    { key: 'notes', label: 'Ghi Chú', width: 24 },
];

const PAYMENT_ALIASES = {
    unpaid: ['unpaid', 'chưa thanh toán', 'chua thanh toan', 'chưa tt'],
    partial: ['partial', 'một phần', 'mot phan', 'thanh toán một phần'],
    paid: ['paid', 'đã thanh toán', 'da thanh toan', 'đã tt'],
};

const STATUS_ALIASES = {
    draft: ['draft', 'nháp', 'nhap'],
    active: ['active', 'đang hiệu lực', 'dang hieu luc', 'hiệu lực', 'còn hiệu lực'],
    expiring_soon: ['expiring_soon', 'sắp hết hạn', 'sap het han'],
    expired: ['expired', 'đã hết hạn', 'da het han', 'hết hạn'],
    pending_renewal: ['pending_renewal', 'chờ gia hạn', 'cho gia han'],
    addendum: ['addendum', 'chuyển phụ lục', 'chuyen phu luc', 'phụ lục'],
    terminated: ['terminated', 'đã thanh lý', 'da thanh ly', 'thanh lý'],
};

const BILLING_ALIASES = {
    one_time: ['one_time', 'một lần', 'mot lan', 'một lần duy nhất'],
    monthly: ['monthly', 'hàng tháng', 'hang thang', 'tháng'],
    quarterly: ['quarterly', 'hàng quý', 'hang quy', 'quý'],
    annual: ['annual', 'hàng năm', 'hang nam', 'năm', 'yearly'],
};

const RECOMMENDATION_ALIASES = {
    renew: ['renew', 'tiếp tục gia hạn', 'tiep tuc gia han', 'gia hạn'],
    do_not_renew: ['do_not_renew', 'không gia hạn', 'khong gia han', 'ngừng'],
    change_vendor: ['change_vendor', 'thay đổi ncc', 'thay doi ncc', 'đổi ncc'],
    needs_review: ['needs_review', 'cần review', 'can review', 'review'],
};

// ── Style helpers ─────────────────────────────────────────────────────────

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

// ── Normalisation & resolvers ─────────────────────────────────────────────

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
        if (aliases.some((a) => key.includes(normalizeKey(a)))) return canonical;
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
    return { id: null, error: `chưa có trong hệ thống` };
}
function resolveByEmail(email, employees) {
    const key = normalizeKey(email);
    if (!key) return { id: null };
    const hit = employees.find((e) => normalizeKey(e.email) === key);
    if (hit) return { id: hit.id, name: hit.name };
    return { id: null, error: `email chưa có trong hệ thống` };
}
/** Người phụ trách/quản lý: ưu tiên khớp email, sau đó tên. */
function resolvePerson(raw, employees) {
    if (!String(raw ?? '').trim()) return { id: null };
    if (String(raw).includes('@')) return resolveByEmail(raw, employees);
    return resolveByName(raw, employees);
}

// ── Template ───────────────────────────────────────────────────────────────

function buildGuideSheet({ vendors, statusOptions, billingOptions }) {
    const ws = {};
    const COLS = 5;
    let row = 0;

    setCell(ws, row, 0, 'HƯỚNG DẪN NHẬP HỢP ĐỒNG', S.title);
    mergeRow(ws, row, 0, COLS); row++;
    setCell(ws, row, 0, 'Mẫu chuẩn VAschools · Module Quản lý Hợp đồng (CLM)', S.subtitle);
    mergeRow(ws, row, 0, COLS); row += 2;

    const steps = [
        '1. Mở sheet "Nhap lieu" và điền dữ liệu từ dòng 8 trở đi (sau 2 dòng mẫu, không sửa dòng tiêu đề cột).',
        '2. Cột có dấu * là bắt buộc: Tên DV.',
        '3. Mã HĐ: nếu trùng mã đã có, hệ thống sẽ CẬP NHẬT hợp đồng đó; để trống sẽ tự sinh mã mới.',
        '4. Tên NCC / Nhóm DV: ghi tên; nếu chưa có hệ thống sẽ tự tạo khi nhập.',
        '5. Người phụ trách / quản lý: nhập email (vd: ten@vaschools.edu.vn) để khớp đúng nhân sự.',
        '6. Phòng Ban: ghi tên đơn vị sử dụng (vd: Mầm non Bình Thới).',
        '7. Chu kỳ: Hàng năm | Một lần | Hàng tháng | Hàng quý.',
        '8. Trạng thái để trống = Đang hiệu lực. Ngày: định dạng DD/MM/YYYY.',
        '9. Link file: dán URL/tên file, nhiều link cách nhau bằng xuống dòng. Tối đa 200 dòng/lần.',
    ];
    steps.forEach((text) => { setCell(ws, row, 0, text, S.guide); mergeRow(ws, row, 0, COLS); row++; });
    row++;

    setCell(ws, row, 0, 'GIÁ TRỊ TRẠNG THÁI', S.section);
    mergeRow(ws, row, 0, 2);
    setCell(ws, row, 3, 'GIÁ TRỊ CHU KỲ', S.section);
    mergeRow(ws, row, 3, COLS); row++;

    (statusOptions || []).forEach((opt, i) => {
        setCell(ws, row + i, 0, opt.label ?? '', S.guide);
        setCell(ws, row + i, 1, opt.value ?? '', S.guideBold);
    });
    (billingOptions || []).forEach((opt, i) => {
        setCell(ws, row + i, 3, opt.label ?? '', S.guide);
        setCell(ws, row + i, 4, opt.value ?? '', S.guideBold);
    });
    row += Math.max(statusOptions?.length || 0, billingOptions?.length || 0) + 1;

    setCell(ws, row, 0, 'DANH SÁCH NHÀ CUNG CẤP', S.section);
    mergeRow(ws, row, 0, COLS); row++;
    (vendors || []).slice(0, 60).forEach((v, i) => {
        const col = (i % 3) * 2;
        const r = row + Math.floor(i / 3);
        setCell(ws, r, col, v.name, S.guide);
    });
    row += Math.ceil(((vendors || []).length || 1) / 3) + 1;

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: row, c: COLS } });
    setColWidths(ws, [22, 16, 4, 22, 16]);
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
    IMPORT_HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h.label, h.key === 'service_name' ? S.required : S.header));

    const samples = [
        ['KON-VM-01', 'Công ty TNHH Một thành viên Kidsonline', 'Giáo vụ số', 'Kidsonline', 'Mầm non Bình Thới', 'Truchtm@vaschools.edu.vn', 'phongcongnghe@vaschools.edu.vn', '01/10/2024', '01/10/2025', 'Hàng năm', 'Chuyển phụ lục', 'KidsOnline.pdf\nHop dong KidsOnline - VM (1).docx', 'HCQT mua'],
        ['M365-01', 'Microsoft', 'License', 'Microsoft 365 E3', 'Toàn trường', '', '', '01/01/2026', '31/12/2026', 'Hàng năm', 'Đang hiệu lực', '', ''],
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

function buildCategoriesRefSheet({ categories = [], vendors = [] } = {}) {
    const ws = {};
    const vendorById = new Map(vendors.map((v) => [v.id, v.name]));
    setCell(ws, 0, 0, 'DANH SÁCH NHÓM DỊCH VỤ (chỉ tham chiếu — không upload sheet này)', S.title);
    mergeRow(ws, 0, 0, 2);
    setCell(ws, 1, 0, 'Nhóm dịch vụ', S.header);
    setCell(ws, 1, 1, 'Nhà cung cấp', S.header);
    setCell(ws, 1, 2, 'Ghi chú', S.header);
    const sorted = [...categories].sort((a, b) => String(a.name).localeCompare(String(b.name), 'vi'));
    sorted.forEach((c, idx) => {
        const r = 2 + idx;
        const vendorLabel = c.vendor_id != null ? (vendorById.get(c.vendor_id) ?? 'NCC #'.concat(c.vendor_id)) : 'Dùng chung';
        setCell(ws, r, 0, c.name, S.guide);
        setCell(ws, r, 1, vendorLabel, S.guide);
        setCell(ws, r, 2, c.vendor_id == null ? 'Áp dụng mọi NCC' : 'Theo NCC', S.guide);
    });
    if (!sorted.length) {
        setCell(ws, 2, 0, 'Chưa có nhóm trong hệ thống — ghi tên cột Nhóm dịch vụ khi nhập để tự tạo', S.note);
        mergeRow(ws, 2, 0, 2);
    }
    const lastRow = Math.max(2, 1 + sorted.length);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: lastRow, c: 2 } });
    setColWidths(ws, [28, 24, 22]);
    return ws;
}

export function downloadContractImportTemplate({
    vendors = [], categories = [], statusOptions = [], billingOptions = [],
} = {}) {
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildGuideSheet({ vendors, statusOptions, billingOptions }), 'Huong dan');
    XLSX.utils.book_append_sheet(wb, buildDataSheet(), 'Nhap lieu');
    if (categories.length || vendors.length) {
        XLSX.utils.book_append_sheet(wb, buildCategoriesRefSheet({ categories, vendors }), 'Nhom dich vu');
    }
    const t = fileStamp();
    XLSX.writeFile(wb, `VA_MauNhap_HopDong_${t.dd}${t.mm}${t.yyyy}.xlsx`);
}

// ── Parse (đa-sheet: Contracts + ContractFinances + Reviews) ───────────────

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

/** Bản đồ cột hợp đồng — nhận cả tiêu đề mẫu VA lẫn file thật (Mã HĐ, Tên DV…). */
function mapContractColumns(headerRow) {
    const map = {};
    headerRow.forEach((cell, idx) => {
        const k = normalizeKey(cell).replace(/\*/g, '').trim();
        if (!k) return;
        if ((k === 'ma hd' || k.includes('ma hop dong') || k === 'code') && map.code == null) map.code = idx;
        else if (k.includes('ten hop dong') || k === 'name') map.name = idx;
        else if (k === 'ten dv' || k.includes('ten dich vu') || k.includes('ten san pham')) map.service_name = idx;
        else if (k.includes('ten ncc') || k.includes('nha cung cap') || k.includes('vendor')) map.vendor = idx;
        else if (k.includes('nhom dv') || k.includes('nhom dich vu') || k.includes('category')) map.category = idx;
        else if (k.includes('phong ban') || k.includes('don vi su dung') || k.includes('using')) map.using_unit = idx;
        else if (k.includes('nguoi quan ly') || k.includes('manager')) map.manager = idx;
        else if (k.includes('nguoi phu trach') || k.includes('owner')) map.owner = idx;
        else if (k.includes('chu ky') || k.includes('billing')) map.billing_cycle = idx;
        else if (k.includes('chi phi vong doi') || k.includes('lifecycle')) map.lifecycle_cost = idx;
        else if (k.includes('chi phi nam') || k.includes('annual')) map.annual_cost = idx;
        else if (k.includes('thanh toan') || k.includes('payment')) map.payment_status = idx;
        else if (k.includes('ngay bat dau') || k.includes('ngay hieu luc') || k.includes('effective')) map.effective_date = idx;
        else if (k.includes('ngay het han') || k.includes('expiry')) map.expiry_date = idx;
        else if (k.includes('trang thai') || k.includes('status')) map.status = idx;
        else if (k.includes('link') || k.includes('file')) map.link = idx;
        else if (k.includes('ghi chu') || k.includes('note')) map.notes = idx;
    });
    return map;
}
function mapFinanceColumns(headerRow) {
    const map = {};
    headerRow.forEach((cell, idx) => {
        const k = normalizeKey(cell);
        if (k === 'ma hd' || k.includes('ma hop dong')) map.code = idx;
        else if (k.includes('ngay su dung')) map.used_date = idx;
        else if (k.includes('so luong')) map.quantity = idx;
        else if (k.includes('don gia')) map.unit_price = idx;
        else if (k.includes('phi khoi tao')) map.init_fee = idx;
        else if (k.includes('phi duy tri')) map.maintenance_fee = idx;
        else if (k.includes('thoi han')) map.term_months = idx;
        else if (k.includes('tong tien')) map.total = idx;
        else if (k.includes('chi phi gia han') || k.includes('gia han')) map.renewal_cost = idx;
    });
    return map;
}
function mapReviewColumns(headerRow) {
    const map = {};
    headerRow.forEach((cell, idx) => {
        const k = normalizeKey(cell);
        if (k === 'ma hd' || k.includes('ma hop dong')) map.code = idx;
        else if (k.includes('ngay dg') || k.includes('ngay danh gia')) map.reviewed_at = idx;
        else if (k.includes('cl dv') || k.includes('chat luong')) map.service_quality = idx;
        else if (k === 'sla') map.sla = idx;
        else if (k.includes('toc do')) map.speed = idx;
        else if (k.includes('gia hl') || k.includes('gia hai long')) map.price_satisfaction = idx;
        else if (k.includes('on dinh')) map.stability = idx;
        else if (k.includes('thai do')) map.attitude = idx;
        else if (k.includes('tong diem')) map.total_score = idx;
        else if (k.includes('de xuat')) map.recommendation = idx;
        else if (k.includes('nguoi dg') || k.includes('nguoi danh gia')) map.reviewer_email = idx;
    });
    return map;
}

function cellVal(row, idx) {
    if (idx == null || idx < 0) return '';
    const v = row[idx];
    return v == null ? '' : String(v).trim();
}

const SAMPLE_CODES = new Set([normalizeKey('KON-VM-01'), normalizeKey('M365-01')]);
const SAMPLE_SERVICE_NAMES = new Set([normalizeKey('Kidsonline'), normalizeKey('Microsoft 365 E3')]);

function classifySheet(headerRow) {
    const fin = mapFinanceColumns(headerRow);
    if (fin.code != null && (fin.quantity != null || fin.unit_price != null || fin.total != null)) return 'finance';
    const rev = mapReviewColumns(headerRow);
    if (rev.code != null && (rev.total_score != null || rev.service_quality != null || rev.recommendation != null)) return 'review';
    const con = mapContractColumns(headerRow);
    if (con.name != null || con.service_name != null || (con.code != null && con.vendor != null)) return 'contract';
    return null;
}

export async function parseContractImportFile(file) {
    const buf = await file.arrayBuffer();
    const wb = XLSX.read(buf, { type: 'array', cellDates: true });

    let rows = [];
    const finances = [];
    const reviews = [];
    const errors = [];
    let contractSheetFound = false;

    for (const sheetName of wb.SheetNames ?? []) {
        const matrix = readSheetMatrix(wb.Sheets[sheetName]);
        if (!matrix.length) continue;

        // VA template: chỉ sheet "Nhap lieu" mới mang marker hợp đồng.
        const markerIdx = matrix.findIndex((r) => Array.isArray(r) && r.some((c) => String(c).trim() === IMPORT_TEMPLATE_MARKER));

        let kind = null;
        let headerIdx = -1;
        if (markerIdx >= 0) {
            kind = 'contract';
            headerIdx = markerIdx + 1;
        } else {
            // Quét vài dòng đầu để tìm dòng tiêu đề và phân loại sheet.
            for (let i = 0; i < Math.min(matrix.length, 20) && kind == null; i++) {
                kind = classifySheet(matrix[i] || []);
                if (kind) headerIdx = i;
            }
        }
        if (!kind || headerIdx < 0) continue;

        if (kind === 'contract') {
            contractSheetFound = true;
            const col = mapContractColumns(matrix[headerIdx]);
            for (let i = headerIdx + 1; i < matrix.length; i++) {
                const row = matrix[i];
                if (!Array.isArray(row)) continue;
                const code = cellVal(row, col.code);
                const serviceName = cellVal(row, col.service_name);
                const name = serviceName || cellVal(row, col.name);
                const vendor = cellVal(row, col.vendor);
                if (!code && !name && !vendor) continue;
                if (SAMPLE_CODES.has(normalizeKey(code)) || SAMPLE_SERVICE_NAMES.has(normalizeKey(name))) continue;

                rows.push({
                    line: i + 1,
                    code,
                    name,
                    service_name: serviceName,
                    vendor_raw: vendor,
                    category_raw: cellVal(row, col.category),
                    using_unit: cellVal(row, col.using_unit) || null,
                    owner_raw: cellVal(row, col.owner),
                    manager_raw: cellVal(row, col.manager),
                    billing_raw: cellVal(row, col.billing_cycle),
                    annual_cost_raw: cellVal(row, col.annual_cost),
                    lifecycle_cost_raw: cellVal(row, col.lifecycle_cost),
                    payment_status_raw: cellVal(row, col.payment_status),
                    effective_date_raw: cellVal(row, col.effective_date),
                    expiry_date_raw: cellVal(row, col.expiry_date),
                    status_raw: cellVal(row, col.status),
                    notes_raw: cellVal(row, col.notes),
                    link_raw: cellVal(row, col.link),
                });
            }
        } else if (kind === 'finance') {
            const col = mapFinanceColumns(matrix[headerIdx]);
            for (let i = headerIdx + 1; i < matrix.length; i++) {
                const row = matrix[i];
                if (!Array.isArray(row)) continue;
                const code = cellVal(row, col.code);
                if (!code || SAMPLE_CODES.has(normalizeKey(code))) continue;
                finances.push({
                    code,
                    used_date: parseExcelDate(cellVal(row, col.used_date)),
                    quantity: parseNumber(cellVal(row, col.quantity)),
                    unit_price: parseNumber(cellVal(row, col.unit_price)),
                    init_fee: parseNumber(cellVal(row, col.init_fee)),
                    maintenance_fee: parseNumber(cellVal(row, col.maintenance_fee)),
                    term_months: parseNumber(cellVal(row, col.term_months)),
                    total: parseNumber(cellVal(row, col.total)),
                    renewal_cost: parseNumber(cellVal(row, col.renewal_cost)),
                });
            }
        } else if (kind === 'review') {
            const col = mapReviewColumns(matrix[headerIdx]);
            for (let i = headerIdx + 1; i < matrix.length; i++) {
                const row = matrix[i];
                if (!Array.isArray(row)) continue;
                const code = cellVal(row, col.code);
                if (!code || SAMPLE_CODES.has(normalizeKey(code))) continue;
                const clamp = (n) => (n == null ? null : Math.max(0, Math.min(10, n)));
                reviews.push({
                    code,
                    reviewer_email: cellVal(row, col.reviewer_email) || null,
                    reviewed_at: parseExcelDate(cellVal(row, col.reviewed_at)),
                    service_quality: clamp(parseNumber(cellVal(row, col.service_quality))),
                    sla: clamp(parseNumber(cellVal(row, col.sla))),
                    speed: clamp(parseNumber(cellVal(row, col.speed))),
                    price_satisfaction: clamp(parseNumber(cellVal(row, col.price_satisfaction))),
                    stability: clamp(parseNumber(cellVal(row, col.stability))),
                    attitude: clamp(parseNumber(cellVal(row, col.attitude))),
                    total_score: clamp(parseNumber(cellVal(row, col.total_score))),
                    recommendation: mapAlias(cellVal(row, col.recommendation), RECOMMENDATION_ALIASES),
                });
            }
        }
    }

    if (!contractSheetFound) {
        errors.push('Không tìm thấy sheet hợp đồng (cần cột "Mã HĐ" hoặc "Tên DV"). Hãy dùng file mẫu VA hoặc file quản lý phần mềm.');
    } else if (!rows.length) {
        errors.push('Chưa có dòng dữ liệu hợp đồng để nhập.');
    }

    return { rows, finances, reviews, errors };
}

// ── Validate hợp đồng ──────────────────────────────────────────────────────

function ctxOf({ vendors = [], categories = [], employees = [], departments = [], statusOptions = [], paymentOptions = [], billingOptions = [] } = {}) {
    return { vendors, categories, employees, departments, statusOptions, paymentOptions, billingOptions };
}

export function validatePreviewEdit(edit, ctx) {
    const errors = [];
    const name = edit.name?.trim() ?? '';
    if (!name) errors.push('Thiếu tên dịch vụ (Tên DV)');
    else if (name.length > 255) errors.push('Tên DV tối đa 255 ký tự');

    const vendor = edit.vendor_id ? ctx.vendors.find((v) => v.id === Number(edit.vendor_id)) : null;
    const owner = edit.owner_id ? ctx.employees.find((e) => e.id === Number(edit.owner_id)) : null;
    const manager = edit.manager_id ? ctx.employees.find((e) => e.id === Number(edit.manager_id)) : null;

    const catScope = serviceGroupOptions(ctx.categories);
    let categoryId = edit.category_id ? Number(edit.category_id) : null;
    let categoryName = edit.category_name?.trim() || null;
    if (categoryId) {
        const catHit = catScope.find((c) => c.id === categoryId) ?? ctx.categories.find((c) => c.id === categoryId);
        if (catHit) {
            categoryId = catHit.id;
            categoryName = null;
        } else {
            categoryId = null;
        }
    }
    if (!categoryId && categoryName) {
        const byName = resolveByName(categoryName, catScope.length ? catScope : ctx.categories);
        if (byName.id) {
            categoryId = byName.id;
            categoryName = null;
        }
    }

    let payment = edit.payment_status || 'unpaid';
    if (!ctx.paymentOptions.some((o) => o.value === payment)) payment = mapAlias(payment, PAYMENT_ALIASES) || 'unpaid';

    let status = edit.status || 'active';
    if (!ctx.statusOptions.some((o) => o.value === status)) status = mapAlias(status, STATUS_ALIASES) || 'active';

    let billing = edit.billing_cycle || null;
    if (billing && !ctx.billingOptions.some((o) => o.value === billing)) billing = mapAlias(billing, BILLING_ALIASES) || null;

    let effective = edit.effective_date || null;
    if (effective && !/^\d{4}-\d{2}-\d{2}$/.test(effective)) effective = parseExcelDate(effective);
    if (edit.effective_date && !effective) errors.push('Ngày bắt đầu không hợp lệ');

    let expiry = edit.expiry_date || null;
    if (expiry && !/^\d{4}-\d{2}-\d{2}$/.test(expiry)) expiry = parseExcelDate(expiry);
    if (edit.expiry_date && !expiry) errors.push('Ngày hết hạn không hợp lệ');
    if (effective && expiry && expiry < effective) errors.push('Ngày hết hạn phải sau ngày bắt đầu');

    const annual = edit.annual_cost === '' || edit.annual_cost == null ? null : Number(edit.annual_cost);
    if (annual != null && (Number.isNaN(annual) || annual < 0)) errors.push('Chi phí năm không hợp lệ');
    const lifecycle = edit.lifecycle_cost === '' || edit.lifecycle_cost == null ? null : Number(edit.lifecycle_cost);
    if (lifecycle != null && (Number.isNaN(lifecycle) || lifecycle < 0)) errors.push('Chi phí vòng đời không hợp lệ');

    return {
        code: edit.code?.trim() || null,
        name,
        vendor_id: vendor?.id ?? null,
        vendor_name: vendor?.name ?? (edit.vendor_name || null),
        category_id: categoryId,
        category_name: categoryId ? null : categoryName,
        department_id: edit.department_id ? Number(edit.department_id) : null,
        using_unit: edit.using_unit?.trim() || null,
        owner_id: owner?.id ?? null,
        owner_name: owner?.name ?? (edit.owner_name || null),
        manager_id: manager?.id ?? null,
        manager_name: manager?.name ?? (edit.manager_name || null),
        billing_cycle: billing,
        annual_cost: annual,
        lifecycle_cost: lifecycle,
        payment_status: payment,
        effective_date: effective,
        expiry_date: expiry,
        status,
        description: edit.description?.trim() || null,
        links: Array.isArray(edit.links) ? edit.links : [],
        valid: errors.length === 0,
        errors,
    };
}

export function validateImportRows(rawRows, opts = {}) {
    const ctx = ctxOf(opts);
    return rawRows.map((raw) => {
        const vendorRes = resolveByName(raw.vendor_raw, ctx.vendors);
        const ownerRes = resolvePerson(raw.owner_raw, ctx.employees);
        const managerRes = resolvePerson(raw.manager_raw, ctx.employees);
        const catList = serviceGroupOptions(ctx.categories);
        const catRes = raw.category_raw ? resolveByName(raw.category_raw, catList) : { id: null };
        const deptRes = raw.using_unit ? resolveByName(raw.using_unit, ctx.departments) : { id: null };

        const links = String(raw.link_raw ?? '').split(/[\n;,]+/).map((s) => s.trim()).filter(Boolean);

        const edit = {
            code: raw.code ?? '',
            name: (raw.name || raw.service_name) ?? '',
            vendor_id: vendorRes.id,
            vendor_name: vendorRes.name ?? raw.vendor_raw ?? '',
            category_id: catRes.id,
            category_name: catRes.id ? null : (raw.category_raw || null),
            department_id: deptRes.id,
            using_unit: raw.using_unit ?? '',
            owner_id: ownerRes.id,
            owner_name: ownerRes.name ?? raw.owner_raw ?? '',
            manager_id: managerRes.id,
            manager_name: managerRes.name ?? raw.manager_raw ?? '',
            billing_cycle: raw.billing_raw ? (mapAlias(raw.billing_raw, BILLING_ALIASES) ?? raw.billing_raw) : null,
            annual_cost: parseNumber(raw.annual_cost_raw) ?? '',
            lifecycle_cost: parseNumber(raw.lifecycle_cost_raw) ?? '',
            payment_status: raw.payment_status_raw ? (mapAlias(raw.payment_status_raw, PAYMENT_ALIASES) ?? raw.payment_status_raw) : 'unpaid',
            effective_date: parseExcelDate(raw.effective_date_raw) ?? '',
            expiry_date: parseExcelDate(raw.expiry_date_raw) ?? '',
            status: raw.status_raw ? (mapAlias(raw.status_raw, STATUS_ALIASES) ?? raw.status_raw) : 'active',
            description: raw.notes_raw ?? '',
            links,
        };

        // Cảnh báo (không chặn): NCC/nhóm tự tạo khi nhập; owner/manager null nếu không khớp.
        const warnings = [];
        if (raw.vendor_raw && !vendorRes.id) warnings.push(`NCC "${raw.vendor_raw}" sẽ được tạo mới`);
        if (raw.category_raw && !catRes.id) warnings.push(`Nhóm DV "${raw.category_raw}" sẽ được tạo mới`);
        if (raw.owner_raw && !ownerRes.id) warnings.push(`Người phụ trách (${raw.owner_raw}) ${ownerRes.error ?? 'không khớp'} → bỏ trống`);
        if (raw.manager_raw && !managerRes.id) warnings.push(`Người quản lý (${raw.manager_raw}) ${managerRes.error ?? 'không khớp'} → bỏ trống`);

        const validated = validatePreviewEdit(edit, ctx);
        return {
            line: raw.line,
            edit,
            ...validated,
            warnings,
            errors: validated.errors,
            valid: validated.valid,
        };
    });
}

export function revalidatePreviewRow(row, opts = {}) {
    const validated = validatePreviewEdit(row.edit, ctxOf(opts));
    const warnings = row.warnings ?? [];
    Object.assign(row, validated, { warnings });
    return row;
}

export function createPreviewRows(validatedRows, opts = {}) {
    return validatedRows.map((r) => {
        const row = {
            line: r.line,
            edit: {
                code: r.edit?.code ?? '',
                name: r.edit?.name ?? '',
                vendor_id: r.edit?.vendor_id ?? null,
                vendor_name: r.edit?.vendor_name ?? '',
                category_id: r.edit?.category_id ?? null,
                category_name: r.edit?.category_name ?? null,
                department_id: r.edit?.department_id ?? null,
                using_unit: r.edit?.using_unit ?? '',
                owner_id: r.edit?.owner_id ?? null,
                owner_name: r.edit?.owner_name ?? '',
                manager_id: r.edit?.manager_id ?? null,
                manager_name: r.edit?.manager_name ?? '',
                billing_cycle: r.edit?.billing_cycle ?? null,
                annual_cost: r.edit?.annual_cost ?? '',
                lifecycle_cost: r.edit?.lifecycle_cost ?? '',
                payment_status: r.edit?.payment_status ?? 'unpaid',
                effective_date: r.edit?.effective_date ?? '',
                expiry_date: r.edit?.expiry_date ?? '',
                status: r.edit?.status ?? 'active',
                description: r.edit?.description ?? '',
                links: Array.isArray(r.edit?.links) ? r.edit.links : [],
            },
            warnings: r.warnings ?? [],
            valid: r.valid,
            errors: [...(r.errors ?? [])],
        };
        return revalidatePreviewRow(row, opts);
    });
}

export function rowsToPayload(validRows) {
    return validRows.map((r) => ({
        code: r.code || r.edit?.code?.trim() || null,
        name: r.name ?? r.edit?.name?.trim(),
        vendor_id: r.vendor_id,
        vendor_name: r.vendor_id ? null : (r.vendor_name && r.vendor_name !== '—' ? r.vendor_name : null),
        category_id: r.category_id,
        category_name: r.category_id ? null : r.category_name,
        department_id: r.department_id,
        using_unit: r.using_unit,
        owner_id: r.owner_id,
        manager_id: r.manager_id,
        billing_cycle: r.billing_cycle,
        annual_cost: r.annual_cost,
        lifecycle_cost: r.lifecycle_cost,
        payment_status: r.payment_status,
        effective_date: r.effective_date || null,
        expiry_date: r.expiry_date || null,
        status: r.status,
        description: r.description,
        links: r.links ?? [],
    }));
}

/** Lọc finances/reviews chỉ giữ dòng có code khớp với một hợp đồng sắp nhập. */
export function linkedFinances(finances, validRows) {
    const codes = new Set(validRows.map((r) => normalizeKey(r.code)).filter(Boolean));
    return (finances ?? []).filter((f) => codes.has(normalizeKey(f.code)));
}
export function linkedReviews(reviews, validRows) {
    const codes = new Set(validRows.map((r) => normalizeKey(r.code)).filter(Boolean));
    return (reviews ?? []).filter((rv) => codes.has(normalizeKey(rv.code)));
}
