import XLSX from 'xlsx-js-style';
import { normalizeSearchKey } from '@/shared/utils/normalizeSearchKey';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';
const AMBER_SOFT = 'FFFBEB';
const AMBER_TEXT = 'B45309';

export const IMPORT_MIN_LEVELS = 2;
export const IMPORT_MAX_LEVELS = 5;

/** Marker ẩn trên sheet "Nhap lieu" — dòng ngay trên header. */
export const IMPORT_TEMPLATE_MARKER = 'VA_EVAL_IMPORT_V1';

export const IMPORT_BASE_HEADERS = [
    { key: 'department', label: 'Phòng ban (để trống = chung)', width: 22 },
    { key: 'criteria_name', label: 'Tên tiêu chí *', width: 34 },
    { key: 'criteria_code', label: 'Mã tiêu chí (để trống = tự sinh)', width: 20 },
    { key: 'category', label: 'Loại tiêu chí *', width: 20 },
    { key: 'description', label: 'Mô tả', width: 30 },
    { key: 'allow_half_score', label: 'Chấm 0.5 (Có/Không)', width: 14 },
];

export const IMPORT_STATUS_HEADER = { key: 'status', label: 'Trạng thái (Hoạt động/Ngưng)', width: 18 };

/** @returns {Array<{key:string,label:string,width:number,level:number,field:'label'|'description'|'weight'}>} */
export function levelHeaders(max = IMPORT_MAX_LEVELS) {
    const out = [];
    for (let n = 1; n <= max; n++) {
        const required = n <= IMPORT_MIN_LEVELS;
        out.push({ key: `level_${n}_label`, label: `Mức ${n} - Nhãn${required ? ' *' : ''}`, width: 20, level: n, field: 'label' });
        out.push({ key: `level_${n}_desc`, label: `Mức ${n} - Mô tả`, width: 24, level: n, field: 'description' });
        out.push({ key: `level_${n}_weight`, label: `Mức ${n} - Điểm${required ? ' *' : ''}`, width: 12, level: n, field: 'weight' });
    }
    return out;
}

export function allImportHeaders() {
    return [...IMPORT_BASE_HEADERS, ...levelHeaders(), IMPORT_STATUS_HEADER];
}

const BOOL_TRUE_ALIASES = ['co', 'true', '1', 'x', 'yes'];
const BOOL_FALSE_ALIASES = ['khong', 'false', '0', 'no', ''];
const STATUS_INACTIVE_ALIASES = ['ngung', 'ngung hoat dong', 'inactive', 'khoa'];

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

function fileStamp() {
    const t = new Date();
    const dd = String(t.getDate()).padStart(2, '0');
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const yyyy = t.getFullYear();
    return `${dd}${mm}${yyyy}`;
}

function mapAlias(value, aliases) {
    const key = normalizeSearchKey(value);
    return aliases.includes(key);
}

/**
 * Build sheet "Nhap lieu" duy nhất — hướng dẫn nằm ở modal UI, không ở sheet.
 * @param {{ departments: Array<{code:string,name:string}>, categories: string[], defaultScoreLevels: Array }} meta
 */
