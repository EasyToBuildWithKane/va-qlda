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

export const IMPORT_TEMPLATE_MARKER = 'VA_EVAL_TPL_IMPORT_V1';

export const IMPORT_HEADERS = [
    { key: 'name', label: 'Tên mẫu đánh giá *', width: 34 },
    { key: 'template_code', label: 'Mã mẫu (để trống = tự sinh)', width: 18 },
    { key: 'position', label: 'Vị trí đánh giá', width: 28 },
    { key: 'criteria_codes', label: 'Mã tiêu chí (cách nhau bởi ;)', width: 36 },
    { key: 'weights', label: 'Trọng số tương ứng (;)', width: 22 },
    { key: 'required_scores', label: 'Điểm yêu cầu tương ứng (;)', width: 26 },
    { key: 'include_in_total', label: 'Tính tổng (Có/Không;…)', width: 22 },
    { key: 'description', label: 'Mô tả', width: 28 },
    { key: 'status', label: 'Trạng thái (Hoạt động/Ngưng)', width: 18 },
];

const BOOL_TRUE = ['co', 'true', '1', 'x', 'yes'];
const STATUS_INACTIVE = ['ngung', 'ngung hoat dong', 'inactive', 'khoa'];

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
    cell: {
        font: { sz: 10, color: { rgb: '334155' } },
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
    return `${dd}${mm}${t.getFullYear()}`;
}

function splitList(raw) {
    return String(raw ?? '')
        .split(/[;|,]/)
        .map((s) => s.trim())
        .filter(Boolean);
}

function parseBool(raw, defaultValue = true) {
    const key = normalizeSearchKey(raw);
    if (key === '') return defaultValue;
    if (BOOL_TRUE.includes(key)) return true;
    return false;
}

function readSheetMatrix(sheet) {
    const ref = sheet['!ref'];
    if (!ref) return [];
    const range = XLSX.utils.decode_range(ref);
    const rows = [];
    for (let r = range.s.r; r <= range.e.r; r++) {
        const row = [];
        for (let c = range.s.c; c <= range.e.c; c++) {
            const cell = sheet[XLSX.utils.encode_cell({ r, c })];
            row.push(cell?.v != null ? String(cell.v).trim() : '');
        }
        rows.push(row);
    }
    return rows;
}

function findHeaderIndex(matrix) {
    for (let i = 0; i < matrix.length; i++) {
        const joined = matrix[i].map((c) => normalizeSearchKey(c)).join('|');
        if (joined.includes('ten mau') && joined.includes('ma mau')) return i;
        if (matrix[i].some((c) => c === IMPORT_TEMPLATE_MARKER) && i + 1 < matrix.length) {
            return i + 1;
        }
    }
    return -1;
}

function columnIndexMap(headerRow) {
    const map = {};
    headerRow.forEach((label, idx) => {
        const key = normalizeSearchKey(label);
        if (key.includes('ten mau')) map.name = idx;
        else if (key.includes('ma mau')) map.template_code = idx;
        else if (key.includes('vi tri')) map.position = idx;
        else if (key.includes('ma tieu chi')) map.criteria_codes = idx;
        else if (key.includes('trong so')) map.weights = idx;
        else if (key.includes('diem yeu cau')) map.required_scores = idx;
        else if (key.includes('tinh tong') || key.includes('tinh vao tong')) map.include_in_total = idx;
        else if (key.includes('mo ta')) map.description = idx;
        else if (key.includes('trang thai')) map.status = idx;
    });
    return map;
}

/**
 * @param {{ positions?: Array<{code:string,name:string}>, criteriaOptions?: Array<{id:number,criteria_code:string,criteria_name:string}> }} meta
 */
