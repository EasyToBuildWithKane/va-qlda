import XLSX from 'xlsx-js-style';
import { date, datetime } from '@/composables/useFormat';
import { getTaskAssigneeIds } from '@/composables/useProjectDashboard';

/** Tên sheet mẫu VA (Excel cấm: : \\ / ? * [ ]) */
const SHEET = {
    overview: 'VA - Tổng quan',
    tasks: 'VA - Công việc',
    workload: 'VA - Workload',
    issues: 'VA - vướng mắc',
};

function sanitizeSheetName(name) {
    return String(name)
        .replace(/[:\\/?*[\]]/g, '')
        .trim()
        .slice(0, 31) || 'Sheet';
}

const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const SLATE_600 = '475569';
const WHITE = 'FFFFFF';

const S = {
    title: {
        font: { bold: true, sz: 16, color: { rgb: BRAND } },
        alignment: { horizontal: 'left', vertical: 'center' },
    },
    subtitle: {
        font: { sz: 10, color: { rgb: SLATE_600 }, italic: true },
        alignment: { horizontal: 'left' },
    },
    section: {
        font: { bold: true, sz: 11, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'left', vertical: 'center' },
    },
    label: {
        font: { bold: true, sz: 10, color: { rgb: SLATE_600 } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { vertical: 'center' },
        border: borderThin(),
    },
    value: {
        font: { sz: 10, color: { rgb: '1E293B' } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    formula: {
        font: { bold: true, sz: 11, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'right', vertical: 'center' },
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
    total: {
        font: { bold: true, sz: 10, color: { rgb: BRAND } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { horizontal: 'right', vertical: 'center' },
        border: borderThin(),
    },
};

function borderThin() {
    return {
        top: { style: 'thin', color: { rgb: SLATE_200 } },
        bottom: { style: 'thin', color: { rgb: SLATE_200 } },
        left: { style: 'thin', color: { rgb: SLATE_200 } },
        right: { style: 'thin', color: { rgb: SLATE_200 } },
    };
}

function qSheet(name) {
    return `'${String(name).replace(/'/g, "''")}'`;
}

function assigneeNames(task) {
    if (task.assignees?.length) return task.assignees.map((a) => a.name).join(', ');
    if (task.assignee?.name) return task.assignee.name;
    return '—';
}

function severityLabel(value) {
    const map = { low: 'Thấp', medium: 'Trung bình', high: 'Cao', critical: 'Nghiêm trọng' };
    return map[value] ?? value;
}

function statusLabelIssue(value) {
    const map = { open: 'Đang mở', in_progress: 'Đang xử lý', resolved: 'Đã xử lý' };
    return map[value] ?? value;
}

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    const isFormula = value && typeof value === 'object' && value.f;
    ws[ref] = isFormula
        ? { f: value.f, t: 'n', s: style }
        : { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

function setColWidths(ws, widths) {
    ws['!cols'] = widths.map((wch) => ({ wch }));
}

function buildOverviewSheet(project, daysLeft) {
    const ws = {};
    const end = project.due_date || project.end_date;
    const exportedAt = datetime(new Date().toISOString());
    const COLS = 5;

    setCell(ws, 0, 0, `BÁO CÁO DỰ ÁN — ${project.name}`, S.title);
    mergeRow(ws, 0, 0, COLS);

    setCell(ws, 1, 0, 'Mẫu chuẩn VAschools · Quản lý dự án (VA)', S.subtitle);
    mergeRow(ws, 1, 0, COLS);

    const info = [
        ['Đơn vị', 'VAschools'],
        ['Ngày xuất báo cáo', exportedAt],
        ['Mã dự án', project.code ?? '—'],
        ['Tên dự án', project.name ?? '—'],
        ['Ngày bắt đầu', date(project.start_date)],
        ['Ngày kết thúc', date(end)],
        ['Chủ dự án / PM', project.manager?.name ?? '—'],
        ['Phòng ban', project.department?.name ?? '—'],
        ['Loại dự án', project.type?.label ?? '—'],
        ['Trạng thái dự án', project.status?.label ?? '—'],
    ];

    let row = 3;
    setCell(ws, row, 0, 'THÔNG TIN DỰ ÁN', S.section);
    mergeRow(ws, row, 0, COLS);
    row++;

    info.forEach(([label, val]) => {
        setCell(ws, row, 0, label, S.label);
        setCell(ws, row, 1, val, S.value);
        mergeRow(ws, row, 1, COLS);
        row++;
    });

    row++;
    setCell(ws, row, 0, 'CHỈ SỐ TỔNG HỢP (tự động từ các sheet)', S.section);
    mergeRow(ws, row, 0, COLS);
    row++;

    const t = qSheet(SHEET.tasks);
    const i = qSheet(SHEET.issues);
    const metrics = [
        ['Tổng số công việc', { f: `COUNTA(${t}!B2:B2000)` }],
        ['Đã hoàn thành', { f: `COUNTIF(${t}!D2:D2000,"done")` }],
        ['Tỷ lệ hoàn thành', { f: `IFERROR(COUNTIF(${t}!D2:D2000,"done")/COUNTA(${t}!B2:B2000),0)`, pct: true }],
        ['Tiến độ TB', { f: `IFERROR(ROUND(AVERAGE(${t}!F2:F2000),0),0)/100`, pct: true }],
        ['vướng mắc đang mở', { f: `COUNTIFS(${i}!D2:D2000,"<>resolved",${i}!D2:D2000,"<>")` }],
        ['Ngày còn lại đến deadline', daysLeft === '—' ? '—' : daysLeft],
    ];

    metrics.forEach(([label, val]) => {
        setCell(ws, row, 0, label, S.label);
        const isFormula = val && typeof val === 'object' && val.f;
        setCell(ws, row, 1, isFormula ? { f: val.f } : val, isFormula ? S.formula : S.value);
        if (isFormula?.pct) {
            ws[XLSX.utils.encode_cell({ r: row, c: 1 })].z = '0%';
        }
        mergeRow(ws, row, 1, COLS);
        row++;
    });

    row++;
    setCell(ws, row, 0, 'Ghi chú: Các chỉ số có công thức liên kết các sheet VA -. Mở sheet chi tiết bên dưới.', S.subtitle);
    mergeRow(ws, row, 0, COLS);

    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: row, c: COLS } });
    setColWidths(ws, [28, 36, 14, 14, 14]);
    ws['!rows'] = [{ hpt: 32 }, { hpt: 18 }];

    return ws;
}

function buildTasksSheet(tasks) {
    const ws = {};
    const headers = ['STT', 'Tiêu đề', 'Trạng thái', 'Mã TT', 'Người thực hiện', 'Tiến độ %', 'Bắt đầu', 'Hạn', 'Ưu tiên'];
    headers.forEach((h, c) => setCell(ws, 0, c, h, S.header));

    tasks.forEach((t, idx) => {
        const r = idx + 1;
        const rowStyle = idx % 2 === 0 ? S.cell : S.cellAlt;
        setCell(ws, r, 0, idx + 1, rowStyle);
        setCell(ws, r, 1, t.title, rowStyle);
        setCell(ws, r, 2, t.status?.label ?? '—', rowStyle);
        setCell(ws, r, 3, t.status?.value ?? '', rowStyle);
        setCell(ws, r, 4, assigneeNames(t), rowStyle);
        setCell(ws, r, 5, Number(t.progress ?? 0), rowStyle);
        setCell(ws, r, 6, date(t.start_date), rowStyle);
        setCell(ws, r, 7, date(t.due_date), rowStyle);
        setCell(ws, r, 8, t.priority?.label ?? '—', rowStyle);
    });

    const totalRow = tasks.length + 1;
    setCell(ws, totalRow, 0, 'Tổng', S.total);
    setCell(ws, totalRow, 1, { f: `COUNTA(B2:B${Math.max(tasks.length + 1, 2)})` }, S.total);
    setCell(ws, totalRow, 2, 'Hoàn thành:', S.total);
    setCell(ws, totalRow, 3, { f: `COUNTIF(D2:D${Math.max(tasks.length + 1, 2)},"done")` }, S.total);
    setCell(ws, totalRow, 5, { f: `IFERROR(ROUND(AVERAGE(F2:F${Math.max(tasks.length + 1, 2)}),0),0)` }, S.total);

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: totalRow, c: headers.length - 1 },
    });
    setColWidths(ws, [6, 42, 16, 10, 28, 12, 12, 12, 12]);
    return ws;
}

