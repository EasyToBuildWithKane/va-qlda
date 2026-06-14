import XLSX from 'xlsx-js-style';
import { datetime } from '@/composables/useFormat';

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';

function borderThin() {
    return {
        top: { style: 'thin', color: { rgb: SLATE_200 } },
        bottom: { style: 'thin', color: { rgb: SLATE_200 } },
        left: { style: 'thin', color: { rgb: SLATE_200 } },
        right: { style: 'thin', color: { rgb: SLATE_200 } },
    };
}

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    const isNum = typeof value === 'number' && Number.isFinite(value);
    ws[ref] = { v: value ?? '', t: isNum ? 'n' : 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

const S = {
    title: {
        font: { bold: true, sz: 16, color: { rgb: BRAND } },
        alignment: { vertical: 'center' },
    },
    subtitle: {
        font: { sz: 10, color: { rgb: SLATE_600 }, italic: true },
    },
    section: {
        font: { bold: true, sz: 11, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        border: borderThin(),
    },
    header: {
        font: { bold: true, sz: 10, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
    cell: (alt) => ({
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: alt ? SLATE_50 : WHITE } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    }),
    money: (alt, bold = false) => ({
        font: { sz: 10, color: { rgb: bold ? BRAND : '334155' }, bold },
        fill: { fgColor: { rgb: alt ? SLATE_50 : WHITE } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
        numFmt: '#,##0',
    }),
    hours: (alt) => ({
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: alt ? SLATE_50 : WHITE } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
        numFmt: '#,##0.0',
    }),
};

function buildOverviewSheet({ month, monthly, summary }) {
    const ws = {};
    let row = 0;

    setCell(ws, row, 0, 'Báo cáo Coaching / Mentoring', S.title);
    mergeRow(ws, row, 0, 3);
    row += 1;
    setCell(ws, row, 0, `Kỳ báo cáo: tháng ${month} · Xuất lúc ${datetime(new Date().toISOString())}`, S.subtitle);
    mergeRow(ws, row, 0, 3);
    row += 2;

    if (summary) {
        setCell(ws, row, 0, 'Tổng quan hệ thống', S.section);
        mergeRow(ws, row, 0, 3);
        row += 1;
        const kpi = [
            ['Tổng khóa học', summary.courses_total],
            ['Khóa đang diễn ra', summary.courses_active],
            ['Tổng buổi học', summary.sessions_total],
            ['Tổng giờ đào tạo (hoàn thành)', summary.hours_total],
        ];
        kpi.forEach(([label, val], i) => {
            const alt = i % 2 === 1;
            setCell(ws, row, 0, label, S.cell(alt));
            setCell(ws, row, 1, val, typeof val === 'number' && label.includes('giờ') ? S.hours(alt) : S.cell(alt));
            mergeRow(ws, row, 1, 3);
            row += 1;
        });
        row += 1;
    }

    setCell(ws, row, 0, `Chỉ số tháng ${month}`, S.section);
    mergeRow(ws, row, 0, 3);
    row += 1;

    setCell(ws, row, 0, 'Chỉ số', S.header);
    setCell(ws, row, 1, 'Giá trị', S.header);
    mergeRow(ws, row, 1, 3);
    row += 1;

    const rows = [
        ['Tổng buổi học', monthly.sessions_total, 'int'],
        ['Buổi hoàn thành', monthly.sessions_completed, 'int'],
        ['Buổi hủy', monthly.sessions_cancelled, 'int'],
        ['Tổng giờ dạy', monthly.hours_total, 'hours'],
        ['Doanh thu (VNĐ)', monthly.revenue_total, 'money'],
        ['TB / giờ (VNĐ)', monthly.avg_per_hour, 'money'],
        ['TB / buổi (VNĐ)', monthly.avg_per_session, 'money'],
        ['Học viên (distinct)', monthly.students_distinct, 'int'],
    ];

    rows.forEach(([label, val, kind], i) => {
        const alt = i % 2 === 1;
        setCell(ws, row, 0, label, S.cell(alt));
        let style = S.cell(alt);
        let cellVal = val ?? '—';
        if (val != null && val !== '—') {
            if (kind === 'money') {
                style = S.money(alt, label.includes('Doanh thu'));
                cellVal = val;
            } else if (kind === 'hours') {
                style = S.hours(alt);
                cellVal = val;
            }
        }
        setCell(ws, row, 1, cellVal, style);
        mergeRow(ws, row, 1, 3);
        row += 1;
    });

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: row, c: 3 } });
    ws['!cols'] = [{ wch: 32 }, { wch: 18 }, { wch: 12 }, { wch: 12 }];
    return ws;
}

