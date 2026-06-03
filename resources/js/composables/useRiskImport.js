import XLSX from 'xlsx-js-style';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';
const AMBER_SOFT = 'FFFBEB';
const AMBER_TEXT = 'B45309';

export const IMPORT_HEADERS = [
    { key: 'title', label: 'Tiêu đề *', width: 36 },
    { key: 'severity', label: 'Mức độ *', width: 16 },
    { key: 'status', label: 'Trạng thái', width: 16 },
    { key: 'owner', label: 'Người phụ trách', width: 22 },
    { key: 'due_date', label: 'Hạn xử lý (DD/MM/YYYY)', width: 18 },
    { key: 'root_cause', label: 'Nguyên nhân', width: 28 },
    { key: 'resolution', label: 'Hướng xử lý', width: 28 },
    { key: 'description', label: 'Mô tả', width: 32 },
];

const SEVERITY_ALIASES = {
    low: ['low', 'thấp', 'thap'],
    medium: ['medium', 'trung bình', 'trung binh', 'tb'],
    high: ['high', 'cao'],
    critical: ['critical', 'nghiêm trọng', 'nghiem trong', 'critical'],
};

/** Marker ẩn trên sheet "Nhập liệu" — dòng tiêu đề cột ngay bên dưới. */
export const IMPORT_TEMPLATE_MARKER = 'VA_IMPORT_V1';

