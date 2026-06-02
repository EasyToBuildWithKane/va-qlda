import XLSX from 'xlsx-js-style';
import { date } from '@/composables/useFormat';
import { getAssignees } from '@/composables/useSprintFilters';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';
const AMBER_SOFT = 'FFFBEB';
const AMBER_TEXT = 'B45309';

export const SPRINT_IMPORT_MARKER = 'VA_SPRINT_IMPORT_V1';

export const SPRINT_IMPORT_HEADERS = [
    { key: 'title', label: 'Tiêu đề *', width: 34 },
    { key: 'sprint', label: 'Sprint', width: 20 },
    { key: 'status', label: 'Trạng thái', width: 14 },
    { key: 'priority', label: 'Ưu tiên', width: 14 },
    { key: 'phase', label: 'Giai đoạn', width: 16 },
    { key: 'assignee', label: 'Người phụ trách', width: 22 },
    { key: 'reviewer', label: 'Người duyệt', width: 22 },
    { key: 'start_date', label: 'Bắt đầu (DD/MM/YYYY)', width: 16 },
    { key: 'due_date', label: 'Hạn (DD/MM/YYYY)', width: 16 },
    { key: 'estimate_hours', label: 'Giờ ước tính', width: 12 },
    { key: 'progress', label: 'Tiến độ %', width: 10 },
    { key: 'description', label: 'Mô tả', width: 30 },
];

const STATUS_ALIASES = {
    todo: ['todo', 'can lam', 'cần làm', 'chua lam'],
    in_progress: ['in_progress', 'in progress', 'dang lam', 'đang làm'],
    in_review: ['in_review', 'review', 'dang review', 'đang review'],
    done: ['done', 'hoan thanh', 'hoàn thành', 'xong'],
    blocked: ['blocked', 'bi chan', 'bị chặn'],
};

const PRIORITY_ALIASES = {
    low: ['low', 'thap', 'thấp'],
    medium: ['medium', 'trung binh', 'trung bình', 'tb'],
    high: ['high', 'cao'],
    urgent: ['urgent', 'khan cap', 'khẩn cấp'],
};

/** Mô tả chi tiết từng cột trên sheet Hướng dẫn */
const COLUMN_GUIDE = [
    { col: 'Tiêu đề *', required: 'Bắt buộc', desc: 'Tên công việc, tối đa 255 ký tự. Không được trùng toàn bộ nội dung nếu muốn tránh nhầm lẫn.', example: 'Thiết kế API đăng nhập' },
    { col: 'Sprint', required: 'Tuỳ chọn', desc: 'Tên sprint phải khớp chính xác với sheet "Danh sách Sprint" (không phân biệt hoa thường). Để trống = đưa vào Backlog.', example: 'Sprint 1' },
    { col: 'Trạng thái', required: 'Tuỳ chọn', desc: 'Mặc định "Cần làm" (todo) nếu để trống. Dùng mã tiếng Anh hoặc nhãn tiếng Việt (xem bảng bên dưới).', example: 'in_progress hoặc Đang làm' },
    { col: 'Ưu tiên', required: 'Tuỳ chọn', desc: 'Mặc định "Trung bình" (medium). Giá trị: low, medium, high, urgent.', example: 'high' },
    { col: 'Giai đoạn', required: 'Tuỳ chọn', desc: 'Giai đoạn SDLC: discovery, analysis, design, development, testing, uat, deployment, maintenance.', example: 'development' },
    { col: 'Người phụ trách', required: 'Tuỳ chọn', desc: 'Họ tên nhân sự trong hệ thống — khớp danh sách cuối sheet. Một người chính mỗi dòng.', example: 'Nguyễn Văn A' },
    { col: 'Người duyệt', required: 'Tuỳ chọn', desc: 'Người review/QA duyệt task. Cùng quy tắc đặt tên như Người phụ trách.', example: 'Trần Thị B' },
    { col: 'Bắt đầu', required: 'Tuỳ chọn', desc: 'Ngày bắt đầu dự kiến. Định dạng DD/MM/YYYY hoặc YYYY-MM-DD.', example: '01/06/2026' },
    { col: 'Hạn', required: 'Tuỳ chọn', desc: 'Deadline. Cùng định dạng ngày. Hệ thống cảnh báo quá hạn khi import xong.', example: '15/06/2026' },
    { col: 'Giờ ước tính', required: 'Tuỳ chọn', desc: 'Số giờ công ước tính (vd: 4). Khi task chuyển "Đang làm", hệ thống tính hạn = thời điểm bắt đầu + giờ ước tính; cập nhật trạng thái sau hạn → task bị đánh dấu trễ.', example: '4' },
    { col: 'Tiến độ %', required: 'Tuỳ chọn', desc: 'Phần trăm hoàn thành từ 0 đến 100. Mặc định 0.', example: '40' },
    { col: 'Mô tả', required: 'Tuỳ chọn', desc: 'Chi tiết yêu cầu, link tài liệu, ghi chú kỹ thuật.', example: 'Theo mockup Figma v2' },
];

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
    cell: { font: { sz: 10, color: { rgb: '334155' } }, alignment: { vertical: 'center', wrapText: true }, border: borderThin() },
    cellAlt: { font: { sz: 10, color: { rgb: '334155' } }, fill: { fgColor: { rgb: SLATE_50 } }, alignment: { vertical: 'center', wrapText: true }, border: borderThin() },
    kpiLabel: { font: { bold: true, sz: 9, color: { rgb: SLATE_600 } }, fill: { fgColor: { rgb: SLATE_50 } }, alignment: { horizontal: 'center', vertical: 'center' }, border: borderThin() },
    kpiValue: { font: { bold: true, sz: 12, color: { rgb: BRAND } }, fill: { fgColor: { rgb: BRAND_SOFT } }, alignment: { horizontal: 'center', vertical: 'center' }, border: borderThin() },
};

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((wch) => ({ wch }));
}