function buildSeriesSheet(revenueSeries) {
    const ws = {};
    setCell(ws, 0, 0, 'Tháng', S.header);
    setCell(ws, 0, 1, 'Doanh thu (VNĐ)', S.header);
    setCell(ws, 0, 2, 'Giờ dạy', S.header);

    revenueSeries.forEach((row, i) => {
        const r = i + 1;
        const alt = r % 2 === 0;
        setCell(ws, r, 0, row.month, S.cell(alt));
        setCell(ws, r, 1, row.revenue ?? 0, S.money(alt));
        setCell(ws, r, 2, row.hours ?? 0, S.hours(alt));
    });

    const last = Math.max(1, revenueSeries.length);
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: last, c: 2 } });
    ws['!cols'] = [{ wch: 14 }, { wch: 18 }, { wch: 12 }];
    return ws;
}

/**
 * @param {{ month: string, monthly: object, revenueSeries: array, summary?: object }} payload
 */
export function exportCoachingMonthlyWorkbook(payload) {
    const { month, monthly, revenueSeries = [], summary = null } = payload;
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, buildOverviewSheet({ month, monthly, summary }), 'Tong quan');
    XLSX.utils.book_append_sheet(wb, buildSeriesSheet(revenueSeries), '12 thang');

    const safeMonth = month.replace(/[^\d-]/g, '');
    XLSX.writeFile(wb, `VA_Coaching_${safeMonth}.xlsx`);
}

const COURSE_HEADERS = [
    'Mã', 'Tên khóa', 'Trạng thái', 'Học viên', 'Coach', 'Tiến độ %', 'Số buổi',
    'Bắt đầu', 'Kết thúc', 'Học phí', 'Đơn giá/giờ', 'Tổng giờ',
];

function courseRow(c) {
    return [
        c.code ?? '',
        c.name ?? '',
        c.status?.label ?? '',
        c.student_display ?? '',
        c.coach_display ?? '',
        c.progress_percent ?? '',
        c.sessions_count ?? '',
        c.start_date ?? '',
        c.end_date ?? '',
        c.total_fee ?? '',
        c.hourly_rate ?? '',
        c.total_hours ?? '',
    ];
}

function coursesToCsv(rows) {
    const esc = (v) => {
        const s = v == null ? '' : String(v);
        return s.includes(',') || s.includes('"') || s.includes('\n')
            ? `"${s.replace(/"/g, '""')}"`
            : s;
    };
    const lines = [COURSE_HEADERS.map(esc).join(',')];
    rows.forEach((c) => lines.push(courseRow(c).map(esc).join(',')));
    return lines.join('\n');
}

/**
 * @param {{ courses: array, filters?: object }} opts
 */