function buildDataSheet({ departments = [], categories = [], defaultScoreLevels = [] }) {
    const ws = {};
    const headers = allImportHeaders();
    const COLS = headers.length - 1;

    setCell(ws, 0, 0, 'NHẬP TIÊU CHÍ ĐÁNH GIÁ — VA-Workspace', S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, 'Xem hướng dẫn chi tiết trên màn hình Nhập của hệ thống. Điền dữ liệu từ dòng 8 (sau 2 dòng mẫu).', S.subtitle);
    mergeRow(ws, 1, 0, COLS);
    setCell(ws, 2, 0, `⚠ Không đổi tên cột. Cột có (*) là bắt buộc. Mức 3–${IMPORT_MAX_LEVELS} để trống nếu không dùng. Tối đa 200 dòng/lần.`, S.note);
    mergeRow(ws, 2, 0, COLS);
    setCell(ws, 3, 0, IMPORT_TEMPLATE_MARKER, S.subtitle);
    mergeRow(ws, 3, 0, COLS);

    const headerRow = 4;
    headers.forEach((h, c) => {
        const isRequired = h.label.includes('*');
        setCell(ws, headerRow, c, h.label, isRequired ? S.required : S.header);
    });

    const sampleLevels = (defaultScoreLevels.length ? defaultScoreLevels : [
        { label: 'Không đáp ứng', description: '', weight: 1 },
        { label: 'Đạt yêu cầu', description: '', weight: 3 },
        { label: 'Rất tốt', description: '', weight: 5 },
    ]).slice(0, IMPORT_MAX_LEVELS);

    function levelCells(levels) {
        const cells = [];
        for (let n = 1; n <= IMPORT_MAX_LEVELS; n++) {
            const level = levels[n - 1];
            cells.push(level?.label ?? '', level?.description ?? '', level?.weight ?? '');
        }
        return cells;
    }

    const deptSample = departments[0]?.name ?? '';
    const categorySample = categories[0] ?? 'Thái độ';

    const samples = [
        ['', 'Thái độ hợp tác/tinh thần tập thể', '', categorySample, 'Mức độ phối hợp trong công việc nhóm', 'Không', ...levelCells(sampleLevels), 'Hoạt động'],
        [deptSample, 'Chất lượng bàn giao công việc', '', categorySample, '', 'Có', ...levelCells([
            { label: 'Không đạt', description: '', weight: -1 },
            { label: 'Đạt', description: '', weight: 0.5 },
        ]), 'Hoạt động'],
    ];
    samples.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        row.forEach((val, c) => setCell(ws, r, c, val, S.sample));
    });

    const inputStart = headerRow + 1 + samples.length;
    for (let r = inputStart; r < inputStart + 50; r++) {
        headers.forEach((_, c) => setCell(ws, r, c, '', S.input));
    }

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: inputStart + 49, c: COLS },
    });
    setColWidths(ws, headers.map((h) => h.width));
    ws['!rows'] = [{ hpt: 28 }, { hpt: 18 }, { hpt: 16 }];
    return ws;
}

function buildReferenceSheet({ departments = [], categories = [] }) {
    const ws = {};
    const COLS = 3;

    setCell(ws, 0, 0, 'THAM CHIẾU — Phòng ban & Loại tiêu chí', S.section);
    mergeRow(ws, 0, 0, COLS);

    setCell(ws, 2, 0, 'Mã phòng ban', S.header);
    setCell(ws, 2, 1, 'Tên phòng ban', S.header);
    setCell(ws, 2, 2, '', S.header);
    setCell(ws, 2, 3, 'Loại tiêu chí hiện có', S.header);

    const maxRows = Math.max(departments.length, categories.length, 1);
    for (let i = 0; i < maxRows; i++) {
        const dept = departments[i];
        const r = 3 + i;
        setCell(ws, r, 0, dept?.code ?? '', S.sample);
        setCell(ws, r, 1, dept?.name ?? '', S.sample);
        setCell(ws, r, 2, '', S.sample);
        setCell(ws, r, 3, categories[i] ?? '', S.sample);
    }

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 3 + maxRows, c: COLS } });
    setColWidths(ws, [16, 28, 3, 24]);
    return ws;
}

/**
 * Tải file mẫu Excel (.xlsx) — 1 sheet nhập liệu + 1 sheet tham chiếu (không có sheet hướng dẫn).
 */
export function downloadEvaluationImportTemplate({ departments = [], categories = [], defaultScoreLevels = [] } = {}) {
    const wb = XLSX.utils.book_new();
    const meta = { departments, categories, defaultScoreLevels };

    XLSX.utils.book_append_sheet(wb, buildDataSheet(meta), 'Nhap lieu');
    XLSX.utils.book_append_sheet(wb, buildReferenceSheet(meta), 'Tham chieu');

    XLSX.writeFile(wb, `VA_MauNhap_TieuChiDanhGia_${fileStamp()}.xlsx`);
}

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
    const norm = (n) => normalizeSearchKey(n).replace(/\s/g, '');
    return (
        names.find((n) => norm(n) === 'nhaplieu')
        ?? names.find((n) => /nhap|nhập/i.test(n) && !/tham|chiếu|chieu/i.test(n))
        ?? names[0]
    );
}

function isHeaderRow(row) {
    if (!Array.isArray(row)) return false;
    let nameIdx = -1;
    let categoryIdx = -1;
    row.forEach((cell, idx) => {
        const k = normalizeSearchKey(cell).replace(/\*/g, '').trim();
        if (k.includes('ten tieu chi')) nameIdx = idx;
        if (k.includes('loai tieu chi')) categoryIdx = idx;
    });
    return nameIdx >= 0 && categoryIdx >= 0 && nameIdx !== categoryIdx;
}