function buildWorkloadSheet(members, tasks) {
    const ws = {};
    const headers = ['STT', 'Thành viên', 'Vai trò', 'Đang chạy', 'Giờ ước lượng', 'Đã xong', 'Tổng giao', 'Tiến độ %'];
    headers.forEach((h, c) => setCell(ws, 0, c, h, S.header));

    members.forEach((m, idx) => {
        const r = idx + 1;
        const assigned = tasks.filter((t) => getTaskAssigneeIds(t).includes(m.id));
        const active = assigned.filter((t) => t.status?.value !== 'done');
        const hours = active.reduce((s, t) => s + Number(t.estimate_hours || 0), 0);
        const done = assigned.filter((t) => t.status?.value === 'done').length;
        const total = assigned.length;
        const progress = total ? Math.round((done / total) * 100) : 0;
        const rowStyle = idx % 2 === 0 ? S.cell : S.cellAlt;

        setCell(ws, r, 0, idx + 1, rowStyle);
        setCell(ws, r, 1, m.name, rowStyle);
        setCell(ws, r, 2, m.project_role ?? '—', rowStyle);
        setCell(ws, r, 3, active.length, rowStyle);
        setCell(ws, r, 4, hours, rowStyle);
        setCell(ws, r, 5, done, rowStyle);
        setCell(ws, r, 6, total, rowStyle);
        setCell(ws, r, 7, total ? progress / 100 : 0, rowStyle);
        ws[XLSX.utils.encode_cell({ r, c: 7 })].z = '0%';
    });

    const totalRow = members.length + 1;
    setCell(ws, totalRow, 0, 'Tổng', S.total);
    setCell(ws, totalRow, 3, { f: `SUM(D2:D${Math.max(members.length + 1, 2)})` }, S.total);
    setCell(ws, totalRow, 4, { f: `SUM(E2:E${Math.max(members.length + 1, 2)})` }, S.total);

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: totalRow, c: headers.length - 1 },
    });
    setColWidths(ws, [6, 28, 18, 12, 14, 10, 10, 12]);
    return ws;
}

