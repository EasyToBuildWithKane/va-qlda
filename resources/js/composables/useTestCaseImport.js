import XLSX from 'xlsx-js-style';

const BRAND = '9A0036';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';
const AMBER_SOFT = 'FFFBEB';

/** Marker ẩn trên sheet "Nhập liệu" — nhận dạng file mẫu đúng. */
export const IMPORT_TEMPLATE_MARKER = 'VA_TESTCASE_IMPORT_V1';

export const IMPORT_HEADERS = [
    { key: 'title', label: 'Tiêu đề *', width: 36 },
    { key: 'priority', label: 'Mức độ ưu tiên *', width: 18 },
    { key: 'status', label: 'Trạng thái', width: 16 },
    { key: 'suite', label: 'Nhóm kiểm thử (tên)', width: 22 },
    { key: 'preconditions', label: 'Điều kiện tiên quyết', width: 32 },
    { key: 'expected_result', label: 'Kết quả mong đợi', width: 32 },
    { key: 'owner', label: 'Người phụ trách', width: 22 },
];

const PRIORITY_ALIASES = {
    low: ['low', 'thấp', 'thap'],
    medium: ['medium', 'trung bình', 'trung binh', 'tb'],
    high: ['high', 'cao'],
    critical: ['critical', 'nghiêm trọng', 'nghiem trong'],
};

const STATUS_ALIASES = {
    draft: ['draft', 'nháp', 'nhap'],
    ready: ['ready', 'sẵn sàng', 'san sang'],
    deprecated: ['deprecated', 'không còn dùng', 'khong con dung', 'hết hiệu lực'],
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
    required: { font: { bold: true, sz: 10, color: { rgb: WHITE } }, fill: { fgColor: { rgb: 'BE123C' } }, alignment: { horizontal: 'center', vertical: 'center', wrapText: true }, border: borderThin() },
    sample: { font: { sz: 10, color: { rgb: SLATE_600 }, italic: true }, fill: { fgColor: { rgb: AMBER_SOFT } }, alignment: { vertical: 'center', wrapText: true }, border: borderThin() },
    cell: { font: { sz: 10, color: { rgb: '334155' } }, alignment: { vertical: 'center', wrapText: true }, border: borderThin() },
};

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = { v: value ?? '', t: 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

function buildGuideSheet() {
    const ws = {};
    const guides = [
        ['HƯỚNG DẪN NHẬP TEST CASE — VAschools QA', null],
        [''],
        ['1. Cấu trúc file', 'File mẫu có 2 sheet: "Huong dan" (sheet này) và "Nhap lieu" (nhập dữ liệu).'],
        ['2. Sheet nhập liệu', 'Chỉ nhập dữ liệu vào sheet "Nhap lieu", bắt đầu từ dòng 8 trở đi.'],
        ['3. Cột bắt buộc', '"Tiêu đề" và "Mức độ ưu tiên" (ký tự * trong header) là bắt buộc.'],
        ['4. Mức độ ưu tiên', 'Nhập: low / medium / high / critical (hoặc Thấp / Trung bình / Cao / Nghiêm trọng).'],
        ['5. Trạng thái', 'Nhập: draft / ready / deprecated (hoặc Nháp / Sẵn sàng / Không còn dùng). Để trống = Nháp.'],
        ['6. Nhóm kiểm thử', 'Nhập tên nhóm (gom theo tính năng/màn hình). Để trống nếu không nhóm. Alias cũ «Bộ test» vẫn nhận.'],
        ['7. Người phụ trách', 'Nhập họ tên đầy đủ của nhân viên. Để trống nếu chưa xác định.'],
        ['8. Tối đa', 'Tối đa 200 test case mỗi lần nhập.'],
        ['9. Dòng mẫu', 'Dòng 6–7 trên sheet Nhập liệu là dòng mẫu — hệ thống sẽ tự bỏ qua, không xóa.'],
        ['10. Quy trình', 'Tải mẫu → Điền dữ liệu từ dòng 8 → Chọn file → Xem trước → Nhập.'],
    ];

    guides.forEach(([a, b], i) => {
        if (i === 0) {
            setCell(ws, i, 0, a, S.section);
            mergeRow(ws, i, 0, 3);
        } else if (b !== undefined) {
            setCell(ws, i, 0, a, S.guideBold);
            setCell(ws, i, 1, b, S.guide);
            mergeRow(ws, i, 1, 3);
        } else {
            setCell(ws, i, 0, a || '', S.guide);
            mergeRow(ws, i, 0, 3);
        }
    });

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: guides.length, c: 3 } });
    ws['!cols'] = [{ wch: 30 }, { wch: 70 }, { wch: 10 }, { wch: 10 }];
    return ws;
}