function safeCode(code) {
    return String(code ?? 'PRJ').replace(/[:\\/?*[\]]/g, '_');
}

function normalizeKey(val) {
    return String(val ?? '')
        .trim()
        .toLowerCase()
        .replace(/đ/g, 'd')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ');
}

function mapAlias(value, aliasMap, defaultValue = null) {
    const key = normalizeKey(value);
    if (!key) return defaultValue;
    for (const [canonical, aliases] of Object.entries(aliasMap)) {
        if (aliases.some((a) => normalizeKey(a) === key) || normalizeKey(canonical) === key) return canonical;
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

function resolveEmployee(name, employees) {
    const key = normalizeKey(name);
    if (!key) return { id: null };
    const exact = employees.find((e) => normalizeKey(e.name) === key);
    if (exact) return { id: exact.id, name: exact.name };
    const partial = employees.find((e) => normalizeKey(e.name).includes(key) || key.includes(normalizeKey(e.name)));
    if (partial) return { id: partial.id, name: partial.name };
    return { id: null, error: `Không tìm thấy: "${name}"` };
}

function resolveSprint(name, sprints) {
    const key = normalizeKey(name);
    if (!key || key === 'backlog') return { id: null };
    const s = sprints.find((sp) => normalizeKey(sp.name) === key);
    if (s) return { id: s.id, name: s.name };
    return { id: null, error: `Sprint không tồn tại: "${name}"` };
}

function writeSectionTitle(ws, row, title, COLS) {
    setCell(ws, row, 0, title, S.section);
    mergeRow(ws, row, 0, COLS);
    return row + 1;
}

function writeGuideLines(ws, startRow, lines, COLS) {
    let row = startRow;
    lines.forEach((text) => {
        setCell(ws, row, 0, text, S.guide);
        mergeRow(ws, row, 0, COLS);
        row++;
    });
    return row;
}

function buildGuideSheet({ projectCode, projectName, sprints, employees, statusOptions, priorityOptions, phaseOptions }) {
    const ws = {};
    const COLS = 10;
    let row = 0;
    const stamp = new Date().toLocaleDateString('vi-VN');

    setCell(ws, row, 0, 'HƯỚNG DẪN NHẬP SPRINT & TASK — VA SCHOOLS', S.title);
    mergeRow(ws, row, 0, COLS);
    row++;
    setCell(ws, row, 0, `Dự án: ${projectName || '—'}  |  Mã: ${projectCode}  |  File mẫu tạo: ${stamp}`, S.subtitle);
    mergeRow(ws, row, 0, COLS);
    row += 2;

    row = writeSectionTitle(ws, row, 'I. CẤU TRÚC FILE MẪU (3 SHEET)', COLS);
    row = writeGuideLines(ws, row, [
        '• Sheet "Huong dan" (đang xem): Hướng dẫn đầy đủ — không chỉnh sửa, không xóa.',
        '• Sheet "Nhap lieu": Bảng nhập task thực tế — đây là sheet bạn upload lên hệ thống.',
        '• Sheet "Danh sach Sprint": Danh mục sprint hiện có — chỉ tham khảo, copy đúng tên sang cột Sprint.',
    ], COLS);
    row++;

    row = writeSectionTitle(ws, row, 'II. QUY TRÌNH NHẬP TRÊN HỆ THỐNG', COLS);
    row = writeGuideLines(ws, row, [
        'Bước 1 — Tải file mẫu: Trên web, tab Sprint → nút "Dữ liệu" → tab Nhập → "Tải file mẫu (.xlsx)".',
        'Bước 2 — Mở sheet "Nhap lieu": Dòng 5 là tiêu đề cột (nền đỏ/hồng). KHÔNG đổi tên, không xóa, không chèn cột.',
        'Bước 3 — Xóa 2 dòng mẫu in nghiêng (dòng 6–7): Đây chỉ là ví dụ, hệ thống sẽ bỏ qua nếu giữ nguyên.',
        'Bước 4 — Nhập task từ dòng 8 trở đi: Mỗi dòng = 1 task. Có thể copy từ file khác nhưng phải khớp thứ tự cột.',
        'Bước 5 — Kiểm tra Sprint & nhân sự: Đối chiếu tên sprint và họ tên với các bảng phía dưới sheet này.',
        'Bước 6 — Lưu file Excel (.xlsx), không đổi tên sheet "Nhap lieu".',
        'Bước 7 — Upload: Dữ liệu → Nhập → "Chọn file đã điền".',
        'Bước 8 — Xem trước: Hệ thống hiển thị dòng hợp lệ / lỗi. Sửa trực tiếp trên web hoặc sửa file rồi upload lại.',
        'Bước 9 — Xác nhận nhập: Chỉ các dòng hợp lệ được tạo task. Tối đa 200 dòng mỗi lần.',
        'Bước 10 — Kiểm tra lại trên tab Sprint (Danh sách / Lịch) sau khi import thành công.',
    ], COLS);
    row++;

    row = writeSectionTitle(ws, row, 'III. MÔ TẢ CHI TIẾT TỪNG CỘT (SHEET "NHẬP LIỆU")', COLS);
    setCell(ws, row, 0, 'Cột', S.header);
    setCell(ws, row, 1, 'Bắt buộc', S.header);
    mergeRow(ws, row, 2, COLS);
    setCell(ws, row, 2, 'Mô tả', S.header);
    row++;
    COLUMN_GUIDE.forEach((g, i) => {
        const st = i % 2 === 0 ? S.cell : S.cellAlt;
        setCell(ws, row, 0, g.col, st);
        setCell(ws, row, 1, g.required, st);
        setCell(ws, row, 2, `${g.desc}  →  VD: ${g.example}`, st);
        mergeRow(ws, row, 2, COLS);
        row++;
    });
    row++;

    row = writeSectionTitle(ws, row, 'IV. GIÁ TRỊ TRẠNG THÁI (CỘT "TRẠNG THÁI")', COLS);
    setCell(ws, row, 0, 'Nhãn hiển thị', S.header);
    setCell(ws, row, 1, 'Mã nhập (khuyến nghị)', S.header);
    setCell(ws, row, 2, 'Chấp nhận thêm (tiếng Việt)', S.header);
    row++;
    (statusOptions || []).forEach((o, i) => {
        const aliases = (STATUS_ALIASES[o.value] || []).filter((a) => a !== o.value).slice(0, 4).join(', ');
        const st = i % 2 === 0 ? S.cell : S.cellAlt;
        setCell(ws, row, 0, o.label, st);
        setCell(ws, row, 1, o.value, S.guideBold);
        setCell(ws, row, 2, aliases || '—', st);
        mergeRow(ws, row, 2, COLS);
        row++;
    });
    row++;

    row = writeSectionTitle(ws, row, 'V. GIÁ TRỊ ƯU TIÊN (CỘT "ƯU TIÊN")', COLS);
    setCell(ws, row, 0, 'Nhãn', S.header);
    setCell(ws, row, 1, 'Mã', S.header);
    setCell(ws, row, 2, 'Ghi chú', S.header);
    row++;
    const priorityNotes = { low: 'Ưu tiên thấp', medium: 'Mặc định', high: 'Cần ưu tiên', urgent: 'Khẩn cấp' };
    (priorityOptions || []).forEach((o, i) => {
        const st = i % 2 === 0 ? S.cell : S.cellAlt;
        setCell(ws, row, 0, o.label, st);
        setCell(ws, row, 1, o.value, S.guideBold);
        setCell(ws, row, 2, priorityNotes[o.value] || '', st);
        mergeRow(ws, row, 2, COLS);
        row++;
    });
    row++;

    row = writeSectionTitle(ws, row, 'VI. GIÁ TRỊ GIAI ĐOẠN (CỘT "GIAI ĐOẠN")', COLS);
    setCell(ws, row, 0, 'Nhãn', S.header);
    setCell(ws, row, 1, 'Mã nhập', S.header);
    row++;
    (phaseOptions || []).forEach((o, i) => {
        const st = i % 2 === 0 ? S.cell : S.cellAlt;
        setCell(ws, row, 0, o.label, st);
        setCell(ws, row, 1, o.value, S.guideBold);
        mergeRow(ws, row, 1, COLS);
        row++;
    });
    if (!phaseOptions?.length) {
        setCell(ws, row, 0, 'Để trống nếu không dùng giai đoạn SDLC.', S.guide);
        mergeRow(ws, row, 0, COLS);
        row++;
    }
    row++;

    row = writeSectionTitle(ws, row, 'VII. DANH SÁCH SPRINT TRONG DỰ ÁN (COPY ĐÚNG TÊN)', COLS);
    if (!sprints?.length) {
        row = writeGuideLines(ws, row, ['— Dự án chưa có sprint. Để trống cột Sprint = task vào Backlog. —'], COLS);
    } else {
        setCell(ws, row, 0, 'Tên Sprint', S.header);
        setCell(ws, row, 1, 'Trạng thái', S.header);
        setCell(ws, row, 2, 'Bắt đầu', S.header);
        setCell(ws, row, 3, 'Kết thúc', S.header);
        setCell(ws, row, 4, 'Mục tiêu (tóm tắt)', S.header);
        row++;
        sprints.forEach((sp, i) => {
            const st = i % 2 === 0 ? S.cell : S.cellAlt;
            setCell(ws, row, 0, sp.name, st);
            setCell(ws, row, 1, sp.status?.label || '', st);
            setCell(ws, row, 2, date(sp.start_date) || '—', st);
            setCell(ws, row, 3, date(sp.end_date) || '—', st);
            setCell(ws, row, 4, (sp.goal || '').slice(0, 80), st);
            mergeRow(ws, row, 4, COLS);
            row++;
        });
    }
    row++;

    row = writeSectionTitle(ws, row, 'VIII. DANH SÁCH NHÂN SỰ (NGƯỜI PHỤ TRÁCH / NGƯỜI DUYỆT)', COLS);
    row = writeGuideLines(ws, row, [
        'Ghi đúng họ tên như bảng dưới (không cần mã nhân viên). Hệ thống tìm gần đúng nếu thiếu dấu.',
        'Một task import chỉ gán một người phụ trách chính qua file này.',
    ], COLS);
    const staff = (employees || []).slice(0, 60);
    if (!staff.length) {
        setCell(ws, row, 0, '— Chưa có danh sách nhân sự — Có thể để trống cột Người phụ trách.', S.guide);
        mergeRow(ws, row, 0, COLS);
        row++;
    } else {
        setCell(ws, row, 0, 'STT', S.header);
        setCell(ws, row, 1, 'Họ tên', S.header);
        setCell(ws, row, 2, 'STT', S.header);
        setCell(ws, row, 3, 'Họ tên', S.header);
        setCell(ws, row, 4, 'STT', S.header);
        setCell(ws, row, 5, 'Họ tên', S.header);
        row++;
        staff.forEach((e, i) => {
            const col = (i % 3) * 2;
            const r = row + Math.floor(i / 3);
            const st = Math.floor(i / 3) % 2 === 0 ? S.cell : S.cellAlt;
            setCell(ws, r, col, String(i + 1), st);
            setCell(ws, r, col + 1, e.name, st);
        });
        row += Math.ceil(staff.length / 3);
    }
    row++;

    row = writeSectionTitle(ws, row, 'IX. LỖI THƯỜNG GẶP & CÁCH XỬ LÝ', COLS);
    row = writeGuideLines(ws, row, [
        '• "Không tìm thấy dòng tiêu đề" → Dùng đúng file mẫu VA, không xóa dòng 5 (header) trên sheet Nhập liệu.',
        '• "Sprint không tồn tại" → Kiểm tra chính tả tên sprint; tạo sprint trên web trước rồi tải lại file mẫu.',
        '• "Không tìm thấy nhân sự" → So khớp họ tên với mục VIII; tránh nickname nếu hệ thống không có.',
        '• "Trạng thái / Ưu tiên không hợp lệ" → Dùng mã tiếng Anh trong bảng IV, V.',
        '• "Thiếu tiêu đề" → Cột Tiêu đề (*) không được để trống.',
        '• File quá 200 dòng → Chia thành nhiều lần import.',
        '• Ngày không hợp lệ → Dùng DD/MM/YYYY (vd: 05/03/2026), tránh text như "TBD".',
        '• Import xong không thấy task → Kiểm tra tab Hợp lệ khi xem trước; chỉ dòng hợp lệ mới được tạo.',
    ], COLS);
    row++;

    setCell(ws, row, 0, 'Hỗ trợ: Liên hệ PM dự án hoặc quản trị hệ thống QLDA nếu cần import số lượng lớn / tích hợp tự động.', S.note);
    mergeRow(ws, row, 0, COLS);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: row, c: COLS } });
    setColWidths(ws, [18, 12, 14, 14, 28, 12, 22, 10, 10, 10]);
    ws['!rows'] = [{ hpt: 30 }, { hpt: 18 }];
    return ws;
}