function buildIssuesSheet(blockers) {
    const ws = {};
    const headers = ['STT', 'Tiêu đề', 'Mức độ', 'Mã', 'Trạng thái', 'Mã TT', 'Người xử lý', 'Mô tả'];
    headers.forEach((h, c) => setCell(ws, 0, c, h, S.header));

    blockers.forEach((b, idx) => {
        const r = idx + 1;
        const rowStyle = idx % 2 === 0 ? S.cell : S.cellAlt;
        const sev = b.severity?.value ?? 'medium';
        setCell(ws, r, 0, idx + 1, rowStyle);
        setCell(ws, r, 1, b.title, rowStyle);
        setCell(ws, r, 2, b.severity?.label ?? severityLabel(sev), rowStyle);
        setCell(ws, r, 3, sev, rowStyle);
        setCell(ws, r, 4, b.status?.label ?? statusLabelIssue(b.status?.value), rowStyle);
        setCell(ws, r, 5, b.status?.value ?? '', rowStyle);
        setCell(ws, r, 6, b.owner?.name ?? '—', rowStyle);
        setCell(ws, r, 7, (b.description ?? '').slice(0, 500), rowStyle);

        if (sev === 'critical') {
            const ref = XLSX.utils.encode_cell({ r, c: 2 });
            ws[ref].s = {
                ...rowStyle,
                font: { bold: true, sz: 10, color: { rgb: 'BE123C' } },
                fill: { fgColor: { rgb: 'FFE4E6' } },
            };
        } else if (sev === 'high') {
            const ref = XLSX.utils.encode_cell({ r, c: 2 });
            ws[ref].s = {
                ...rowStyle,
                font: { bold: true, sz: 10, color: { rgb: 'C2410C' } },
                fill: { fgColor: { rgb: 'FFEDD5' } },
            };
        }
    });

    const totalRow = blockers.length + 1;
    setCell(ws, totalRow, 0, 'Tổng', S.total);
    setCell(ws, totalRow, 1, { f: `COUNTA(B2:B${Math.max(blockers.length + 1, 2)})` }, S.total);
    setCell(ws, totalRow, 2, 'Đang mở:', S.total);
    setCell(ws, totalRow, 5, { f: `COUNTIFS(F2:F${Math.max(blockers.length + 1, 2)},"<>resolved",F2:F${Math.max(blockers.length + 1, 2)},"<>")` }, S.total);

    ws['!ref'] = XLSX.utils.encode_range({
        s: { r: 0, c: 0 },
        e: { r: totalRow, c: headers.length - 1 },
    });
    setColWidths(ws, [6, 40, 14, 10, 14, 12, 22, 36]);

    return ws;
}

/** Cho UI repaint trước khi build file nặng */
function nextFrame() {
    return new Promise((resolve) => {
        requestAnimationFrame(() => requestAnimationFrame(resolve));
    });
}

/**
 * Export project dashboard report as formatted .xlsx (mẫu VA)
 * @returns {Promise<string>} tên file đã tải
 */
export function useProjectExport() {
    const exportReport = async ({ project, tasks, blockers, members }) => {
        await nextFrame();

        const end = project.due_date || project.end_date;
        const daysLeft = end
            ? Math.ceil((new Date(`${end}T00:00:00`) - new Date().setHours(0, 0, 0, 0)) / 86400000)
            : '—';

        const wb = XLSX.utils.book_new();

        const wsTasks = buildTasksSheet(tasks);
        const wsWorkload = buildWorkloadSheet(members, tasks);
        const wsIssues = buildIssuesSheet(blockers);
        const wsOverview = buildOverviewSheet(project, daysLeft);

        const sheets = [
            [wsOverview, SHEET.overview],
            [wsTasks, SHEET.tasks],
            [wsWorkload, SHEET.workload],
            [wsIssues, SHEET.issues],
        ];
        sheets.forEach(([ws, name]) => {
            XLSX.utils.book_append_sheet(wb, ws, sanitizeSheetName(name));
        });

        const today = new Date();
        const dd = String(today.getDate()).padStart(2, '0');
        const mm = String(today.getMonth() + 1).padStart(2, '0');
        const yyyy = today.getFullYear();
        const code = String(project.code ?? 'DA').replace(/[:\\/?*[\]]/g, '_');
        const filename = `VA_BaoCao_${code}_${dd}${mm}${yyyy}.xlsx`;

        XLSX.writeFile(wb, filename);
        return filename;
    };

    return { exportReport };
}