export function downloadTemplateImportFile(meta = {}) {
    const headers = IMPORT_HEADERS;
    const COLS = headers.length - 1;
    const ws = {};

    setCell(ws, 0, 0, 'NHẬP MẪU ĐÁNH GIÁ — VA-Workspace', S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, 'Xem hướng dẫn trên màn hình Nhập. Điền từ dòng 8 (sau 2 dòng mẫu). Mã tiêu chí lấy từ sheet Tham chieu.', S.subtitle);
    mergeRow(ws, 1, 0, COLS);
    setCell(ws, 2, 0, '⚠ Không đổi tên cột. Cột (*) bắt buộc. Tối đa 200 dòng/lần.', S.note);
    mergeRow(ws, 2, 0, COLS);
    setCell(ws, 3, 0, IMPORT_TEMPLATE_MARKER, S.subtitle);
    mergeRow(ws, 3, 0, COLS);

    const headerRow = 4;
    headers.forEach((h, c) => {
        setCell(ws, headerRow, c, h.label, h.label.includes('*') ? S.required : S.header);
    });

    const sample1 = [
        'Đánh giá chuyên viên kinh doanh',
        '',
        meta.positions?.[0]?.name ?? 'Chuyên viên Kinh doanh',
        meta.criteriaOptions?.slice(0, 2).map((c) => c.criteria_code).join('; ') || 'TCVA001; TCVA002',
        '1; 1',
        'Điểm yêu cầu 1; Điểm yêu cầu 1',
        'Có; Có',
        '',
        'Hoạt động',
    ];
    const sample2 = [
        'Đánh giá trưởng nhóm kỹ thuật',
        'MDG099',
        meta.positions?.[1]?.name ?? '',
        meta.criteriaOptions?.[0]?.criteria_code || 'TCVA001',
        '2',
        'Điểm yêu cầu 2',
        'Có',
        'Mẫu mẫu — xóa trước khi nhập',
        'Hoạt động',
    ];
    sample1.forEach((v, c) => setCell(ws, 5, c, v, S.sample));
    sample2.forEach((v, c) => setCell(ws, 6, c, v, S.sample));

    for (let r = 7; r < 57; r++) {
        headers.forEach((_, c) => setCell(ws, r, c, '', S.input));
    }

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 56, c: COLS } });
    setColWidths(ws, headers.map((h) => h.width));

    const refWs = {};
    setCell(refWs, 0, 0, 'THAM CHIẾU — Vị trí & tiêu chí', S.title);
    mergeRow(refWs, 0, 0, 3);
    setCell(refWs, 2, 0, 'Mã vị trí', S.header);
    setCell(refWs, 2, 1, 'Tên vị trí', S.header);
    (meta.positions || []).slice(0, 200).forEach((p, i) => {
        setCell(refWs, 3 + i, 0, p.code, S.cell);
        setCell(refWs, 3 + i, 1, p.name, S.cell);
    });
    setCell(refWs, 2, 3, 'Mã tiêu chí', S.header);
    setCell(refWs, 2, 4, 'Tên tiêu chí', S.header);
    setCell(refWs, 2, 5, 'Loại', S.header);
    (meta.criteriaOptions || []).slice(0, 300).forEach((c, i) => {
        setCell(refWs, 3 + i, 3, c.criteria_code, S.cell);
        setCell(refWs, 3 + i, 4, c.criteria_name, S.cell);
        setCell(refWs, 3 + i, 5, c.category || '', S.cell);
    });
    const refLast = Math.max((meta.positions || []).length, (meta.criteriaOptions || []).length, 1) + 3;
    refWs['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: refLast, c: 5 } });
    setColWidths(refWs, [18, 28, 4, 14, 32, 16]);

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Nhap lieu');
    XLSX.utils.book_append_sheet(wb, refWs, 'Tham chieu');
    XLSX.writeFile(wb, `VA_MauNhap_MauDanhGia_${fileStamp()}.xlsx`);
}

/**
 * @returns {{ rows: Array, errors: string[] }}
 */