const STATUS_ALIASES = {
    open: ['open', 'đang mở', 'dang mo', 'mở', 'mo'],
    in_progress: ['in_progress', 'in progress', 'đang xử lý', 'dang xu ly', 'xử lý'],
    blocked: ['blocked', 'bị chặn', 'bi chan', 'chặn'],
    resolved: ['resolved', 'đã giải quyết', 'da giai quyet', 'giải quyết'],
    closed: ['closed', 'đã đóng', 'da dong', 'đóng'],
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
    title: {
        font: { bold: true, sz: 16, color: { rgb: BRAND } },
        alignment: { horizontal: 'left', vertical: 'center' },
    },
    subtitle: {
        font: { sz: 10, color: { rgb: SLATE_600 }, italic: true },
        alignment: { horizontal: 'left', wrapText: true },
    },
    section: {
        font: { bold: true, sz: 11, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'left', vertical: 'center' },
    },
    guide: {
        font: { sz: 10, color: { rgb: '334155' } },
        alignment: { vertical: 'top', wrapText: true },
        border: borderThin(),
    },
    guideBold: {
        font: { bold: true, sz: 10, color: { rgb: BRAND } },
        alignment: { vertical: 'top', wrapText: true },
    },
    header: {
        font: { bold: true, sz: 10, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    required: {
        font: { bold: true, sz: 10, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    sample: {
        font: { sz: 10, color: { rgb: SLATE_600 }, italic: true },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    note: {
        font: { sz: 9, color: { rgb: AMBER_TEXT } },
        fill: { fgColor: { rgb: AMBER_SOFT } },
        alignment: { vertical: 'center', wrapText: true },
    },
    input: {
        font: { sz: 10, color: { rgb: '1E293B' } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
};

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = { v: value ?? '', t: 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((wch) => ({ wch }));
}

function safeCode(code) {
    return String(code ?? 'DA').replace(/[:\\/?*[\]]/g, '_');
}

function fileStamp() {
    const t = new Date();
    const dd = String(t.getDate()).padStart(2, '0');
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const yyyy = t.getFullYear();
    return { dd, mm, yyyy, iso: t.toISOString().slice(0, 10) };
}

function normalizeKey(val) {
    return String(val ?? '')
        .trim()
        .toLowerCase()
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'd')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ');
}

function mapAlias(value, aliasMap, defaultValue = null) {
    const key = normalizeKey(value);
    if (!key) return defaultValue;
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
        const y = parsed.y;
        const m = String(parsed.m).padStart(2, '0');
        const d = String(parsed.d).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    const s = String(val).trim();
    const iso = s.match(/^(\d{4})-(\d{2})-(\d{2})/);
    if (iso) return `${iso[1]}-${iso[2]}-${iso[3]}`;
    const vn = s.match(/^(\d{1,2})[/.-](\d{1,2})[/.-](\d{4})/);
    if (vn) {
        const d = String(vn[1]).padStart(2, '0');
        const m = String(vn[2]).padStart(2, '0');
        return `${vn[3]}-${m}-${d}`;
    }
    const parsed = new Date(s);
    if (!Number.isNaN(parsed.getTime())) {
        const y = parsed.getFullYear();
        const m = String(parsed.getMonth() + 1).padStart(2, '0');
        const d = String(parsed.getDate()).padStart(2, '0');
        return `${y}-${m}-${d}`;
    }
    return null;
}

function resolveOwner(name, employees) {
    const key = normalizeKey(name);
    if (!key) return { owner_id: null };
    const exact = employees.find((e) => normalizeKey(e.name) === key);
    if (exact) return { owner_id: exact.id, owner_name: exact.name };
    const partial = employees.find((e) => normalizeKey(e.name).includes(key) || key.includes(normalizeKey(e.name)));
    if (partial) return { owner_id: partial.id, owner_name: partial.name };
    return { owner_id: null, owner_error: `Không tìm thấy nhân sự: "${name}"` };
}

function buildGuideSheet({ projectCode, projectName, severityOptions, statusOptions, employees }) {
    const ws = {};
    const COLS = 7;
    let row = 0;

    setCell(ws, row, 0, 'HƯỚNG DẪN NHẬP RỦI RO & VƯỚNG MẮC', S.title);
    mergeRow(ws, row, 0, COLS);
    row++;
    setCell(ws, row, 0, `Dự án: ${projectName || '—'} (${projectCode}) · Mẫu chuẩn VAschools`, S.subtitle);
    mergeRow(ws, row, 0, COLS);
    row += 2;

    const steps = [
        '1. Mở sheet "Nhập liệu" và điền dữ liệu từ dòng 8 trở đi (sau 2 dòng mẫu, không sửa dòng tiêu đề cột).',
        '2. Cột có dấu * là bắt buộc: Tiêu đề, Mức độ.',
        '3. Mức độ: low | medium | high | critical (hoặc Thấp, Trung bình, Cao, Nghiêm trọng).',
        '4. Trạng thái: để trống = Đang mở. Có thể dùng mã: open, in_progress, blocked, resolved, closed.',
        '5. Người phụ trách: ghi đúng họ tên như trong hệ thống (xem danh sách bên dưới).',
        '6. Hạn xử lý: định dạng DD/MM/YYYY (vd: 15/06/2026).',
        '7. Xóa các dòng mẫu (in nghiêng) trước khi tải lên. Không nhập quá 200 dòng/lần.',
        '8. Trên web: bấm Nhập → chọn file → xem trước → Xác nhận nhập.',
    ];
    steps.forEach((text) => {
        setCell(ws, row, 0, text, S.guide);
        mergeRow(ws, row, 0, COLS);
        row++;
    });

    row++;
    setCell(ws, row, 0, 'GIÁ TRỊ MỨC ĐỘ', S.section);
    mergeRow(ws, row, 0, 3);
    setCell(ws, row, 4, 'GIÁ TRỊ TRẠNG THÁI', S.section);
    mergeRow(ws, row, 4, COLS);
    row++;

    const severities = severityOptions?.length
        ? severityOptions
        : Object.entries(SEVERITY_ALIASES).map(([value, aliases]) => ({ value, label: aliases[0] }));
    severities.filter(Boolean).forEach((opt, i) => {
        setCell(ws, row + i, 0, opt?.label ?? opt?.value ?? '', S.guide);
        setCell(ws, row + i, 1, opt?.value ?? '', S.guideBold);
    });

    const statuses = statusOptions?.length
        ? statusOptions
        : Object.entries(STATUS_ALIASES).map(([value, aliases]) => ({ value, label: aliases[0] }));
    statuses.filter(Boolean).forEach((opt, i) => {
        setCell(ws, row + i, 4, opt?.label ?? opt?.value ?? '', S.guide);
        setCell(ws, row + i, 5, opt?.value ?? '', S.guideBold);
    });
    row += Math.max(severities.length, statuses.length) + 1;

    setCell(ws, row, 0, 'DANH SÁCH NHÂN SỰ (Người phụ trách)', S.section);
    mergeRow(ws, row, 0, COLS);
    row++;
    const staff = (employees || []).slice(0, 30);
    if (!staff.length) {
        setCell(ws, row, 0, '— Chưa có danh sách nhân sự trong dự án —', S.guide);
        mergeRow(ws, row, 0, COLS);
        row++;
    } else {
        staff.forEach((e, i) => {
            const col = (i % 4) * 2;
            const r = row + Math.floor(i / 4);
            setCell(ws, r, col, e.name, S.guide);
        });
        row += Math.ceil(staff.length / 4) + 1;
    }

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: row, c: COLS } });
    setColWidths(ws, [18, 14, 14, 14, 18, 14, 14, 14]);
    ws['!rows'] = [{ hpt: 28 }, { hpt: 18 }];
    return ws;
}

function buildDataSheet({ projectCode, projectName }) {
    const ws = {};
    const COLS = IMPORT_HEADERS.length - 1;

    setCell(ws, 0, 0, `NHẬP RỦI RO & VƯỚNG MẮC — ${projectName || projectCode}`, S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, `Mã dự án: ${projectCode} · Điền từ dòng 8 (sau 2 dòng mẫu) · Xóa dòng mẫu trước khi import`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);
    setCell(ws, 2, 0, '⚠ Không đổi tên cột. Cột * là bắt buộc.', S.note);
    mergeRow(ws, 2, 0, COLS);
    setCell(ws, 3, 0, IMPORT_TEMPLATE_MARKER, S.subtitle);
    mergeRow(ws, 3, 0, COLS);

    const headerRow = 4;
    IMPORT_HEADERS.forEach((h, c) => {
        setCell(ws, headerRow, c, h.label, h.key === 'title' || h.key === 'severity' ? S.required : S.header);
    });

    const samples = [
        ['Thiếu tài liệu phê duyệt từ phòng ban', 'high', 'open', '', '30/06/2026', 'Chưa có quy trình ký', 'Họp với PM phòng ban', 'Ảnh hưởng tiến độ sprint'],
        ['Lỗi tích hợp API thanh toán', 'critical', 'in_progress', '', '15/06/2026', 'API sandbox thay đổi', 'Cập nhật SDK và test lại', ''],
    ];
    samples.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        row.forEach((val, c) => setCell(ws, r, c, val, S.sample));
    });

    const inputStart = headerRow + 1 + samples.length;
    for (let r = inputStart; r < inputStart + 50; r++) {
        IMPORT_HEADERS.forEach((_, c) => setCell(ws, r, c, '', S.input));
    }

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: inputStart + 49, c: COLS },
    });
    setColWidths(ws, IMPORT_HEADERS.map((h) => h.width));
    ws['!rows'] = [{ hpt: 26 }, { hpt: 16 }, { hpt: 20 }];
    return ws;
}