function buildDataSheet({ projectCode, projectName }) {
    const ws = {};
    const COLS = SPRINT_IMPORT_HEADERS.length - 1;

    setCell(ws, 0, 0, `NHẬP TASK — ${projectName || projectCode}`, S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, `Mã dự án: ${projectCode} · Dòng 5 = tiêu đề cột · Dòng 6–7 = mẫu (xóa trước khi import) · Nhập từ dòng 8`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);
    setCell(ws, 2, 0, '⚠ Không đổi tên/thứ tự cột. Chỉ cột "Tiêu đề *" bắt buộc. Xem chi tiết sheet "Huong dan".', S.note);
    mergeRow(ws, 2, 0, COLS);
    setCell(ws, 3, 0, SPRINT_IMPORT_MARKER, S.subtitle);
    mergeRow(ws, 3, 0, COLS);

    const headerRow = 4;
    SPRINT_IMPORT_HEADERS.forEach((h, c) => {
        setCell(ws, headerRow, c, h.label, h.key === 'title' ? S.required : S.header);
    });

    const samples = [
        ['Thiết kế màn hình đăng nhập', 'Sprint 1', 'in_progress', 'high', '', 'Nguyễn Văn A', '', '01/06/2026', '15/06/2026', '8', '40', 'Mockup Figma'],
        ['Viết API đăng nhập', 'Sprint 1', 'todo', 'medium', '', '', 'Trần Thị B', '', '20/06/2026', '16', '0', ''],
    ];
    samples.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        row.forEach((val, c) => setCell(ws, r, c, val, S.sample));
    });

    const inputStart = headerRow + 1 + samples.length;
    for (let r = inputStart; r < inputStart + 50; r++) {
        SPRINT_IMPORT_HEADERS.forEach((_, c) => setCell(ws, r, c, '', S.input));
    }

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: inputStart + 49, c: COLS } });
    setColWidths(ws, SPRINT_IMPORT_HEADERS.map((h) => h.width));
    ws['!rows'] = [{ hpt: 26 }, { hpt: 16 }, { hpt: 20 }];
    return ws;
}