function findHeaderIndex(rows) {
    for (let i = 0; i < Math.min(rows.length, 40); i++) {
        if (rows[i]?.some?.((c) => String(c).trim() === IMPORT_TEMPLATE_MARKER)) {
            return i + 1;
        }
    }
    for (let i = 0; i < Math.min(rows.length, 40); i++) {
        if (isHeaderRow(rows[i])) return i;
    }
    return isHeaderRow(rows[4]) ? 4 : -1;
}

function columnIndexMap(headerRow) {
    const map = { levels: {} };
    headerRow.forEach((cell, idx) => {
        const key = normalizeSearchKey(cell).replace(/\*/g, '').trim();
        const levelMatch = key.match(/^muc (\d+) - (nhan|mo ta|diem)$/);
        if (levelMatch) {
            const n = Number(levelMatch[1]);
            const field = { nhan: 'label', 'mo ta': 'description', diem: 'weight' }[levelMatch[2]];
            map.levels[n] = map.levels[n] ?? {};
            map.levels[n][field] = idx;
            return;
        }
        if (key.includes('phong ban')) map.department = idx;
        else if (key.includes('ten tieu chi')) map.criteria_name = idx;
        else if (key.includes('ma tieu chi')) map.criteria_code = idx;
        else if (key.includes('loai tieu chi')) map.category = idx;
        else if (key.includes('mo ta') && !key.includes('muc')) map.description = idx;
        else if (key.includes('cham 0.5') || key.includes('cham 05')) map.allow_half_score = idx;
        else if (key.includes('trang thai')) map.status = idx;
    });
    return map;
}

function cellVal(row, idx) {
    if (idx == null || idx < 0) return '';
    const v = row[idx];
    return v == null ? '' : String(v).trim();
}

const SAMPLE_NAMES = new Set([
    normalizeSearchKey('Thái độ hợp tác/tinh thần tập thể'),
    normalizeSearchKey('Chất lượng bàn giao công việc'),
]);

/**
 * @returns {Promise<{ rows: object[], errors: string[] }>}
 */
export async function parseEvaluationImportFile(file) {
    const buf = await file.arrayBuffer();
    const wb = XLSX.read(buf, { type: 'array', cellDates: true });
    const sheetName = pickImportSheet(wb);
    const matrix = readSheetMatrix(wb.Sheets[sheetName]);
    const headerIdx = findHeaderIndex(matrix);

    if (headerIdx < 0) {
        return { rows: [], errors: ['Không tìm thấy dòng tiêu đề (cột "Tên tiêu chí"/"Loại tiêu chí"). Hãy dùng file mẫu VA.'] };
    }

    const colMap = columnIndexMap(matrix[headerIdx]);
    if (colMap.criteria_name == null || colMap.category == null) {
        return { rows: [], errors: ['File thiếu cột "Tên tiêu chí" hoặc "Loại tiêu chí". Tải lại file mẫu.'] };
    }

    const rows = [];
    const errors = [];

    for (let i = headerIdx + 1; i < matrix.length; i++) {
        const row = matrix[i];
        if (!Array.isArray(row)) continue;

        const criteria_name = cellVal(row, colMap.criteria_name);
        const department_raw = cellVal(row, colMap.department);
        const criteria_code_raw = cellVal(row, colMap.criteria_code);
        const category = cellVal(row, colMap.category);
        const description = cellVal(row, colMap.description);
        const allow_half_score_raw = cellVal(row, colMap.allow_half_score);
        const status_raw = cellVal(row, colMap.status);

        const allEmpty = !criteria_name && !department_raw && !category;
        if (allEmpty) continue;
        if (SAMPLE_NAMES.has(normalizeSearchKey(criteria_name))) continue;

        const levels_raw = [];
        for (let n = 1; n <= IMPORT_MAX_LEVELS; n++) {
            const idxs = colMap.levels[n];
            if (!idxs) continue;
            levels_raw.push({
                label: cellVal(row, idxs.label),
                description: cellVal(row, idxs.description),
                weight: cellVal(row, idxs.weight),
            });
        }

        rows.push({
            line: i + 1,
            criteria_name,
            department_raw,
            criteria_code_raw,
            category,
            description: description || null,
            allow_half_score_raw,
            status_raw,
            levels_raw,
        });
    }

    if (!rows.length) {
        errors.push('Chưa có dòng dữ liệu để nhập. Hãy thêm ít nhất một dòng mới từ dòng 8 trong sheet "Nhap lieu".');
    }

    return { rows, errors };
}