/**
 * Tải file mẫu Excel (.xlsx) có sheet hướng dẫn + nhập liệu.
 */
export function downloadRiskImportTemplate({
    projectCode = 'DA',
    projectName = '',
    severityOptions = [],
    statusOptions = [],
    employees = [],
}) {
    const code = safeCode(projectCode);
    const wb = XLSX.utils.book_new();
    const meta = { projectCode: code, projectName, severityOptions, statusOptions, employees };

    XLSX.utils.book_append_sheet(wb, buildGuideSheet(meta), 'Huong dan');
    XLSX.utils.book_append_sheet(wb, buildDataSheet(meta), 'Nhap lieu');

    XLSX.writeFile(wb, `VA_MauNhap_RuiRo_${code}.xlsx`);
}

function cellText(cell) {
    if (!cell) return '';
    if (cell.w != null && cell.w !== '') return String(cell.w);
    if (cell.v != null) return String(cell.v);
    return '';
}

/** Đọc toàn bộ ô theo !ref (ổn định hơn sheet_to_json với merge/style). */
function readSheetMatrix(sheet) {
    if (!sheet?.['!ref']) return [];
    const range = XLSX.utils.decode_range(sheet['!ref']);
    const rows = [];
    for (let R = range.s.r; R <= range.e.r; R++) {
        const row = [];
        for (let C = range.s.c; C <= range.e.c; C++) {
            row.push(cellText(sheet[XLSX.utils.encode_cell({ r: R, c: C })]));
        }
        rows.push(row);
    }
    return rows;
}