export function exportCoachingCoursesCsv({ courses, filters = {} }) {
    const blob = new Blob(['\uFEFF' + coursesToCsv(courses)], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const note = filters.q || filters.status ? '_loc' : '';
    a.download = `VA_Coaching_KhoaHoc${note}_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

/**
 * @param {{ courses: array, filters?: object }} opts
 */
export function exportCoachingCoursesWorkbook({ courses, filters = {} }) {
    const ws = {};
    let row = 0;
    setCell(ws, row, 0, 'Danh sách khóa học Coaching', S.title);
    mergeRow(ws, row, 0, COURSE_HEADERS.length - 1);
    row += 1;
    const filterParts = [];
    if (filters.q) filterParts.push(`Từ khóa: ${filters.q}`);
    if (filters.status) filterParts.push(`Trạng thái: ${filters.status}`);
    setCell(ws, row, 0, filterParts.length ? filterParts.join(' · ') : 'Không lọc', S.subtitle);
    mergeRow(ws, row, 0, COURSE_HEADERS.length - 1);
    row += 2;

    COURSE_HEADERS.forEach((h, c) => setCell(ws, row, c, h, S.header));
    row += 1;

    courses.forEach((course, ri) => {
        const full = courseRow(course);
        const alt = ri % 2 === 1;
        full.forEach((val, c) => {
            const isMoney = c >= 9 && c <= 10 && val != null && val !== '';
            setCell(ws, row, c, val, isMoney ? S.money(alt) : S.cell(alt));
        });
        row += 1;
    });

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: Math.max(row, 1), c: COURSE_HEADERS.length - 1 },
    });
    ws['!cols'] = COURSE_HEADERS.map(() => ({ wch: 16 }));

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Khoa hoc');
    XLSX.writeFile(wb, `VA_Coaching_KhoaHoc_${new Date().toISOString().slice(0, 10)}.xlsx`);
}

const SESSION_HEADERS = [
    'STT buổi', 'Tên buổi', 'Mã khóa', 'Tên khóa', 'Ngày', 'Giờ bắt đầu', 'Giờ kết thúc',
    'Tổng giờ', 'Trạng thái', 'Chủ đề', 'Số tài liệu', 'Số bài tập', 'Ghi chú ngắn',
];

function sessionDisplay(val, empty = 'Chưa có') {
    if (val == null || val === '') return empty;
    return val;
}

function sessionRow(s) {
    const matCount = s.materials_count ?? s.materials?.length;
    const assignCount = s.assignments_count ?? s.assignments?.length;
    return [
        s.session_number ?? '',
        s.title ?? '',
        s.course?.code ?? '',
        s.course?.name ?? '',
        s.date ?? '',
        s.start_time ?? '',
        s.end_time ?? '',
        s.total_hours ?? '',
        s.status?.label ?? '',
        sessionDisplay(s.topic, 'Chưa có chủ đề'),
        matCount != null && matCount > 0 ? matCount : 'Chưa có tài liệu',
        assignCount != null && assignCount > 0 ? assignCount : 'Chưa có bài tập',
        sessionDisplay(s.notes, 'Chưa có ghi chú'),
    ];
}

function sessionsToCsv(rows) {
    const esc = (v) => {
        const s = v == null ? '' : String(v);
        return s.includes(',') || s.includes('"') || s.includes('\n')
            ? `"${s.replace(/"/g, '""')}"`
            : s;
    };
    const lines = [SESSION_HEADERS.map(esc).join(',')];
    rows.forEach((s) => lines.push(sessionRow(s).map(esc).join(',')));
    return lines.join('\n');
}

/**
 * @param {{ sessions: array, filters?: object }} opts
 */
export function exportCoachingSessionsCsv({ sessions, filters = {} }) {
    const blob = new Blob(['\uFEFF' + sessionsToCsv(sessions)], { type: 'text/csv;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    const note = Object.values(filters).some(Boolean) ? '_loc' : '';
    a.download = `VA_Coaching_BuoiHoc${note}_${new Date().toISOString().slice(0, 10)}.csv`;
    a.click();
    URL.revokeObjectURL(url);
}

function filterSummarySessions(filters) {
    const parts = [];
    if (filters.q) parts.push(`Từ khóa: ${filters.q}`);
    if (filters.status) parts.push(`Trạng thái: ${filters.status}`);
    if (filters.course) parts.push(`Khóa học ID: ${filters.course}`);
    if (filters.date_from) parts.push(`Từ ngày: ${filters.date_from}`);
    if (filters.date_to) parts.push(`Đến ngày: ${filters.date_to}`);
    if (filters.has_materials === '1') parts.push('Có tài liệu');
    if (filters.has_materials === '0') parts.push('Chưa có tài liệu');
    if (filters.has_assignments === '1') parts.push('Có bài tập');
    if (filters.has_assignments === '0') parts.push('Chưa có bài tập');
    if (filters.scheduled === '1') parts.push('Đã có ngày học');
    if (filters.scheduled === '0') parts.push('Chưa lên lịch');
    return parts.length ? parts.join(' · ') : 'Không lọc';
}

function buildSessionsOverviewSheet({ summary, filters, scopeLabel, exportedCount }) {
    const ws = {};
    let row = 0;
    setCell(ws, row, 0, 'Báo cáo buổi học Coaching', S.title);
    mergeRow(ws, row, 0, 3);
    row += 1;
    setCell(ws, row, 0, filterSummarySessions(filters), S.subtitle);
    mergeRow(ws, row, 0, 3);
    row += 1;
    setCell(ws, row, 0, `${scopeLabel} · ${exportedCount} buổi · Xuất ${datetime(new Date().toISOString())}`, S.subtitle);
    mergeRow(ws, row, 0, 3);
    row += 2;

    if (summary) {
        const kpi = [
            ['Tổng buổi (theo lọc)', summary.total],
            ['Số khóa học', summary.courses],
            ['Tổng giờ ghi nhận', summary.hours_total],
            ['Hoàn thành', summary.completed],
            ['Đang học', summary.in_progress],
            ['Chưa học', summary.pending],
            ['Đã hủy', summary.cancelled],
            ['Chưa lên lịch', summary.unscheduled],
            ['Có tài liệu', summary.with_materials],
            ['Có bài tập', summary.with_assignments],
        ];
        kpi.forEach(([label, val], i) => {
            const alt = i % 2 === 1;
            setCell(ws, row, 0, label, S.cell(alt));
            const isHours = label.includes('giờ') && typeof val === 'number';
            setCell(ws, row, 1, val ?? 0, isHours ? S.hours(alt) : S.cell(alt));
            mergeRow(ws, row, 1, 3);
            row += 1;
        });
    }

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: Math.max(row, 1), c: 3 } });
    ws['!cols'] = [{ wch: 28 }, { wch: 16 }, { wch: 12 }, { wch: 12 }];
    return ws;
}

