import XLSX from 'xlsx-js-style';
import { date as formatDate, datetime } from '@/composables/useFormat';

const BRAND = '9A0036';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
const WHITE = 'FFFFFF';

const COL_DEFS = [
    { key: 'date', label: 'Ngày' },
    { key: 'employee', label: 'Người báo cáo' },
    { key: 'title', label: 'Tiêu đề' },
    { key: 'projects', label: 'Dự án' },
    { key: 'status', label: 'Trạng thái' },
    { key: 'grade', label: 'Xếp loại' },
    { key: 'score', label: 'Tổng điểm' },
    { key: 'late', label: 'Nộp trễ' },
    { key: 'feedback', label: 'Phản hồi' },
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
    title: {
        font: { bold: true, sz: 14, color: { rgb: BRAND } },
    },
    subtitle: {
        font: { sz: 10, color: { rgb: '64748B' }, italic: true },
    },
    header: {
        font: { bold: true, sz: 10, color: { rgb: WHITE } },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
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
};

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

function mergeRow(ws, r, c0, c1) {
    if (!ws['!merges']) ws['!merges'] = [];
    ws['!merges'].push({ s: { r, c: c0 }, e: { r, c: c1 } });
}

function projectLabels(report) {
    const items = report.projects ?? [];
    return items
        .map((p) => p?.code || p?.name)
        .filter(Boolean)
        .join(', ');
}

function feedbackSnippet(report) {
    if (report.status === 'draft' && (report.review_notes || '').trim()) {
        return String(report.review_notes).trim();
    }
    if ((report.score?.notes || '').trim()) {
        return String(report.score.notes).trim();
    }
    return '';
}

function rowValues(report) {
    return {
        date: formatDate(report.date),
        employee: report.employee?.name ?? '—',
        title: report.title ?? '',
        projects: projectLabels(report) || '—',
        status: report.status_label ?? report.status ?? '',
        grade: report.score?.grade ?? '—',
        score: report.score ? Number(report.score.total_score ?? 0).toFixed(2) : '—',
        late: report.is_late ? 'Có' : 'Không',
        feedback: feedbackSnippet(report) || '—',
    };
}

function activeColumns(visibleKeys) {
    if (!visibleKeys?.length) return COL_DEFS;
    const keys = new Set(['date', ...visibleKeys]);
    if (keys.has('score')) keys.add('grade');
    return COL_DEFS.filter((c) => keys.has(c.key));
}

function filterNote(filters, meta) {
    const parts = [];
    if (filters?.q) parts.push(`Từ khoá: ${filters.q}`);
    if (filters?.status) parts.push(`Trạng thái: ${filters.status}`);
    if (filters?.late) parts.push('Chỉ nộp trễ');
    if (filters?.from || filters?.to) {
        parts.push(`Khoảng ngày: ${filters.from || '…'} → ${filters.to || '…'}`);
    }
    if (meta?.total != null) {
        parts.push(`Trang ${meta.current_page}/${meta.last_page} · ${meta.total} bản ghi (toàn bộ kết quả lọc)`);
    }
    return parts.join(' · ') || 'Không có bộ lọc';
}

function buildXlsx(rows, cols, filters, meta) {
    const ws = {};
    const lastCol = Math.max(cols.length - 1, 3);
    const exportedAt = datetime(new Date().toISOString());

    setCell(ws, 0, 0, 'LỊCH SỬ BÁO CÁO NGÀY — VAschools', S.title);
    mergeRow(ws, 0, 0, lastCol);
    setCell(ws, 1, 0, `Xuất lúc ${exportedAt}`, S.subtitle);
    mergeRow(ws, 1, 0, lastCol);
    setCell(ws, 2, 0, filterNote(filters, meta), S.subtitle);
    mergeRow(ws, 2, 0, lastCol);

    const headerRow = 4;
    cols.forEach((c, i) => setCell(ws, headerRow, i, c.label, S.header));

    rows.forEach((report, idx) => {
        const vals = rowValues(report);
        const r = headerRow + 1 + idx;
        const style = idx % 2 === 0 ? S.cell : S.cellAlt;
        cols.forEach((c, ci) => setCell(ws, r, ci, vals[c.key], style));
    });

    const endRow = headerRow + rows.length;
    ws['!ref'] = XLSX.utils.encode_range({ s: { r: 0, c: 0 }, e: { r: endRow, c: cols.length - 1 } });
    ws['!cols'] = cols.map((c) => ({ wch: c.key === 'title' || c.key === 'feedback' ? 32 : 14 }));

    return ws;
}

function escapeCsv(val) {
    return `"${String(val ?? '').replace(/"/g, '""')}"`;
}

function buildCsv(rows, cols, filters, meta) {
    const vals = rows.map((r) => {
        const v = rowValues(r);
        return cols.map((c) => v[c.key]);
    });
    const lines = [
        ['LỊCH SỬ BÁO CÁO NGÀY'],
        ['Ngày xuất', datetime(new Date().toISOString())],
        ['Bộ lọc', filterNote(filters, meta)],
        [],
        cols.map((c) => c.label),
        ...vals,
    ];
    return '\uFEFF' + lines.map((row) => row.map(escapeCsv).join(',')).join('\n');
}

function stampFilename() {
    const t = new Date();
    const iso = t.toISOString().slice(0, 10);
    return `VA_BaoCaoNgay_${iso}`;
}

/**
 * @param {{ rows: object[], visibleColumnKeys?: string[], filters?: object, meta?: object, format: 'csv'|'xlsx' }} opts
 */
export function exportDailyReportHistory({ rows, visibleColumnKeys, filters = {}, meta = {}, format }) {
    if (!rows?.length) return null;

    const cols = activeColumns(visibleColumnKeys);
    const base = stampFilename();

    if (format === 'csv') {
        const blob = new Blob([buildCsv(rows, cols, filters, meta)], { type: 'text/csv;charset=utf-8;' });
        const a = document.createElement('a');
        a.href = URL.createObjectURL(blob);
        a.download = `${base}.csv`;
        a.click();
        URL.revokeObjectURL(a.href);
        return `${base}.csv`;
    }

    const ws = buildXlsx(rows, cols, filters, meta);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Bao cao ngay');
    const filename = `${base}.xlsx`;
    XLSX.writeFile(wb, filename);
    return filename;
}