function buildSprintListSheet(sprints) {
    const ws = {};
    setCell(ws, 0, 0, 'DANH SÁCH SPRINT — THAM CHIẾU KHI NHẬP', S.title);
    mergeRow(ws, 0, 0, 5);
    setCell(ws, 1, 0, 'Copy chính xác tên cột "Sprint" sang sheet "Nhap lieu". Để trống Sprint = Backlog.', S.subtitle);
    mergeRow(ws, 1, 0, 5);
    ['Tên Sprint', 'Trạng thái', 'Bắt đầu', 'Kết thúc', 'Mục tiêu'].forEach((h, c) => setCell(ws, 3, c, h, S.header));
    (sprints || []).forEach((s, i) => {
        const r = 4 + i;
        const st = i % 2 === 0 ? S.cell : S.cellAlt;
        setCell(ws, r, 0, s.name, st);
        setCell(ws, r, 1, s.status?.label || '', st);
        setCell(ws, r, 2, date(s.start_date) || '', st);
        setCell(ws, r, 3, date(s.end_date) || '', st);
        setCell(ws, r, 4, s.goal || '', st);
    });
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 4 + (sprints?.length || 0), c: 5 } });
    setColWidths(ws, [24, 14, 12, 12, 36]);
    return ws;
}

export function downloadSprintImportTemplate({
    projectCode = 'PRJ',
    projectName = '',
    sprints = [],
    employees = [],
    statusOptions = [],
    priorityOptions = [],
    phaseOptions = [],
}) {
    const code = safeCode(projectCode);
    const wb = XLSX.utils.book_new();
    const meta = { projectCode: code, projectName, sprints, employees, statusOptions, priorityOptions, phaseOptions };
    XLSX.utils.book_append_sheet(wb, buildGuideSheet(meta), 'Huong dan');
    XLSX.utils.book_append_sheet(wb, buildDataSheet(meta), 'Nhap lieu');
    if (sprints.length) XLSX.utils.book_append_sheet(wb, buildSprintListSheet(sprints), 'Danh sach Sprint');
    XLSX.writeFile(wb, `VA_MauNhap_Sprint_${code}.xlsx`);
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
    const norm = (n) => normalizeKey(n).replace(/\s/g, '');
    return (
        names.find((n) => norm(n) === 'nhaplieu')
        ?? names.find((n) => /nhap|nhập/i.test(n) && !/huong|guide|danh/i.test(n))
        ?? names[names.length - 1]
        ?? names[0]
    );
}