function buildInputSheet() {
    const ws = {};
    const COLS = IMPORT_HEADERS.length - 1;

    setCell(ws, 0, 0, IMPORT_TEMPLATE_MARKER, { font: { color: { rgb: 'FFFFFF' }, sz: 8 }, alignment: { horizontal: 'left' } });
    mergeRow(ws, 0, 0, COLS);

    setCell(ws, 1, 0, 'NHẬP TEST CASE — VAschools QA', S.section);
    mergeRow(ws, 1, 0, COLS);

    setCell(ws, 2, 0, 'Điền dữ liệu từ dòng 8 trở đi. Không thay đổi dòng header (dòng 5). Dòng 6–7 là mẫu.', S.subtitle);
    mergeRow(ws, 2, 0, COLS);

    IMPORT_HEADERS.forEach(({ label }, c) => {
        const isRequired = label.endsWith('*');
        setCell(ws, 4, c, label, isRequired ? S.required : S.header);
    });

    const samples = [
        ['Kiểm tra đăng nhập thành công', 'high', 'ready', 'Xác thực cơ bản', 'Đã có tài khoản hợp lệ', 'Đăng nhập thành công', 'Nguyễn Văn A'],
        ['Kiểm tra quên mật khẩu', 'medium', 'draft', '', '', 'Nhận email đặt lại mật khẩu', 'Trần Thị B'],
    ];
    samples.forEach((row, si) => {
        row.forEach((val, c) => setCell(ws, 5 + si, c, val, S.sample));
    });

    for (let r = 7; r < 57; r++) {
        IMPORT_HEADERS.forEach((_, c) => setCell(ws, r, c, '', S.cell));
    }

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 57, c: COLS } });
    ws['!cols'] = IMPORT_HEADERS.map(({ width }) => ({ wch: width }));
    return ws;
}

/** Tải file mẫu nhập test case. */
export function downloadTestCaseTemplate() {
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildGuideSheet(), 'Huong dan');
    XLSX.utils.book_append_sheet(wb, buildInputSheet(), 'Nhap lieu');
    XLSX.writeFile(wb, 'VA_TestCase_MauNhap.xlsx');
}