function pickImportSheet(wb) {
    const names = wb.SheetNames ?? [];
    const norm = (n) => normalizeKey(n).replace(/\s/g, '');

    return (
        names.find((n) => norm(n) === 'nhaplieu')
        ?? names.find((n) => /nhap|nhập/i.test(n) && /lieu|liệu/i.test(n) && !/huong|huớng|guide/i.test(n))
        ?? names.find((n) => /nhap|nhập/i.test(n) && !/huong|huớng|guide|dan$/i.test(n))
        ?? (names.length > 1 ? names[names.length - 1] : null)
        ?? names[0]
    );
}

/** Header thật có "Tiêu đề" và "Mức độ" ở hai cột khác nhau (tránh nhầm dòng hướng dẫn gộp ô). */
function isHeaderRow(row) {
    if (!Array.isArray(row)) return false;
    let titleIdx = -1;
    let severityIdx = -1;
    row.forEach((cell, idx) => {
        const k = normalizeKey(cell).replace(/\*/g, '').trim();
        if (k.includes('tieu de') || k === 'title') titleIdx = idx;
        if (k.includes('muc do') || k.includes('severity')) severityIdx = idx;
    });
    return titleIdx >= 0 && severityIdx >= 0 && titleIdx !== severityIdx;
}

function findHeaderIndex(rows) {
    for (let i = 0; i < Math.min(rows.length, 40); i++) {
        const row = rows[i];
        if (!Array.isArray(row)) continue;
        if (row.some((c) => String(c).trim() === IMPORT_TEMPLATE_MARKER)) {
            return i + 1;
        }
    }
    for (let i = 0; i < Math.min(rows.length, 40); i++) {
        if (isHeaderRow(rows[i])) return i;
    }
    if (isHeaderRow(rows[4])) return 4;
    return -1;
}

function sheetRows(wb) {
    const sheetName = pickImportSheet(wb);
    const sheet = wb.Sheets[sheetName];
    return readSheetMatrix(sheet);
}

function columnIndexMap(headerRow) {
    const map = {};
    headerRow.forEach((cell, idx) => {
        const key = normalizeKey(cell).replace(/\*/g, '').trim();
        if (key.includes('tieu de') || key === 'title') map.title = idx;
        else if (key.includes('muc do') || key.includes('severity')) map.severity = idx;
        else if (key.includes('trang thai') || key.includes('status')) map.status = idx;
        else if (key.includes('nguoi phu trach') || key.includes('owner')) map.owner = idx;
        else if (key.includes('han xu ly') || key.includes('due')) map.due_date = idx;
        else if (key.includes('nguyen nhan') || key.includes('root')) map.root_cause = idx;
        else if (key.includes('huong xu ly') || key.includes('resolution')) map.resolution = idx;
        else if (key.includes('mo ta') || key.includes('description')) map.description = idx;
    });
    return map;
}