function buildSessionsDataSheet(sessions) {
    const ws = {};
    let row = 0;
    SESSION_HEADERS.forEach((h, c) => setCell(ws, row, c, h, S.header));
    row += 1;

    sessions.forEach((session, ri) => {
        const full = sessionRow(session);
        const alt = ri % 2 === 1;
        full.forEach((val, c) => {
            const isHours = c === 7 && typeof val === 'number';
            setCell(ws, row, c, val, isHours ? S.hours(alt) : S.cell(alt));
        });
        row += 1;
    });

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: Math.max(row, 1), c: SESSION_HEADERS.length - 1 },
    });
    ws['!cols'] = [
        { wch: 8 }, { wch: 28 }, { wch: 12 }, { wch: 24 }, { wch: 12 },
        { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 14 }, { wch: 24 },
        { wch: 14 }, { wch: 14 }, { wch: 20 },
    ];
    return ws;
}

function buildSessionsByCourseSheet(sessions) {
    const ws = {};
    let row = 0;
    const groups = new Map();
    sessions.forEach((s) => {
        const key = s.course?.code ?? 'NA';
        if (!groups.has(key)) {
            groups.set(key, { label: s.course ? `${s.course.code} — ${s.course.name}` : 'Chưa gán khóa', items: [] });
        }
        groups.get(key).items.push(s);
    });

    [...groups.values()].sort((a, b) => a.label.localeCompare(b.label, 'vi')).forEach((group) => {
        setCell(ws, row, 0, group.label, S.section);
        mergeRow(ws, row, 0, SESSION_HEADERS.length - 1);
        row += 1;
        SESSION_HEADERS.forEach((h, c) => setCell(ws, row, c, h, S.header));
        row += 1;
        group.items.forEach((session, ri) => {
            const full = sessionRow(session);
            const alt = ri % 2 === 1;
            full.forEach((val, c) => {
                const isHours = c === 7 && typeof val === 'number';
                setCell(ws, row, c, val, isHours ? S.hours(alt) : S.cell(alt));
            });
            row += 1;
        });
        row += 1;
    });

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: Math.max(row, 1), c: SESSION_HEADERS.length - 1 },
    });
    return ws;
}

/**
 * @param {{ sessions: array, filters?: object, summary?: object, scopeLabel?: string }} opts
 */
export function exportCoachingSessionsWorkbook({
    sessions,
    filters = {},
    summary = null,
    scopeLabel = 'Trang hiện tại',
}) {
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(
        wb,
        buildSessionsOverviewSheet({ summary, filters, scopeLabel, exportedCount: sessions.length }),
        'Tong quan',
    );
    XLSX.utils.book_append_sheet(wb, buildSessionsDataSheet(sessions), 'Danh sach');
    if (sessions.length > 0) {
        XLSX.utils.book_append_sheet(wb, buildSessionsByCourseSheet(sessions), 'Theo khoa');
    }
    XLSX.writeFile(wb, `VA_Coaching_BuoiHoc_${new Date().toISOString().slice(0, 10)}.xlsx`);
}
