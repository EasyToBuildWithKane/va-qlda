import axios from 'axios';
import XLSX from 'xlsx-js-style';
import { date as formatDate, datetime } from '@/composables/useFormat';

// VAschools brand palette
const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const WHITE = 'FFFFFF';

// Task status → reconciliation bucket + cell fill (conditional formatting).
const STATUS_BUCKET = {
    done: 'done',
    in_progress: 'doing',
    in_review: 'doing',
    todo: 'todo',
    blocked: 'todo',
};
const STATUS_LABEL = {
    done: 'Hoàn thành',
    in_progress: 'Đang làm',
    in_review: 'Đang review',
    todo: 'Cần làm',
    blocked: 'Bị chặn',
};
const STATUS_FILL = {
    done: 'DCFCE7',
    in_progress: 'E0F2FE',
    in_review: 'EDE9FE',
    todo: 'F1F5F9',
    blocked: 'FFE4E6',
};

function borderThin() {
    const c = { style: 'thin', color: { rgb: SLATE_200 } };
    return { top: c, bottom: c, left: c, right: c };
}

const S = {
    title: { font: { bold: true, sz: 15, color: { rgb: BRAND } } },
    subtitle: { font: { sz: 10, color: { rgb: '64748B' }, italic: true } },
    sectionTitle: {
        font: { bold: true, sz: 11, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { vertical: 'center' },
    },
    header: {
        font: { bold: true, sz: 10, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    kpiLabel: {
        font: { sz: 10, color: { rgb: '475569' } },
        fill: { fgColor: { rgb: BRAND_SOFT } },
        alignment: { vertical: 'center' },
        border: borderThin(),
    },
    kpiValue: {
        font: { bold: true, sz: 12, color: { rgb: '0F172A' } },
        alignment: { vertical: 'center', horizontal: 'right' },
        border: borderThin(),
    },
    cell: {
        font: { sz: 10, color: { rgb: '334155' } },
        alignment: { vertical: 'top', wrapText: true },
        border: borderThin(),
    },
    cellAlt: {
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { vertical: 'top', wrapText: true },
        border: borderThin(),
    },
    num: {
        font: { sz: 10, color: { rgb: '334155' } },
        alignment: { vertical: 'top', horizontal: 'right' },
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

function finalize(ws, lastRow, lastCol, widths) {
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: Math.max(lastRow, 1), c: Math.max(lastCol, 1) } });
    if (widths) ws['!cols'] = widths.map((w) => ({ wch: w }));
    return ws;
}

/** Convert report HTML into readable plain text (bullets + line breaks preserved). */
function htmlToText(html) {
    if (!html) return '';
    let s = String(html)
        .replace(/<\/(p|div|h[1-6])>/gi, '\n')
        .replace(/<li[^>]*>/gi, '• ')
        .replace(/<\/li>/gi, '\n')
        .replace(/<br\s*\/?>/gi, '\n')
        .replace(/<[^>]+>/g, '')
        .replace(/&nbsp;/g, ' ')
        .replace(/&amp;/g, '&')
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>');
    return s.replace(/\n{3,}/g, '\n\n').replace(/[ \t]+\n/g, '\n').trim();
}

function snippet(html, max = 220) {
    const t = htmlToText(html).replace(/\n+/g, ' · ');
    return t.length > max ? `${t.slice(0, max)}…` : t;
}

/** Tally a report's linked tasks into reconciliation buckets. */
function taskBuckets(report) {
    const b = { done: 0, doing: 0, todo: 0, total: 0 };
    for (const p of report.projects ?? []) {
        for (const t of p.tasks ?? []) {
            const bucket = STATUS_BUCKET[t.status] ?? 'todo';
            b[bucket] += 1;
            b.total += 1;
        }
    }
    return b;
}

function projectLabels(report) {
    return (report.projects ?? []).map((p) => p?.name || p?.code).filter(Boolean).join(', ');
}

function ymdMonth(date) {
    return String(date).slice(0, 7);
}

function topKey(counter) {
    let best = null;
    let bestN = 0;
    for (const [k, n] of Object.entries(counter)) {
        if (n > bestN) { best = k; bestN = n; }
    }
    return best ? `${best} (${bestN})` : '—';
}

function filterNote(filters, meta, total) {
    const parts = [];
    if (filters?.q) parts.push(`Từ khoá: ${filters.q}`);
    if (filters?.status) parts.push(`Trạng thái: ${filters.status}`);
    if (filters?.grade) parts.push(`Xếp loại: ${filters.grade}`);
    if (filters?.late) parts.push('Chỉ nộp trễ');
    if (filters?.employee_ids?.length) parts.push(`${filters.employee_ids.length} nhân sự`);
    if (filters?.from || filters?.to) parts.push(`Khoảng ngày: ${filters.from || '…'} → ${filters.to || '…'}`);
    parts.push(`${total} báo cáo (toàn bộ kết quả lọc${meta?.total != null && total < meta.total ? `, giới hạn xuất ${total}/${meta.total}` : ''})`);
    return parts.join(' · ');
}

// ---- Generic styled table sheet ----------------------------------------

/**
 * @param {object} opts
 * @param {string} opts.title
 * @param {string} [opts.subtitle]
 * @param {{header:string,width:number,key:string,num?:boolean,statusFill?:boolean}[]} opts.columns
 * @param {object[]} opts.rows  Plain value objects keyed by column.key
 */
function buildTableSheet({ title, subtitle, columns, rows }) {
    const ws = {};
    const lastCol = columns.length - 1;

    setCell(ws, 0, 0, title, S.title);
    mergeRow(ws, 0, 0, lastCol);
    if (subtitle) {
        setCell(ws, 1, 0, subtitle, S.subtitle);
        mergeRow(ws, 1, 0, lastCol);
    }

    const headerRow = subtitle ? 3 : 2;
    columns.forEach((c, i) => setCell(ws, headerRow, i, c.header, S.header));

    rows.forEach((row, idx) => {
        const r = headerRow + 1 + idx;
        const alt = idx % 2 === 1;
        columns.forEach((c, ci) => {
            const val = row[c.key];
            let style = c.num ? S.num : (alt ? S.cellAlt : S.cell);
            if (c.statusFill && row[`${c.key}__fill`]) {
                style = { ...style, fill: { fgColor: { rgb: row[`${c.key}__fill`] } } };
            }
            setCell(ws, r, ci, val, style);
        });
    });

    const lastRow = headerRow + rows.length;
    if (rows.length) {
        ws['!autofilter'] = {
            ref: XLSX.utils.encode_range({ s: { r: headerRow, c: 0 }, e: { r: lastRow, c: lastCol } }),
        };
    }
    return finalize(ws, lastRow, lastCol, columns.map((c) => c.width));
}

// ---- Sheet 1: Dashboard -------------------------------------------------

function buildDashboardSheet(rows, filters, meta) {
    const ws = {};
    const lastCol = 3;
    const total = rows.length;
    const reviewed = rows.filter((r) => r.status === 'reviewed').length;
    const submitted = rows.filter((r) => r.status === 'submitted').length;
    const draft = rows.filter((r) => r.status === 'draft').length;
    const late = rows.filter((r) => r.is_late).length;
    const employees = new Set(rows.map((r) => r.employee?.id).filter((v) => v != null)).size;
    const tasksTotal = rows.reduce((s, r) => s + taskBuckets(r).total, 0);
    const tasksDone = rows.reduce((s, r) => s + taskBuckets(r).done, 0);
    const completion = total ? Math.round((reviewed / total) * 1000) / 10 : 0;

    setCell(ws, 0, 0, 'BÁO CÁO NGÀY — TỔNG QUAN QUẢN TRỊ', S.title);
    mergeRow(ws, 0, 0, lastCol);
    setCell(ws, 1, 0, `VAschools · Xuất lúc ${datetime(new Date().toISOString())}`, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);
    setCell(ws, 2, 0, filterNote(filters, meta, total), S.subtitle);
    mergeRow(ws, 2, 0, lastCol);

    setCell(ws, 4, 0, 'CHỈ SỐ CHÍNH', S.sectionTitle);
    mergeRow(ws, 4, 0, lastCol);

    const kpis = [
        ['Tổng số nhân sự', employees],
        ['Tổng số báo cáo', total],
        ['Đã duyệt', reviewed],
        ['Chờ duyệt', submitted],
        ['Nháp / trả lại', draft],
        ['Nộp trễ', late],
        ['Tỷ lệ hoàn thành (%)', completion],
        ['Tổng công việc (task)', tasksTotal],
        ['Công việc hoàn thành', tasksDone],
    ];
    let r = 5;
    kpis.forEach(([label, value]) => {
        setCell(ws, r, 0, label, S.kpiLabel);
        mergeRow(ws, r, 0, 1);
        setCell(ws, r, 2, value, S.kpiValue);
        mergeRow(ws, r, 2, 3);
        r += 1;
    });

    // Mini bar (text) — completion vs not, as a visual proxy (no native charts).
    r += 1;
    setCell(ws, r, 0, 'PHÂN BỐ TRẠNG THÁI', S.sectionTitle);
    mergeRow(ws, r, 0, lastCol);
    r += 1;
    const dist = [['Đã duyệt', reviewed], ['Chờ duyệt', submitted], ['Nháp/trả lại', draft]];
    const maxN = Math.max(1, ...dist.map(([, n]) => n));
    dist.forEach(([label, n]) => {
        setCell(ws, r, 0, label, S.cell);
        setCell(ws, r, 1, n, S.num);
        setCell(ws, r, 2, '█'.repeat(Math.round((n / maxN) * 20)), { font: { color: { rgb: BRAND } } });
        mergeRow(ws, r, 2, 3);
        r += 1;
    });

    return finalize(ws, r, lastCol, [26, 14, 14, 14]);
}

// ---- Build all sheets & download ---------------------------------------

function buildWorkbook(rows, filters, meta) {
    const wb = XLSX.utils.book_new();

    // Sheet 1 — Dashboard
    XLSX.utils.book_append_sheet(wb, buildDashboardSheet(rows, filters, meta), 'Tong quan');

    // Sheet 2 — Đối soát theo Ngày
    const reconcileRows = rows.map((rp) => {
        const b = taskBuckets(rp);
        return {
            date: formatDate(rp.date),
            employee: rp.employee?.name ?? '—',
            done: b.done,
            doing: b.doing,
            todo: b.todo,
            goals: snippet(rp.goals_today),
        };
    });
    XLSX.utils.book_append_sheet(wb, buildTableSheet({
        title: 'ĐỐI SOÁT THEO NGÀY',
        subtitle: filterNote(filters, meta, rows.length),
        columns: [
            { header: 'Ngày', key: 'date', width: 13 },
            { header: 'Nhân sự', key: 'employee', width: 22 },
            { header: 'Hoàn thành', key: 'done', width: 11, num: true },
            { header: 'Đang thực hiện', key: 'doing', width: 13, num: true },
            { header: 'Tồn đọng', key: 'todo', width: 11, num: true },
            { header: 'Mục tiêu', key: 'goals', width: 50 },
        ],
        rows: reconcileRows,
    }), 'Doi soat ngay');

    // Sheet 3 — Chi tiết Công việc (one row per linked task)
    const detailRows = [];
    for (const rp of rows) {
        for (const p of rp.projects ?? []) {
            if (!(p.tasks ?? []).length) {
                detailRows.push({
                    employee: rp.employee?.name ?? '—', date: formatDate(rp.date),
                    project: p.name || p.code || '—', task: '—', status: '—',
                });
                continue;
            }
            for (const t of p.tasks) {
                detailRows.push({
                    employee: rp.employee?.name ?? '—',
                    date: formatDate(rp.date),
                    project: p.name || p.code || '—',
                    task: t.title,
                    status: STATUS_LABEL[t.status] ?? t.status ?? '—',
                    status__fill: STATUS_FILL[STATUS_BUCKET[t.status] ?? 'todo'],
                });
            }
        }
    }
    XLSX.utils.book_append_sheet(wb, buildTableSheet({
        title: 'CHI TIẾT CÔNG VIỆC',
        subtitle: `${detailRows.length} công việc liên kết`,
        columns: [
            { header: 'Nhân sự', key: 'employee', width: 22 },
            { header: 'Ngày', key: 'date', width: 13 },
            { header: 'Dự án', key: 'project', width: 24 },
            { header: 'Công việc', key: 'task', width: 40 },
            { header: 'Trạng thái', key: 'status', width: 14, statusFill: true },
        ],
        rows: detailRows,
    }), 'Chi tiet cong viec');

    // Sheet 4 — Mục tiêu Hôm nay
    XLSX.utils.book_append_sheet(wb, buildTableSheet({
        title: 'MỤC TIÊU HÔM NAY',
        columns: [
            { header: 'Nhân sự', key: 'employee', width: 22 },
            { header: 'Ngày', key: 'date', width: 13 },
            { header: 'Mục tiêu', key: 'goals', width: 60 },
            { header: 'Trạng thái', key: 'status', width: 14 },
        ],
        rows: rows.map((rp) => ({
            employee: rp.employee?.name ?? '—',
            date: formatDate(rp.date),
            goals: htmlToText(rp.goals_today),
            status: rp.status_label ?? rp.status,
        })),
    }), 'Muc tieu hom nay');

    // Sheet 5 — Kế hoạch Sắp tới
    XLSX.utils.book_append_sheet(wb, buildTableSheet({
        title: 'KẾ HOẠCH SẮP TỚI',
        subtitle: 'Dữ liệu hiện chỉ có kế hoạch ngày mai (chưa có trường kế hoạch tuần/tháng).',
        columns: [
            { header: 'Nhân sự', key: 'employee', width: 22 },
            { header: 'Ngày', key: 'date', width: 13 },
            { header: 'Kế hoạch ngày mai', key: 'plan', width: 70 },
        ],
        rows: rows.map((rp) => ({
            employee: rp.employee?.name ?? '—',
            date: formatDate(rp.date),
            plan: htmlToText(rp.plan_tomorrow),
        })),
    }), 'Ke hoach');

    // Sheet 6 — Tổng hợp Theo Tháng
    const byMonth = {};
    for (const rp of rows) {
        const m = ymdMonth(rp.date);
        byMonth[m] ??= { total: 0, reviewed: 0, tasks: 0, emp: {}, proj: {} };
        const bucket = byMonth[m];
        bucket.total += 1;
        if (rp.status === 'reviewed') bucket.reviewed += 1;
        bucket.tasks += taskBuckets(rp).total;
        const name = rp.employee?.name;
        if (name) bucket.emp[name] = (bucket.emp[name] ?? 0) + 1;
        for (const p of rp.projects ?? []) {
            const pn = p.name || p.code;
            if (pn) bucket.proj[pn] = (bucket.proj[pn] ?? 0) + 1;
        }
    }
    const monthRows = Object.keys(byMonth).sort().reverse().map((m) => {
        const b = byMonth[m];
        return {
            month: m,
            total: b.total,
            tasks: b.tasks,
            completion: b.total ? Math.round((b.reviewed / b.total) * 1000) / 10 : 0,
            topEmp: topKey(b.emp),
            topProj: topKey(b.proj),
        };
    });
    XLSX.utils.book_append_sheet(wb, buildTableSheet({
        title: 'TỔNG HỢP THEO THÁNG',
        columns: [
            { header: 'Tháng', key: 'month', width: 12 },
            { header: 'Tổng báo cáo', key: 'total', width: 13, num: true },
            { header: 'Tổng công việc', key: 'tasks', width: 14, num: true },
            { header: 'Tỷ lệ hoàn thành (%)', key: 'completion', width: 18, num: true },
            { header: 'Nhân sự nổi bật', key: 'topEmp', width: 26 },
            { header: 'Dự án cập nhật nhiều', key: 'topProj', width: 28 },
        ],
        rows: monthRows,
    }), 'Theo thang');

    // Sheet 7 — Báo cáo Theo Thành viên
    const byEmp = {};
    for (const rp of rows) {
        const id = rp.employee?.id ?? rp.employee?.name ?? '—';
        byEmp[id] ??= {
            name: rp.employee?.name ?? '—', role: rp.employee?.role_title ?? '',
            total: 0, reviewed: 0, late: 0, tasks: 0, done: 0,
        };
        const e = byEmp[id];
        e.total += 1;
        if (rp.status === 'reviewed') e.reviewed += 1;
        if (rp.is_late) e.late += 1;
        const b = taskBuckets(rp);
        e.tasks += b.total;
        e.done += b.done;
    }
    const empRows = Object.values(byEmp)
        .sort((a, b) => b.total - a.total)
        .map((e) => ({
            name: e.name,
            role: e.role || '—',
            total: e.total,
            reviewed: e.reviewed,
            late: e.late,
            tasks: e.tasks,
            done: e.done,
            completion: e.total ? Math.round((e.reviewed / e.total) * 1000) / 10 : 0,
        }));
    XLSX.utils.book_append_sheet(wb, buildTableSheet({
        title: 'BÁO CÁO THEO THÀNH VIÊN',
        columns: [
            { header: 'Nhân sự', key: 'name', width: 24 },
            { header: 'Chức vụ', key: 'role', width: 20 },
            { header: 'Tổng báo cáo', key: 'total', width: 13, num: true },
            { header: 'Đã duyệt', key: 'reviewed', width: 11, num: true },
            { header: 'Nộp trễ', key: 'late', width: 10, num: true },
            { header: 'Tổng task', key: 'tasks', width: 11, num: true },
            { header: 'Task hoàn thành', key: 'done', width: 14, num: true },
            { header: 'Tỷ lệ hoàn thành (%)', key: 'completion', width: 18, num: true },
        ],
        rows: empRows,
    }), 'Theo thanh vien');

    return wb;
}

// ---- CSV fallback (flat) ------------------------------------------------

function escapeCsv(val) {
    return `"${String(val ?? '').replace(/"/g, '""')}"`;
}

function buildCsv(rows, filters, meta) {
    const header = ['Ngày', 'Nhân sự', 'Chức vụ', 'Tiêu đề', 'Dự án', 'Trạng thái', 'Xếp loại', 'Tổng điểm', 'Nộp trễ'];
    const body = rows.map((r) => [
        formatDate(r.date),
        r.employee?.name ?? '—',
        r.employee?.role_title ?? '',
        r.title ?? '',
        projectLabels(r) || '—',
        r.status_label ?? r.status ?? '',
        r.score?.grade ?? '—',
        r.score ? Number(r.score.total_score ?? 0).toFixed(2) : '—',
        r.is_late ? 'Có' : 'Không',
    ]);
    const lines = [
        ['LỊCH SỬ BÁO CÁO NGÀY'],
        ['Ngày xuất', datetime(new Date().toISOString())],
        ['Bộ lọc', filterNote(filters, meta, rows.length)],
        [],
        header,
        ...body,
    ];
    return '﻿' + lines.map((row) => row.map(escapeCsv).join(',')).join('\n');
}

function stampFilename() {
    return `VA_BaoCaoNgay_${new Date().toISOString().slice(0, 10)}`;
}

function downloadBlob(content, filename, type) {
    const blob = new Blob([content], { type });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = filename;
    a.click();
    URL.revokeObjectURL(a.href);
}

/**
 * Fetch the full filtered dataset from the server, then build either the
 * 7-sheet management workbook or a flat CSV. Returns the filename, or null
 * when there is nothing to export.
 *
 * @param {{ params: object, filters?: object, meta?: object, format: 'csv'|'xlsx' }} opts
 */
export async function exportDailyReportHistory({ params, filters = {}, meta = {}, format }) {
    const url = (typeof route === 'function') ? route('daily-reports.export-data') : '/daily-reports/export-data';
    const { data } = await axios.get(url, { params });
    const rows = Array.isArray(data) ? data : (data?.data ?? []);
    if (!rows.length) return null;

    const base = stampFilename();

    if (format === 'csv') {
        downloadBlob(buildCsv(rows, filters, meta), `${base}.csv`, 'text/csv;charset=utf-8;');
        return `${base}.csv`;
    }

    const wb = buildWorkbook(rows, filters, meta);
    const filename = `${base}.xlsx`;
    XLSX.writeFile(wb, filename);
    return filename;
}