function cellVal(row, idx) {
    if (idx == null || idx < 0) return '';
    const v = row[idx];
    return v == null ? '' : String(v).trim();
}

/**
 * @returns {Promise<{ rows: object[], errors: string[] }>}
 */
export async function parseRiskImportFile(file) {
    const buf = await file.arrayBuffer();
    const wb = XLSX.read(buf, { type: 'array', cellDates: true });
    const matrix = sheetRows(wb);
    const headerIdx = findHeaderIndex(matrix);

    if (headerIdx < 0) {
        return {
            rows: [],
            errors: ['Không tìm thấy dòng tiêu đề (cột "Tiêu đề"). Hãy dùng file mẫu VA.'],
        };
    }

    const colMap = columnIndexMap(matrix[headerIdx]);
    if (colMap.title == null || colMap.severity == null) {
        return {
            rows: [],
            errors: ['File thiếu cột "Tiêu đề" hoặc "Mức độ". Tải lại file mẫu.'],
        };
    }

    const rows = [];
    const errors = [];

    for (let i = headerIdx + 1; i < matrix.length; i++) {
        const row = matrix[i];
        if (!Array.isArray(row)) continue;

        const title = cellVal(row, colMap.title);
        const severityRaw = cellVal(row, colMap.severity);
        const statusRaw = cellVal(row, colMap.status);
        const ownerRaw = cellVal(row, colMap.owner);
        const dueRaw = cellVal(row, colMap.due_date);
        const rootCause = cellVal(row, colMap.root_cause);
        const resolution = cellVal(row, colMap.resolution);
        const description = cellVal(row, colMap.description);

        const allEmpty = !title && !severityRaw && !statusRaw && !ownerRaw && !dueRaw;
        if (allEmpty) continue;

        const SAMPLE_TITLES = new Set([
            normalizeKey('Thiếu tài liệu phê duyệt từ phòng ban'),
            normalizeKey('Lỗi tích hợp API thanh toán'),
        ]);
        if (SAMPLE_TITLES.has(normalizeKey(title))) continue;

        rows.push({
            line: i + 1,
            title,
            severity_raw: severityRaw,
            status_raw: statusRaw,
            owner_raw: ownerRaw,
            due_date_raw: dueRaw,
            root_cause: rootCause || null,
            resolution: resolution || null,
            description: description || null,
        });
    }

    if (!rows.length) {
        errors.push(
            'Chưa có dòng dữ liệu để nhập. File mẫu chỉ có 2 dòng ví dụ (sẽ bỏ qua) — hãy thêm ít nhất một dòng mới từ dòng 8 trong sheet "Nhập liệu", hoặc sửa/xóa dòng mẫu rồi nhập lại.',
        );
    }

    return { rows, errors };
}

function validationContext({ employees = [], severityOptions = [], statusOptions = [] } = {}) {
    return {
        employees,
        severityOptions,
        statusOptions,
        allowedSeverity: new Set(
            (severityOptions.length ? severityOptions : Object.keys(SEVERITY_ALIASES)).map((o) =>
                (typeof o === 'string' ? o : o.value),
            ),
        ),
        allowedStatus: new Set(
            (statusOptions.length ? statusOptions : Object.keys(STATUS_ALIASES)).map((o) =>
                (typeof o === 'string' ? o : o.value),
            ),
        ),
    };
}

/**
 * @param {object} edit
 * @param {{ employees, severityOptions, statusOptions, allowedSeverity, allowedStatus }} ctx
 */