function resolveDepartment(codeOrName, departments) {
    const raw = String(codeOrName ?? '').trim();
    if (!raw) return { department_code: null, department_name: null, scope: 'general' };

    const key = normalizeSearchKey(raw);
    const byCode = departments.find((d) => normalizeSearchKey(d.code) === key);
    if (byCode) return { department_code: byCode.code, department_name: byCode.name, scope: 'department' };

    const byName = departments.find((d) => normalizeSearchKey(d.name) === key);
    if (byName) return { department_code: byName.code, department_name: byName.name, scope: 'department' };

    const partial = departments.find((d) => normalizeSearchKey(d.name).includes(key) || key.includes(normalizeSearchKey(d.name)));
    if (partial) return { department_code: partial.code, department_name: partial.name, scope: 'department' };

    return { department_code: null, department_name: null, scope: 'department', department_error: `Không tìm thấy phòng ban: "${raw}"` };
}

function parseBool(raw, defaultValue = false) {
    const key = normalizeSearchKey(raw);
    if (key === '') return defaultValue;
    if (mapAlias(key, BOOL_TRUE_ALIASES)) return true;
    if (mapAlias(key, BOOL_FALSE_ALIASES)) return false;
    return defaultValue;
}

function validationContext({ departments = [], categories = [], canCreateGeneral = false } = {}) {
    return { departments, categories, canCreateGeneral };
}

/**
 * @param {object} edit
 * @param {{ departments: object[], categories: string[], canCreateGeneral: boolean }} ctx
 */
export function validatePreviewEdit(edit, ctx) {
    const errors = [];

    const criteria_name = edit.criteria_name?.trim() ?? '';
    if (!criteria_name) errors.push('Thiếu tên tiêu chí');
    else if (criteria_name.length > 255) errors.push('Tên tiêu chí tối đa 255 ký tự');

    const category = edit.category?.trim() ?? '';
    if (!category) errors.push('Thiếu loại tiêu chí');
    else if (category.length > 100) errors.push('Loại tiêu chí tối đa 100 ký tự');

    let department_code = edit.department_code ?? null;
    let department_name = edit.department_name ?? null;
    let scope = 'general';
    if (edit.department_raw && String(edit.department_raw).trim()) {
        const resolved = resolveDepartment(edit.department_raw, ctx.departments);
        department_code = resolved.department_code;
        department_name = resolved.department_name;
        scope = resolved.department_code ? 'department' : 'department';
        if (resolved.department_error) errors.push(resolved.department_error);
    } else {
        scope = 'general';
        department_code = null;
        department_name = null;
        if (!ctx.canCreateGeneral) {
            errors.push('Chỉ siêu quản trị mới tạo được tiêu chí chung — vui lòng chọn phòng ban.');
        }
    }

    let criteria_code = String(edit.criteria_code ?? '').trim().toUpperCase();
    if (criteria_code && !/^[A-Z][A-Z0-9]*$/.test(criteria_code)) {
        errors.push('Mã tiêu chí chỉ gồm chữ cái và số, bắt đầu bằng chữ (vd. TCVA001)');
    }

    const allow_half_score = !!edit.allow_half_score;

    const levels = (edit.levels ?? [])
        .map((l) => ({
            code: l.code || null,
            label: (l.label ?? '').trim(),
            description: (l.description ?? '').trim() || null,
            weight: l.weight === '' || l.weight == null ? null : Number(l.weight),
        }))
        .filter((l) => l.label !== '');

    if (levels.length < IMPORT_MIN_LEVELS) {
        errors.push(`Cần ít nhất ${IMPORT_MIN_LEVELS} mức có nhãn`);
    }
    levels.forEach((l, i) => {
        if (l.weight == null || Number.isNaN(l.weight)) {
            errors.push(`Mức ${i + 1}: thiếu trọng số`);
        } else if (!allow_half_score && Math.abs(l.weight % 1) > 1e-9) {
            errors.push(`Mức ${i + 1}: trọng số phải là số nguyên khi chưa bật Chấm 0.5`);
        }
    });

    const status = edit.status ?? 'active';

    return {
        criteria_name,
        category,
        description: edit.description?.trim() || null,
        department_code,
        department_name,
        scope,
        criteria_code: criteria_code || null,
        allow_half_score,
        levels,
        is_active: status !== 'inactive',
        valid: errors.length === 0,
        errors,
    };
}