function isHeaderRow(row) {
    if (!Array.isArray(row)) return false;
    return row.some((c) => normalizeKey(c).replace(/\*/g, '').includes('tieu de'));
}

function findHeaderIndex(rows) {
    for (let i = 0; i < Math.min(rows.length, 40); i++) {
        if (rows[i]?.some((c) => String(c).trim() === SPRINT_IMPORT_MARKER)) return i + 1;
    }
    for (let i = 0; i < Math.min(rows.length, 40); i++) {
        if (isHeaderRow(rows[i])) return i;
    }
    return isHeaderRow(rows[4]) ? 4 : -1;
}

function columnIndexMap(headerRow) {
    const map = {};
    headerRow.forEach((cell, idx) => {
        const key = normalizeKey(cell).replace(/\*/g, '').trim();
        if (key.includes('tieu de') || key === 'title') map.title = idx;
        else if (key.includes('sprint')) map.sprint = idx;
        else if (key.includes('trang thai') || key === 'status') map.status = idx;
        else if (key.includes('uu tien') || key.includes('priority')) map.priority = idx;
        else if (key.includes('giai doan') || key.includes('phase')) map.phase = idx;
        else if (key.includes('nguoi phu trach') || key.includes('assignee')) map.assignee = idx;
        else if (key.includes('nguoi duyet') || key.includes('reviewer')) map.reviewer = idx;
        else if (key.includes('bat dau') || key.includes('start')) map.start_date = idx;
        else if (key.includes('han') || key.includes('due')) map.due_date = idx;
        else if (key.includes('gio') || key.includes('estimate')) map.estimate_hours = idx;
        else if (key.includes('tien do') || key.includes('progress')) map.progress = idx;
        else if (key.includes('mo ta') || key.includes('description')) map.description = idx;
    });
    return map;
}