export function validatePreviewEdit(edit, ctx) {
    const errors = [];
    const title = edit.title?.trim() ?? '';

    if (!title) errors.push('Thiếu tiêu đề');
    else if (title.length > 255) errors.push('Tiêu đề tối đa 255 ký tự');

    let severity = edit.severity;
    if (severity && !ctx.allowedSeverity.has(severity)) {
        severity = mapAlias(severity, SEVERITY_ALIASES);
    }
    if (!severity) errors.push('Chọn mức độ hợp lệ');
    else if (!ctx.allowedSeverity.has(severity)) errors.push(`Mức độ "${edit.severity}" không được phép`);

    let status = edit.status || 'open';
    if (status && !ctx.allowedStatus.has(status)) {
        status = mapAlias(status, STATUS_ALIASES);
    }
    if (!status) errors.push('Chọn trạng thái hợp lệ');
    else if (!ctx.allowedStatus.has(status)) errors.push(`Trạng thái không hợp lệ`);

    let due_date = edit.due_date || null;
    if (due_date && !/^\d{4}-\d{2}-\d{2}$/.test(due_date)) {
        due_date = parseExcelDate(due_date);
    }
    if (edit.due_date && !due_date) errors.push('Ngày hạn không hợp lệ (YYYY-MM-DD hoặc DD/MM/YYYY)');

    const owner_id = edit.owner_id !== '' && edit.owner_id != null ? Number(edit.owner_id) : null;
    const owner = ctx.employees.find((e) => e.id === owner_id);
    const owner_name = owner?.name ?? (edit.owner_name?.trim() || '—');

    const severity_label = ctx.severityOptions.find((o) => o.value === severity)?.label ?? severity;
    const status_label = ctx.statusOptions.find((o) => o.value === status)?.label ?? status;

    return {
        title,
        severity,
        severity_label,
        status,
        status_label,
        owner_id,
        owner_name,
        due_date,
        due_date_display: due_date ? due_date.split('-').reverse().join('/') : '—',
        root_cause: edit.root_cause?.trim() || null,
        resolution: edit.resolution?.trim() || null,
        description: edit.description?.trim() || null,
        valid: errors.length === 0,
        errors,
    };
}

/**
 * @param {object[]} rawRows
 * @param {{ employees: object[], severityOptions: object[], statusOptions: object[] }} opts
 */
export function validateImportRows(rawRows, opts = {}) {
    const ctx = validationContext(opts);

    return rawRows.map((raw) => {
        const severity = mapAlias(raw.severity_raw, SEVERITY_ALIASES) ?? '';
        const status = raw.status_raw ? (mapAlias(raw.status_raw, STATUS_ALIASES) ?? '') : 'open';
        const due_date = parseExcelDate(raw.due_date_raw);
        const ownerRes = resolveOwner(raw.owner_raw, ctx.employees);

        const edit = {
            title: raw.title ?? '',
            severity,
            status: status || 'open',
            owner_id: ownerRes.owner_id,
            owner_name: ownerRes.owner_name ?? raw.owner_raw ?? '',
            due_date: due_date ?? '',
            root_cause: raw.root_cause ?? '',
            resolution: raw.resolution ?? '',
            description: raw.description ?? '',
        };

        const extraErrors = [];
        if (raw.severity_raw && !severity) extraErrors.push(`Mức độ không hợp lệ: "${raw.severity_raw}"`);
        if (raw.status_raw && !mapAlias(raw.status_raw, STATUS_ALIASES)) {
            extraErrors.push(`Trạng thái không hợp lệ: "${raw.status_raw}"`);
        }
        if (raw.due_date_raw && !due_date) extraErrors.push(`Ngày hạn không hợp lệ: "${raw.due_date_raw}"`);
        if (ownerRes.owner_error) extraErrors.push(ownerRes.owner_error);

        const validated = validatePreviewEdit(edit, ctx);
        return {
            line: raw.line,
            edit: { ...edit, severity: validated.severity ?? edit.severity, status: validated.status ?? edit.status },
            ...validated,
            errors: [...extraErrors, ...validated.errors],
            valid: extraErrors.length === 0 && validated.valid,
        };
    });
}