export function parseTemplateImportFile(file, meta = {}) {
    return new Promise((resolve, reject) => {
        const reader = new FileReader();
        reader.onload = (e) => {
            try {
                const data = new Uint8Array(e.target.result);
                const wb = XLSX.read(data, { type: 'array' });
                const sheetName = wb.SheetNames.find((n) => normalizeSearchKey(n).includes('nhap'))
                    || wb.SheetNames[0];
                const sheet = wb.Sheets[sheetName];
                const matrix = readSheetMatrix(sheet);
                const headerIdx = findHeaderIndex(matrix);
                if (headerIdx < 0) {
                    resolve({ rows: [], errors: ['Không tìm thấy dòng tiêu đề. Dùng đúng file mẫu.'] });
                    return;
                }
                const colMap = columnIndexMap(matrix[headerIdx]);
                if (colMap.name == null) {
                    resolve({ rows: [], errors: ['Thiếu cột «Tên mẫu đánh giá».'] });
                    return;
                }

                const positions = meta.positions || [];
                const criteriaOptions = meta.criteriaOptions || [];
                const byCode = Object.fromEntries(
                    criteriaOptions.map((c) => [String(c.criteria_code).toUpperCase(), c]),
                );

                const rows = [];
                const errors = [];
                const sampleNames = new Set([
                    normalizeSearchKey('Đánh giá chuyên viên kinh doanh'),
                    normalizeSearchKey('Đánh giá trưởng nhóm kỹ thuật'),
                ]);

                for (let r = headerIdx + 1; r < matrix.length; r++) {
                    const line = matrix[r];
                    if (!line || line.every((c) => !c)) continue;
                    const name = line[colMap.name] || '';
                    if (!name) continue;
                    if (sampleNames.has(normalizeSearchKey(name))
                        && normalizeSearchKey(line[colMap.description] || '').includes('mau')) {
                        continue;
                    }

                    const positionRaw = colMap.position != null ? (line[colMap.position] || '') : '';
                    let position_code = null;
                    let position_name = null;
                    if (positionRaw) {
                        const hit = positions.find((p) => (
                            normalizeSearchKey(p.name) === normalizeSearchKey(positionRaw)
                            || String(p.code).toUpperCase() === positionRaw.toUpperCase()
                        ));
                        if (hit) {
                            position_code = hit.code;
                            position_name = hit.name;
                        } else {
                            position_name = positionRaw;
                        }
                    }

                    const codes = splitList(colMap.criteria_codes != null ? line[colMap.criteria_codes] : '');
                    const weights = splitList(colMap.weights != null ? line[colMap.weights] : '');
                    const scores = splitList(colMap.required_scores != null ? line[colMap.required_scores] : '');
                    const includes = splitList(colMap.include_in_total != null ? line[colMap.include_in_total] : '');

                    const criteria = [];
                    const rowErrors = [];
                    codes.forEach((code, i) => {
                        const crit = byCode[code.toUpperCase()];
                        if (!crit) {
                            rowErrors.push(`Dòng ${r + 1}: không tìm thấy tiêu chí «${code}».`);
                            return;
                        }
                        criteria.push({
                            criterion_id: crit.id,
                            weight: weights[i] != null && weights[i] !== '' ? Number(weights[i]) : 1,
                            required_score_label: scores[i] || null,
                            include_in_total: includes[i] != null ? parseBool(includes[i], true) : true,
                            sort_order: i,
                        });
                    });

                    const statusRaw = colMap.status != null ? (line[colMap.status] || '') : '';
                    const is_active = !STATUS_INACTIVE.includes(normalizeSearchKey(statusRaw));

                    if (rowErrors.length) {
                        errors.push(...rowErrors);
                    }

                    rows.push({
                        _row: r + 1,
                        name,
                        template_code: colMap.template_code != null ? (line[colMap.template_code] || '').toUpperCase() || null : null,
                        position_code,
                        position_name,
                        description: colMap.description != null ? (line[colMap.description] || '') || null : null,
                        is_active,
                        criteria,
                        _errors: rowErrors,
                    });
                }

                if (rows.length > 200) {
                    errors.push('File vượt quá 200 dòng. Chia nhỏ rồi nhập lại.');
                }

                resolve({ rows: rows.slice(0, 200), errors });
            } catch (err) {
                reject(err);
            }
        };
        reader.onerror = () => reject(new Error('Không đọc được file.'));
        reader.readAsArrayBuffer(file);
    });
}

export function validateTemplateRows(rows) {
    return (rows || []).map((row) => {
        const errors = [...(row._errors || [])];
        if (!row.name?.trim()) errors.push('Thiếu tên mẫu đánh giá.');
        if (row.template_code && !/^[A-Z][A-Z0-9]*$/.test(row.template_code)) {
            errors.push('Mã mẫu không hợp lệ.');
        }
        return { ...row, _errors: errors, _valid: errors.length === 0 };
    });
}

export function templateRowToPayload(row) {
    return {
        name: row.name,
        template_code: row.template_code || null,
        position_code: row.position_code || null,
        position_name: row.position_name || null,
        description: row.description || null,
        is_active: row.is_active !== false,
        criteria: (row.criteria || []).map((c, i) => ({
            criterion_id: c.criterion_id,
            weight: c.weight ?? 1,
            required_score_label: c.required_score_label || null,
            include_in_total: c.include_in_total !== false,
            sort_order: c.sort_order ?? i,
        })),
    };
}