function cellVal(row, idx) {
    if (idx == null || idx < 0) return '';
    const v = row[idx];
    return v == null ? '' : String(v).trim();
}

export async function parseSprintImportFile(file) {
    const buf = await file.arrayBuffer();
    const wb = XLSX.read(buf, { type: 'array', cellDates: true });
    const sheet = wb.Sheets[pickImportSheet(wb)];
    const matrix = readSheetMatrix(sheet);
    const headerIdx = findHeaderIndex(matrix);

    if (headerIdx < 0) {
        return { rows: [], errors: ['Không tìm thấy dòng tiêu đề. Hãy dùng file mẫu VA.'] };
    }

    const colMap = columnIndexMap(matrix[headerIdx]);
    if (colMap.title == null) {
        return { rows: [], errors: ['File thiếu cột "Tiêu đề". Tải lại file mẫu.'] };
    }

    const rows = [];
    const errors = [];
    const SAMPLE = new Set([
        normalizeKey('Thiết kế màn hình đăng nhập'),
        normalizeKey('Viết API đăng nhập'),
    ]);

    for (let i = headerIdx + 1; i < matrix.length; i++) {
        const row = matrix[i];
        if (!Array.isArray(row)) continue;
        const title = cellVal(row, colMap.title);
        if (!title && !cellVal(row, colMap.sprint)) continue;
        if (!title) continue;
        if (SAMPLE.has(normalizeKey(title))) continue;

        rows.push({
            line: i + 1,
            title,
            sprint_raw: cellVal(row, colMap.sprint),
            status_raw: cellVal(row, colMap.status),
            priority_raw: cellVal(row, colMap.priority),
            phase_raw: cellVal(row, colMap.phase),
            assignee_raw: cellVal(row, colMap.assignee),
            reviewer_raw: cellVal(row, colMap.reviewer),
            start_date_raw: cellVal(row, colMap.start_date),
            due_date_raw: cellVal(row, colMap.due_date),
            estimate_hours_raw: cellVal(row, colMap.estimate_hours),
            progress_raw: cellVal(row, colMap.progress),
            description: cellVal(row, colMap.description) || null,
        });
    }

    if (!rows.length) {
        errors.push('Chưa có dòng dữ liệu. Thêm ít nhất một dòng từ dòng 8 trong sheet "Nhập liệu".');
    }
    if (rows.length > 200) {
        errors.push(`File có ${rows.length} dòng — tối đa 200 dòng/lần.`);
    }

    return { rows: rows.slice(0, 200), errors };
}

