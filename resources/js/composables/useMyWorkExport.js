import XLSX from 'xlsx-js-style';
import { date, datetime } from '@/composables/useFormat';

/**
 * Xuất Excel cho màn hình "Việc của tôi" (/my-work).
 *
 * Hai báo cáo, cùng bộ style thương hiệu (mirror useRiskExport — tham chiếu vàng):
 *   - exportMyWorkTasks  : danh sách việc cá nhân / thành viên (gộp mọi bucket) +
 *                          dải KPI + tô màu quá hạn / ưu tiên.
 *   - exportTeamRoster   : bảng tải việc theo từng thành viên trong phạm vi nhóm.
 *
 * Mọi business rule (parse, KPI, style) ở composable — .vue chỉ gọi hàm.
 */

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

const S = {
    title: {
        font: { bold: true, sz: 16, color: { rgb: BRAND } },
        alignment: { horizontal: 'left', vertical: 'center' },
    },
    subtitle: {
        font: { sz: 10, color: { rgb: SLATE_600 }, italic: true },
        alignment: { horizontal: 'left' },
    },
    kpiLabel: {
        font: { bold: true, sz: 9, color: { rgb: SLATE_600 } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    kpiValue: {
        font: { bold: true, sz: 13, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
    sectionBand: {
        font: { bold: true, sz: 10, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: SLATE_600 } },
        alignment: { horizontal: 'left', vertical: 'center' },
        border: borderThin(),
    },
    header: {
        font: { bold: true, sz: 10, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    cell: {
        font: { sz: 10, color: { rgb: '334155' } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    cellAlt: {
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    num: {
        font: { sz: 10, color: { rgb: '334155' } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
    numAlt: {
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
    total: {
        font: { bold: true, sz: 10, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: borderThin(),
    },
    totalLabel: {
        font: { bold: true, sz: 10, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
    },
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

function fileStamp() {
    const t = new Date();
    const dd = String(t.getDate()).padStart(2, '0');
    const mm = String(t.getMonth() + 1).padStart(2, '0');
    const yyyy = t.getFullYear();
    return { dd, mm, yyyy };
}

function safeName(value) {
    return String(value ?? 'VA')
        .normalize('NFD')
        .replace(/[̀-ͯ]/g, '')
        .replace(/đ/g, 'd')
        .replace(/Đ/g, 'D')
        .replace(/[^a-zA-Z0-9]+/g, '_')
        .replace(/^_+|_+$/g, '')
        .slice(0, 40) || 'VA';
}

function fmtHours(h) {
    if (h == null || h <= 0) return '—';
    return `${Number.isInteger(h) ? h : Number(h).toFixed(1)}h`;
}

function overdueDays(row) {
    if (!row.due_date || !row.is_late) return 0;
    const due = new Date(`${row.due_date}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.max(0, Math.round((today - due) / 86400000));
}

// ── KPI strip dùng chung ──────────────────────────────────────────────────────
function writeKpiStrip(ws, rowIndex, pairs, colsTotal) {
    // pairs: [{label, value}]; mỗi cặp chiếm 2 cột, cân đều trên độ rộng bảng.
    const span = Math.max(2, Math.floor((colsTotal + 1) / pairs.length));
    pairs.forEach(({ label, value }, i) => {
        const c0 = i * span;
        const labelEnd = c0 + Math.max(1, Math.floor(span / 2)) - 1;
        const valStart = labelEnd + 1;
        const valEnd = c0 + span - 1;
        setCell(ws, rowIndex, c0, label, S.kpiLabel);
        if (labelEnd > c0) mergeRow(ws, rowIndex, c0, labelEnd);
        setCell(ws, rowIndex, valStart, value, S.kpiValue);
        if (valEnd > valStart) mergeRow(ws, rowIndex, valStart, valEnd);
    });
}

// ════════════════════════════════════════════════════════════════════════════
//  BÁO CÁO 1 — Danh sách việc cá nhân / thành viên
// ════════════════════════════════════════════════════════════════════════════

const TASK_HEADERS = [
    'STT', 'Nhóm thời gian', 'Dự án', 'Công việc', 'Ưu tiên', 'Trạng thái',
    'Giai đoạn', 'Bắt đầu', 'Hạn hoàn thành', 'Quá hạn (ngày)',
    'Ước tính (h)', 'Tiến độ', 'Sprint', 'Giờ hôm nay',
];

const BUCKET_LABELS = {
    overdue: 'Quá hạn',
    today: 'Hôm nay',
    upcoming: 'Sắp tới',
    no_due: 'Chưa có hạn',
};

const BUCKET_ORDER = ['overdue', 'today', 'upcoming', 'no_due'];

function flattenBuckets(buckets) {
    const rows = [];
    BUCKET_ORDER.forEach((key) => {
        (buckets?.[key] ?? []).forEach((task) => rows.push({ bucket: key, task }));
    });
    return rows;
}

function taskRowToArray(bucketKey, t, idx) {
    const projectLabel = t.project
        ? [t.project.code, t.project.name].filter(Boolean).join(' · ')
        : '—';
    const od = overdueDays(t);
    return [
        idx + 1,
        BUCKET_LABELS[bucketKey] ?? '—',
        projectLabel,
        t.title ?? '—',
        t.priority?.label ?? '—',
        t.status?.label ?? '—',
        t.phase?.label ?? '—',
        date(t.start_date) || '—',
        date(t.due_date) || '—',
        od > 0 ? od : '—',
        fmtHours(t.estimate_hours),
        t.progress != null ? `${t.progress}%` : '—',
        t.sprint?.name ?? '—',
        t.logged_today > 0 ? fmtHours(t.logged_today) : '—',
    ];
}

const PRIORITY_FILL = {
    critical: { color: 'BE123C', fill: 'FFE4E6', bold: true },
    urgent: { color: 'BE123C', fill: 'FFE4E6', bold: true },
    high: { color: 'C2410C', fill: 'FFEDD5', bold: true },
    medium: { color: 'B45309', fill: 'FEF3C7', bold: false },
};

function applyPriorityStyle(ws, r, c, value, baseStyle) {
    const conf = PRIORITY_FILL[value];
    if (!conf) return;
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref].s = {
        ...baseStyle,
        font: { sz: 10, bold: conf.bold, color: { rgb: conf.color } },
        fill: { fgColor: { rgb: conf.fill } },
    };
}

function computeTaskKpis(rows) {
    const tasks = rows.map((r) => r.task);
    return {
        total: tasks.length,
        overdue: rows.filter((r) => r.bucket === 'overdue').length,
        today: rows.filter((r) => r.bucket === 'today').length,
        upcoming: rows.filter((r) => r.bucket === 'upcoming').length,
        inProgress: tasks.filter((t) => t.status?.value === 'in_progress').length,
    };
}

function buildTasksXlsx(rows, { ownerName, summary }) {
    const ws = {};
    const COLS = TASK_HEADERS.length - 1;
    const exportedAt = datetime(new Date().toISOString());
    const kpis = computeTaskKpis(rows);

    setCell(ws, 0, 0, `VIỆC CỦA ${(ownerName || 'TÔI').toUpperCase()}`, S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, `Xuất lúc ${exportedAt} · Mẫu báo cáo VAschools · QLDA`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);

    writeKpiStrip(ws, 3, [
        { label: 'Tổng việc', value: kpis.total },
        { label: 'Quá hạn', value: summary?.overdue ?? kpis.overdue },
        { label: 'Hôm nay', value: summary?.dueToday ?? kpis.today },
        { label: 'Đang làm', value: summary?.inProgress ?? kpis.inProgress },
        { label: 'Giờ log hôm nay', value: summary?.hoursLoggedToday ?? 0 },
    ], COLS);

    const headerRow = 5;
    TASK_HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h, S.header));

    const CENTERED = new Set([0, 9, 10, 11, 13]); // STT, quá hạn, ước tính, tiến độ, giờ
    rows.forEach(({ bucket, task }, idx) => {
        const r = headerRow + 1 + idx;
        const alt = idx % 2 === 1;
        const values = taskRowToArray(bucket, task, idx);
        values.forEach((val, c) => {
            const style = CENTERED.has(c) ? (alt ? S.numAlt : S.num) : (alt ? S.cellAlt : S.cell);
            setCell(ws, r, c, val, style);
        });
        applyPriorityStyle(ws, r, 4, task.priority?.value, alt ? S.cellAlt : S.cell);
        if (bucket === 'overdue') {
            const ref = XLSX.utils.encode_cell({ r, c: 9 });
            ws[ref].s = { ...(alt ? S.numAlt : S.num), font: { bold: true, sz: 10, color: { rgb: 'BE123C' } } };
        }
    });

    const totalRow = headerRow + 1 + rows.length;
    setCell(ws, totalRow, 0, 'Tổng', S.totalLabel);
    mergeRow(ws, totalRow, 0, 2);
    setCell(ws, totalRow, 3, `${rows.length} việc`, S.total);
    for (let c = 4; c <= COLS; c += 1) setCell(ws, totalRow, c, '', S.total);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: COLS } });
    setColWidths(ws, [5, 14, 28, 40, 12, 14, 14, 12, 14, 12, 11, 10, 18, 11]);
    ws['!rows'] = [{ hpt: 26 }, { hpt: 16 }, null, { hpt: 24 }, null, { hpt: 26 }];
    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: headerRow, c: 0 }, e: { r: Math.max(headerRow, totalRow - 1), c: COLS } }) };
    ws['!freeze'] = { xSplit: 0, ySplit: headerRow + 1 };

    return ws;
}

/**
 * @param {{ buckets: object, ownerName?: string, summary?: object }} opts
 */
export function exportMyWorkTasks({ buckets, ownerName = 'Tôi', summary = null }) {
    const rows = flattenBuckets(buckets);
    if (!rows.length) return false;

    const ws = buildTasksXlsx(rows, { ownerName, summary });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'VA - Viec cua toi');

    const { dd, mm, yyyy } = fileStamp();
    XLSX.writeFile(wb, `VA_ViecCuaToi_${safeName(ownerName)}_${dd}${mm}${yyyy}.xlsx`);
    return true;
}

// ════════════════════════════════════════════════════════════════════════════
//  BÁO CÁO 2 — Tải việc theo thành viên nhóm
// ════════════════════════════════════════════════════════════════════════════

const ROSTER_HEADERS = [
    'STT', 'Thành viên', 'Vai trò', 'Nhóm tổ chức', 'Phòng ban',
    'Việc mở', 'Quá hạn', 'Hôm nay', 'Sắp tới', 'Đang làm', 'Chưa có hạn', 'Tình trạng',
];

function memberStatusLabel(m) {
    if ((m.overdue ?? 0) > 0) return 'Có việc quá hạn';
    if ((m.dueToday ?? 0) > 0) return 'Đến hạn hôm nay';
    if ((m.open ?? 0) === 0) return 'Không còn việc mở';
    return 'Bình thường';
}

function rosterRowToArray(m, idx) {
    return [
        idx + 1,
        m.name ?? '—',
        m.role_title ?? '—',
        m.org_team_name ?? m.org_unit_name ?? '—',
        m.departments?.[0]?.name ?? m.org_section_title ?? m.group_department ?? '—',
        m.open ?? 0,
        m.overdue ?? 0,
        m.dueToday ?? 0,
        m.upcoming ?? 0,
        m.inProgress ?? 0,
        m.noDue ?? 0,
        memberStatusLabel(m),
    ];
}

function buildRosterXlsx(members, { summary, scopeLabel }) {
    const ws = {};
    const COLS = ROSTER_HEADERS.length - 1;
    const exportedAt = datetime(new Date().toISOString());

    const sorted = [...members].sort((a, b) => {
        const ra = (a.overdue ?? 0) * 100 + (a.dueToday ?? 0) * 10 + (a.open ?? 0);
        const rb = (b.overdue ?? 0) * 100 + (b.dueToday ?? 0) * 10 + (b.open ?? 0);
        if (rb !== ra) return rb - ra;
        return (a.name ?? '').localeCompare(b.name ?? '', 'vi');
    });

    setCell(ws, 0, 0, 'TẢI VIỆC THEO THÀNH VIÊN NHÓM', S.title);
    mergeRow(ws, 0, 0, COLS);
    setCell(ws, 1, 0, `${scopeLabel ? scopeLabel + ' · ' : ''}Xuất lúc ${exportedAt} · Mẫu báo cáo VAschools`, S.subtitle);
    mergeRow(ws, 1, 0, COLS);

    const atRisk = sorted.filter((m) => (m.overdue ?? 0) > 0 || (m.dueToday ?? 0) > 0).length;
    writeKpiStrip(ws, 3, [
        { label: 'Thành viên', value: summary?.members ?? sorted.length },
        { label: 'Tổng việc mở', value: summary?.open ?? 0 },
        { label: 'Quá hạn', value: summary?.overdue ?? 0 },
        { label: 'Hôm nay', value: summary?.dueToday ?? 0 },
        { label: 'Cần lưu ý', value: summary?.atRisk ?? atRisk },
    ], COLS);

    const headerRow = 5;
    ROSTER_HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h, S.header));

    const CENTERED = new Set([0, 5, 6, 7, 8, 9, 10]);
    sorted.forEach((m, idx) => {
        const r = headerRow + 1 + idx;
        const alt = idx % 2 === 1;
        const values = rosterRowToArray(m, idx);
        values.forEach((val, c) => {
            const style = CENTERED.has(c) ? (alt ? S.numAlt : S.num) : (alt ? S.cellAlt : S.cell);
            setCell(ws, r, c, val, style);
        });
        if ((m.overdue ?? 0) > 0) {
            const ref = XLSX.utils.encode_cell({ r, c: 6 });
            ws[ref].s = { ...(alt ? S.numAlt : S.num), font: { bold: true, sz: 10, color: { rgb: 'BE123C' } } };
        }
        if ((m.dueToday ?? 0) > 0) {
            const ref = XLSX.utils.encode_cell({ r, c: 7 });
            ws[ref].s = { ...(alt ? S.numAlt : S.num), font: { bold: true, sz: 10, color: { rgb: 'B45309' } } };
        }
    });

    const totalRow = headerRow + 1 + sorted.length;
    const dataStart = headerRow + 2;
    const dataEnd = Math.max(headerRow + 1 + sorted.length, dataStart);
    setCell(ws, totalRow, 0, 'Tổng', S.totalLabel);
    mergeRow(ws, totalRow, 0, 4);
    [5, 6, 7, 8, 9, 10].forEach((c) => {
        const col = XLSX.utils.encode_col(c);
        ws[XLSX.utils.encode_cell({ r: totalRow, c })] = {
            f: `SUM(${col}${dataStart}:${col}${dataEnd})`, t: 'n', s: S.total,
        };
    });
    setCell(ws, totalRow, 11, '', S.total);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: totalRow, c: COLS } });
    setColWidths(ws, [5, 26, 22, 22, 22, 9, 9, 9, 9, 9, 11, 20]);
    ws['!rows'] = [{ hpt: 26 }, { hpt: 16 }, null, { hpt: 24 }, null, { hpt: 26 }];
    ws['!autofilter'] = { ref: XLSX.utils.encode_range({ s: { r: headerRow, c: 0 }, e: { r: Math.max(headerRow, totalRow - 1), c: COLS } }) };
    ws['!freeze'] = { xSplit: 0, ySplit: headerRow + 1 };

    return ws;
}

/**
 * @param {{ members: object[], summary?: object, scopeLabel?: string }} opts
 */
export function exportTeamRoster({ members, summary = null, scopeLabel = '' }) {
    if (!members?.length) return false;

    const ws = buildRosterXlsx(members, { summary, scopeLabel });
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'VA - Tai viec nhom');

    const { dd, mm, yyyy } = fileStamp();
    XLSX.writeFile(wb, `VA_TaiViecNhom_${dd}${mm}${yyyy}.xlsx`);
    return true;
}