/** @param {object} row — preview row with .edit */
export function revalidatePreviewRow(row, opts = {}) {
    const ctx = validationContext(opts);
    const validated = validatePreviewEdit(row.edit, ctx);
    Object.assign(row, validated);
    row.edit.severity = validated.severity ?? row.edit.severity;
    row.edit.status = validated.status ?? row.edit.status;
    row.edit.owner_id = validated.owner_id;
    return row;
}

export function createPreviewRows(validatedRows, opts = {}) {
    return validatedRows.map((r) => {
        const row = {
            line: r.line,
            edit: {
                title: r.edit?.title ?? r.title ?? '',
                severity: r.edit?.severity ?? r.severity ?? '',
                status: r.edit?.status ?? r.status ?? 'open',
                owner_id: r.edit?.owner_id ?? r.owner_id ?? null,
                owner_name: r.edit?.owner_name ?? r.owner_name ?? '',
                due_date: r.edit?.due_date ?? r.due_date ?? '',
                root_cause: r.edit?.root_cause ?? r.root_cause ?? '',
                resolution: r.edit?.resolution ?? r.resolution ?? '',
                description: r.edit?.description ?? r.description ?? '',
            },
            valid: r.valid,
            errors: [...(r.errors ?? [])],
        };
        return revalidatePreviewRow(row, opts);
    });
}

export function rowsToPayload(validRows) {
    return validRows.map((r) => ({
        title: r.edit?.title?.trim() ?? r.title,
        severity: r.severity,
        status: r.status,
        owner_id: r.owner_id,
        due_date: r.due_date || null,
        root_cause: r.root_cause,
        resolution: r.resolution,
        description: r.description,
    }));
}

function setCellSimple(ws, r, c, value, style) {
    setCell(ws, r, c, value, style);
}

/**
 * Xuất dòng xem trước (hợp lệ hoặc lỗi) ra Excel để sửa lại.
 * @param {'valid'|'errors'} mode
 */
export function exportPreviewRows(rows, { projectCode = 'DA', projectName = '', mode = 'errors' } = {}) {
    const ws = {};
    const headers = [...IMPORT_HEADERS.map((h) => h.label), mode === 'errors' ? 'Lỗi' : 'Ghi chú'];
    const COLS = headers.length - 1;

    setCell(ws, 0, 0, mode === 'errors' ? 'DÒNG LỖI — SỬA VÀ TẢI LẠI' : 'DÒNG HỢP LỆ', S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, `Dự án: ${projectName || projectCode}`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);

    headers.forEach((h, c) => setCellSimple(ws, 3, c, h, S.header));

    rows.forEach((row, idx) => {
        const r = 4 + idx;
        const e = row.edit ?? row;
        const vals = [
            e.title ?? '',
            row.severity_label ?? e.severity ?? '',
            row.status_label ?? e.status ?? '',
            row.owner_name ?? '—',
            row.due_date_display ?? (e.due_date ? e.due_date.split('-').reverse().join('/') : ''),
            e.root_cause ?? '',
            e.resolution ?? '',
            e.description ?? '',
            mode === 'errors' ? (row.errors ?? []).join('; ') : 'OK',
        ];
        const style = idx % 2 === 0 ? S.cell : S.cellAlt;
        vals.forEach((v, c) => setCellSimple(ws, r, c, v, style));
    });

    const totalRow = 4 + rows.length;
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: COLS } });
    setColWidths(ws, [...IMPORT_HEADERS.map((h) => h.width), 36]);

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, mode === 'errors' ? 'Loi' : 'Hop le');
    const code = safeCode(projectCode);
    const t = fileStamp();
    XLSX.writeFile(wb, `VA_RuiRo_${mode === 'errors' ? 'Loi' : 'HopLe'}_${code}_${t.dd}${t.mm}${t.yyyy}.xlsx`);
}