export function validateSprintImportRows(rawRows, ctx) {
    const { sprints = [], employees = [], statusOptions = [], priorityOptions = [], phaseOptions = [] } = ctx;
    const allowedStatus = new Set(statusOptions.map((o) => o.value));
    const allowedPriority = new Set(priorityOptions.map((o) => o.value));
    const allowedPhase = new Set(phaseOptions.map((o) => o.value));

    return rawRows.map((raw) => {
        const errors = [];
        const title = raw.title?.trim() ?? '';
        if (!title) errors.push('Thiếu tiêu đề');
        else if (title.length > 255) errors.push('Tiêu đề tối đa 255 ký tự');

        const sprintRes = resolveSprint(raw.sprint_raw, sprints);
        if (raw.sprint_raw && sprintRes.error) errors.push(sprintRes.error);

        let status = mapAlias(raw.status_raw, STATUS_ALIASES, 'todo');
        if (raw.status_raw && !status) errors.push(`Trạng thái không hợp lệ: "${raw.status_raw}"`);
        else if (status && !allowedStatus.has(status)) status = 'todo';

        let priority = mapAlias(raw.priority_raw, PRIORITY_ALIASES, 'medium');
        if (raw.priority_raw && !priority) errors.push(`Ưu tiên không hợp lệ: "${raw.priority_raw}"`);
        else if (priority && !allowedPriority.has(priority)) priority = 'medium';

        let phase = null;
        if (raw.phase_raw) {
            const pk = normalizeKey(raw.phase_raw);
            const found = phaseOptions.find((o) => o.value === raw.phase_raw || normalizeKey(o.label) === pk);
            phase = found?.value ?? null;
            if (!phase && allowedPhase.has(raw.phase_raw)) phase = raw.phase_raw;
        }

        const assigneeRes = resolveEmployee(raw.assignee_raw, employees);
        if (raw.assignee_raw && assigneeRes.error) errors.push(assigneeRes.error);

        const reviewerRes = resolveEmployee(raw.reviewer_raw, employees);
        if (raw.reviewer_raw && reviewerRes.error) errors.push(reviewerRes.error);

        const start_date = parseExcelDate(raw.start_date_raw);
        const due_date = parseExcelDate(raw.due_date_raw);
        let progress = parseInt(raw.progress_raw, 10);
        if (Number.isNaN(progress)) progress = 0;
        progress = Math.min(100, Math.max(0, progress));

        let estimate_hours = raw.estimate_hours_raw ? parseFloat(raw.estimate_hours_raw) : null;
        if (estimate_hours != null && Number.isNaN(estimate_hours)) {
            errors.push('Giờ ước tính không hợp lệ');
            estimate_hours = null;
        }

        return {
            line: raw.line,
            valid: errors.length === 0,
            errors,
            edit: {
                title,
                sprint_id: sprintRes.id,
                sprint_name: sprintRes.name || raw.sprint_raw || 'Backlog',
                status: status || 'todo',
                priority: priority || 'medium',
                phase,
                assignee_id: assigneeRes.id,
                reviewer_id: reviewerRes.id,
                start_date,
                due_date,
                estimate_hours,
                progress,
                description: raw.description,
            },
        };
    });
}

export function createSprintPreviewRows(validated) {
    return validated.map((v) => ({
        line: v.line,
        valid: v.valid,
        errors: [...v.errors],
        ...v.edit,
    }));
}

export function revalidateSprintPreviewRow(row, ctx) {
    const [v] = validateSprintImportRows([{
        line: row.line,
        title: row.title,
        sprint_raw: row.sprint_name === 'Backlog' ? '' : row.sprint_name,
        status_raw: row.status,
        priority_raw: row.priority,
        phase_raw: row.phase,
        assignee_raw: ctx.employees?.find((e) => e.id === row.assignee_id)?.name || '',
        reviewer_raw: ctx.employees?.find((e) => e.id === row.reviewer_id)?.name || '',
        start_date_raw: row.start_date,
        due_date_raw: row.due_date,
        estimate_hours_raw: row.estimate_hours,
        progress_raw: row.progress,
        description: row.description,
    }], ctx);
    if (!v) return;
    row.valid = v.valid;
    row.errors = v.errors;
    Object.assign(row, v.edit);
}

export function sprintRowToPayload(row) {
    return {
        title: row.title,
        sprint_id: row.sprint_id,
        status: row.status,
        priority: row.priority,
        phase: row.phase || undefined,
        assignee_id: row.assignee_id,
        reviewer_id: row.reviewer_id,
        start_date: row.start_date,
        due_date: row.due_date,
        estimate_hours: row.estimate_hours,
        progress: row.progress,
        description: row.description,
    };
}

/** Bulk import payload for POST /projects/{id}/tasks/import */
export function sprintRowsToImportPayload(rows) {
    return {
        rows: rows.map((row) => sprintRowToPayload(row)),
    };
}