function normalizeStr(s) {
    return String(s ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .trim();
}

function resolveAlias(aliases, raw) {
    const n = normalizeStr(raw);
    for (const [value, list] of Object.entries(aliases)) {
        if (list.includes(n)) return value;
    }
    return null;
}

function readSheetMatrix(workbook, sheetName) {
    const sheet = workbook.Sheets[sheetName];
    if (!sheet) return null;
    return XLSX.utils.sheet_to_json(sheet, { header: 1, defval: '' });
}

function findMarkerRow(matrix) {
    for (let i = 0; i < Math.min(matrix.length, 10); i++) {
        const cell = String(matrix[i]?.[0] ?? '').trim();
        if (cell === IMPORT_TEMPLATE_MARKER) return i;
    }
    return -1;
}

function findHeaderRow(matrix, startAfter) {
    for (let i = startAfter; i < Math.min(matrix.length, startAfter + 5); i++) {
        const row = matrix[i];
        if (row?.some((c) => String(c).includes('Tiêu đề'))) return i;
    }
    return -1;
}

function buildColumnMap(headerRow) {
    const map = {};
    const keyMap = {
        'tieu de': 'title',
        'muc do uu tien': 'priority',
        'trang thai': 'status',
        'bo test': 'suite',
        'nhom kiem thu': 'suite',
        'nhom kiem thu (ten)': 'suite',
        'dieu kien tien quyet': 'preconditions',
        'ket qua mong doi': 'expected_result',
        'nguoi phu trach': 'owner',
    };
    headerRow.forEach((cell, idx) => {
        const norm = normalizeStr(String(cell).replace(/\*/g, '').trim());
        if (keyMap[norm]) map[keyMap[norm]] = idx;
    });
    return map;
}

/**
 * Parse Excel/CSV file and return rows + errors.
 * @param {File} file
 * @param {{ employees: Array, suites: Array }} options
 * @returns {Promise<{ rows: object[], errors: string[] }>}
 */
export async function parseTestCaseFile(file, { employees = [], suites = [] } = {}) {
    const buffer = await file.arrayBuffer();
    const workbook = XLSX.read(buffer, { type: 'array', cellDates: true });

    const sheetName = workbook.SheetNames.find((n) =>
        normalizeStr(n).includes('nhap') || normalizeStr(n).includes('data'),
    ) ?? workbook.SheetNames[0];

    const matrix = readSheetMatrix(workbook, sheetName);
    if (!matrix) return { rows: [], errors: ['Không đọc được sheet dữ liệu.'] };

    const markerIdx = findMarkerRow(matrix);
    const headerIdx = markerIdx >= 0 ? findHeaderRow(matrix, markerIdx + 1) : findHeaderRow(matrix, 0);

    if (headerIdx < 0) return { rows: [], errors: ['Không tìm thấy dòng header. Vui lòng dùng file mẫu.'] };

    const colMap = buildColumnMap(matrix[headerIdx]);
    if (!('title' in colMap)) return { rows: [], errors: ['Không tìm thấy cột "Tiêu đề". Kiểm tra file mẫu.'] };

    const employeeMap = Object.fromEntries(
        employees.map((e) => [normalizeStr(e.name ?? e.label), e.id ?? e.value]),
    );
    const suiteMap = Object.fromEntries(
        suites.map((s) => [normalizeStr(s.name ?? s.label), s.id ?? s.value]),
    );

    const SAMPLE_ROWS = new Set([
        normalizeStr('Kiểm tra đăng nhập thành công'),
        normalizeStr('Kiểm tra quên mật khẩu'),
    ]);

    const rows = [];
    const errors = [];

    for (let i = headerIdx + 1; i < matrix.length; i++) {
        const row = matrix[i];
        if (!row?.some((c) => String(c ?? '').trim())) continue;

        const rawTitle = String(row[colMap.title] ?? '').trim();
        if (!rawTitle || SAMPLE_ROWS.has(normalizeStr(rawTitle))) continue;

        const rawPriority = String(row[colMap.priority] ?? '').trim();
        const priority = resolveAlias(PRIORITY_ALIASES, rawPriority) ?? rawPriority.toLowerCase();

        const rawStatus = String(row[colMap.status] ?? '').trim();
        const status = rawStatus ? (resolveAlias(STATUS_ALIASES, rawStatus) ?? 'draft') : 'draft';

        const rawOwner = String(row[colMap.owner] ?? '').trim();
        const ownerId = rawOwner ? (employeeMap[normalizeStr(rawOwner)] ?? null) : null;

        const rawSuite = String(row[colMap.suite] ?? '').trim();
        const suiteId = rawSuite ? (suiteMap[normalizeStr(rawSuite)] ?? null) : null;

        if (!rawTitle) {
            errors.push(`Dòng ${i + 1}: Tiêu đề không được để trống.`);
            continue;
        }
        if (!['low', 'medium', 'high', 'critical'].includes(priority)) {
            errors.push(`Dòng ${i + 1}: Mức độ ưu tiên không hợp lệ ("${rawPriority}").`);
        }

        rows.push({
            _row: i + 1,
            _valid: !errors.some((e) => e.startsWith(`Dòng ${i + 1}`)),
            title: rawTitle,
            priority,
            status,
            suite_id: suiteId,
            suite_name: rawSuite,
            owner_id: ownerId,
            owner_name: rawOwner,
            preconditions: String(row[colMap.preconditions] ?? '').trim(),
            expected_result: String(row[colMap.expected_result] ?? '').trim(),
        });
    }

    if (rows.length > 200) {
        errors.push('Vượt quá 200 test case. Vui lòng tách file nhỏ hơn.');
    }

    return { rows, errors };
}

/**
 * Validate rows and mark valid/invalid.
 * @param {object[]} rows
 * @returns {{ validRows: object[], invalidRows: object[] }}
 */
export function validateTestCaseRows(rows) {
    const validRows = [];
    const invalidRows = [];

    rows.forEach((row) => {
        const rowErrors = [];
        if (!row.title) rowErrors.push('Tiêu đề trống');
        if (!['low', 'medium', 'high', 'critical'].includes(row.priority)) rowErrors.push('Mức độ ưu tiên không hợp lệ');
        if (row._valid === false) rowErrors.push('Dòng bị đánh dấu lỗi');

        if (rowErrors.length === 0) {
            validRows.push({ ...row, _errors: [] });
        } else {
            invalidRows.push({ ...row, _errors: rowErrors });
        }
    });

    return { validRows, invalidRows };
}

/**
 * Convert a preview row to API payload.
 * @param {object} row
 * @param {number} projectId
 * @returns {object}
 */
export function testCaseRowToPayload(row, projectId) {
    return {
        project_id: projectId,
        title: row.title,
        priority: row.priority,
        status: row.status || 'draft',
        suite_id: row.suite_id ?? null,
        owner_id: row.owner_id ?? null,
        preconditions: row.preconditions || null,
        expected_result: row.expected_result || null,
        steps: [],
    };
}

export const useTestCaseImport = () => ({
    downloadTestCaseTemplate,
    parseTestCaseFile,
    validateTestCaseRows,
    testCaseRowToPayload,
    IMPORT_TEMPLATE_MARKER,
    IMPORT_HEADERS,
});