/**
 * @param {object[]} rawRows
 * @param {{ departments: object[], categories: string[], canCreateGeneral: boolean }} opts
 */
export function validateImportRows(rawRows, opts = {}) {
    const ctx = validationContext(opts);

    return rawRows.map((raw) => {
        const allow_half_score = parseBool(raw.allow_half_score_raw, false);
        const status = mapAlias(raw.status_raw, STATUS_INACTIVE_ALIASES) ? 'inactive' : 'active';

        const levels = (raw.levels_raw ?? []).map((l) => ({
            code: null,
            label: l.label ?? '',
            description: l.description ?? '',
            weight: l.weight ?? '',
        }));

        const edit = {
            criteria_name: raw.criteria_name ?? '',
            criteria_code: raw.criteria_code_raw ?? '',
            category: raw.category ?? '',
            description: raw.description ?? '',
            department_raw: raw.department_raw ?? '',
            allow_half_score,
            levels,
            status,
        };

        const validated = validatePreviewEdit(edit, ctx);
        return { line: raw.line, edit, ...validated };
    });
}

/** @param {object} row — preview row with .edit */
export function revalidatePreviewRow(row, opts = {}) {
    const ctx = validationContext(opts);
    const validated = validatePreviewEdit(row.edit, ctx);
    Object.assign(row, validated);
    return row;
}

export function createPreviewRows(validatedRows) {
    return validatedRows.map((r) => ({ ...r }));
}

export function rowsToPayload(validRows) {
    return validRows.map((r) => ({
        scope: r.scope,
        department_code: r.department_code,
        department_name: r.department_name,
        criteria_code: r.criteria_code,
        criteria_name: r.edit?.criteria_name?.trim() ?? r.criteria_name,
        category: r.category,
        description: r.description,
        allow_half_score: r.allow_half_score,
        score_levels: r.levels.map((l) => ({
            code: l.code,
            label: l.label,
            description: l.description,
            weight: l.weight,
        })),
        is_active: r.is_active,
    }));
}

/**
 * Xuất dòng xem trước (hợp lệ hoặc lỗi) ra Excel để sửa lại.
 * @param {'valid'|'errors'} mode
 */
export function exportPreviewRows(rows, { mode = 'errors' } = {}) {
    const ws = {};
    const baseHeaders = IMPORT_BASE_HEADERS.map((h) => h.label);
    const levelCols = levelHeaders();
    const headers = [...baseHeaders, ...levelCols.map((h) => h.label), IMPORT_STATUS_HEADER.label, mode === 'errors' ? 'Lỗi' : 'Ghi chú'];
    const COLS = headers.length - 1;

    setCell(ws, 0, 0, mode === 'errors' ? 'DÒNG LỖI — SỬA VÀ TẢI LẠI' : 'DÒNG HỢP LỆ', S.title);
    mergeRow(ws, 0, 0, COLS);

    headers.forEach((h, c) => setCell(ws, 2, c, h, S.header));

    rows.forEach((row, idx) => {
        const r = 3 + idx;
        const e = row.edit ?? row;
        const vals = [
            row.department_name ?? e.department_raw ?? '',
            e.criteria_name ?? '',
            row.criteria_code ?? '',
            row.category ?? e.category ?? '',
            row.description ?? e.description ?? '',
            row.allow_half_score ? 'Có' : 'Không',
        ];
        for (let n = 1; n <= IMPORT_MAX_LEVELS; n++) {
            const level = (row.levels ?? e.levels ?? [])[n - 1];
            vals.push(level?.label ?? '', level?.description ?? '', level?.weight ?? '');
        }
        vals.push(row.is_active === false ? 'Ngưng' : 'Hoạt động');
        vals.push(mode === 'errors' ? (row.errors ?? []).join('; ') : 'OK');

        const style = idx % 2 === 0 ? S.sample : S.input;
        vals.forEach((v, c) => setCell(ws, r, c, v, style));
    });

    const totalRow = 3 + rows.length;
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: COLS } });
    setColWidths(ws, [
        ...IMPORT_BASE_HEADERS.map((h) => h.width),
        ...levelCols.map((h) => h.width),
        IMPORT_STATUS_HEADER.width,
        36,
    ]);

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, mode === 'errors' ? 'Loi' : 'Hop le');
    XLSX.writeFile(wb, `VA_TieuChiDanhGia_${mode === 'errors' ? 'Loi' : 'HopLe'}_${fileStamp()}.xlsx`);
}