function styledTableSheet(title, subtitle, headers, dataRows) {
    const ws = {};
    const COLS = headers.length - 1;
    setCell(ws, 0, 0, title, S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, subtitle, S.subtitle);
    mergeRow(ws, 1, 0, COLS);
    const hr = 3;
    headers.forEach((h, c) => setCell(ws, hr, c, h, S.header));
    dataRows.forEach((row, i) => {
        const r = hr + 1 + i;
        const st = i % 2 === 0 ? S.cell : S.cellAlt;
        row.forEach((val, c) => setCell(ws, r, c, val ?? '', st));
    });
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: hr + dataRows.length, c: COLS } });
    setColWidths(ws, headers.map(() => 14));
    return ws;
}

/**
 * Xuất workbook định dạng: Tổng quan + Tasks + Sprints (+ Đối soát nếu có).
 */
export function exportSprintWorkbook({
    projectCode = 'PRJ',
    projectName = '',
    tasks = [],
    sprints = [],
    sprintById = new Map(),
    issues = [],
    format = 'xlsx',
}) {
    const code = safeCode(projectCode);
    const stamp = new Date().toISOString().slice(0, 10);
    const filename = `VA_Sprint_${code}_${stamp}.xlsx`;

    const taskRows = tasks.map((t, i) => [
        i + 1,
        t.id,
        t.title,
        t.sprint_id ? (sprintById.get(t.sprint_id)?.name || t.sprint_id) : 'Backlog',
        t.status?.label || '',
        t.priority?.label || '',
        t.phase?.label || '',
        getAssignees(t).map((a) => a.name).join(', '),
        t.reviewer?.name || '',
        date(t.start_date) || '',
        date(t.due_date) || '',
        t.estimate_hours ?? '',
        t.progress ?? 0,
        t.description || '',
    ]);

    const sprintRows = sprints.map((s, i) => [
        i + 1,
        s.id,
        s.name,
        s.goal || '',
        s.status?.label || '',
        date(s.start_date) || '',
        date(s.end_date) || '',
        s.progress ?? 0,
        s.task_count ?? '',
    ]);

    const wb = XLSX.utils.book_new();

    const overview = {};
    setCell(overview, 0, 0, `BÁO CÁO SPRINT — ${projectName}`, S.title);
    mergeRow(overview, 0, 0, 4);
    setCell(overview, 2, 0, 'Mã dự án', S.kpiLabel);
    setCell(overview, 2, 1, code, S.kpiValue);
    setCell(overview, 2, 2, 'Ngày xuất', S.kpiLabel);
    setCell(overview, 2, 3, stamp, S.kpiValue);
    setCell(overview, 4, 0, 'Sprint', S.kpiLabel);
    setCell(overview, 4, 1, sprints.length, S.kpiValue);
    setCell(overview, 4, 2, 'Task', S.kpiLabel);
    setCell(overview, 4, 3, tasks.length, S.kpiValue);
    overview['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: 6, c: 4 } });
    setColWidths(overview, [16, 18, 16, 18]);
    XLSX.utils.book_append_sheet(wb, overview, 'Tong quan');

    XLSX.utils.book_append_sheet(
        wb,
        styledTableSheet(
            'DANH SÁCH TASK',
            `${tasks.length} công việc · ${projectName}`,
            ['STT', 'ID', 'Tiêu đề', 'Sprint', 'Trạng thái', 'Ưu tiên', 'Giai đoạn', 'Người làm', 'Duyệt', 'Bắt đầu', 'Hạn', 'Giờ', '%', 'Mô tả'],
            taskRows,
        ),
        'Tasks',
    );

    XLSX.utils.book_append_sheet(
        wb,
        styledTableSheet(
            'DANH SÁCH SPRINT',
            `${sprints.length} sprint`,
            ['STT', 'ID', 'Tên', 'Mục tiêu', 'Trạng thái', 'Bắt đầu', 'Kết thúc', '%', 'Số task'],
            sprintRows,
        ),
        'Sprints',
    );

    if (issues?.length) {
        const issueRows = issues.map((iss, i) => [i + 1, iss.level, iss.code || '', iss.message]);
        XLSX.utils.book_append_sheet(
            wb,
            styledTableSheet('ĐỐI SOÁT', `${issues.length} phát hiện`, ['STT', 'Mức', 'Mã', 'Mô tả'], issueRows),
            'Doi soat',
        );
    }

    if (format === 'csv') {
        const ws = wb.Sheets.Tasks;
        XLSX.writeFile({ SheetNames: ['Tasks'], Sheets: { Tasks: ws } }, filename.replace('.xlsx', '.csv'));
    } else {
        XLSX.writeFile(wb, filename);
    }
    return filename;
}

/** @deprecated use exportSprintWorkbook */
export function exportSprintTasks(tasks, opts = {}) {
    return exportSprintWorkbook({ ...opts, tasks, sprints: opts.sprints || [] });
}

export function exportSprints(sprints, opts = {}) {
    return exportSprintWorkbook({ ...opts, sprints, tasks: [] });
}
