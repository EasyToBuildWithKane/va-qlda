import * as XLSX from 'xlsx-js-style';
import { richContentPlainText } from '@/shared/utils/richContent';
import { displayOrEmpty, EMPTY_LABELS, auditGradeLabel } from '@/shared/utils/emptyDisplay.js';

/**
 * Xuất báo cáo hiệu suất ra Excel có style (brand) + in báo cáo.
 * Toàn bộ I/O Excel nằm ở composable — KHÔNG import xlsx trong .vue (theo
 * "tham chiếu vàng" của dự án).
 */
const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';
const ZEBRA = 'F8FAFC'; // slate-50
const LINE = 'E2E8F0'; // slate-200
const INK = '334155'; // slate-700
const MUTED = '64748B'; // slate-500
const GREEN = '047857'; // emerald-700
const ROSE = 'BE123C'; // rose-700

function thinBorder() {
    const side = { style: 'thin', color: { rgb: LINE } };
    return { top: side, bottom: side, left: side, right: side };
}

function headerStyle() {
    return {
        font: { bold: true, color: { rgb: 'FFFFFF' }, sz: 11 },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center', wrapText: true },
        border: thinBorder(),
    };
}

function titleStyle() {
    return {
        font: { bold: true, sz: 15, color: { rgb: BRAND } },
        alignment: { vertical: 'center' },
    };
}

function subtitleStyle() {
    return {
        font: { sz: 10, italic: true, color: { rgb: MUTED } },
        alignment: { vertical: 'center' },
    };
}

function bodyStyle({ zebra = false, wrap = false, align = 'left', color = INK, bold = false } = {}) {
    return {
        font: { sz: 10, color: { rgb: color }, bold },
        alignment: { vertical: 'center', horizontal: align, wrapText: wrap },
        fill: zebra ? { fgColor: { rgb: ZEBRA } } : undefined,
        border: thinBorder(),
    };
}

/** AOA sheet đơn giản (giữ cho export Dashboard cũ). */
function aoaSheet(rows, { headerRow = 0, colWidths = [] } = {}) {
    const ws = XLSX.utils.aoa_to_sheet(rows);
    ws['!cols'] = colWidths.map((w) => ({ wch: w }));

    const range = XLSX.utils.decode_range(ws['!ref']);
    for (let r = range.s.r; r <= range.e.r; r += 1) {
        for (let c = range.s.c; c <= range.e.c; c += 1) {
            const ref = XLSX.utils.encode_cell({ r, c });
            const cell = ws[ref];
            if (!cell) continue;
            if (r === headerRow) {
                cell.s = headerStyle();
            } else {
                cell.s = {
                    border: thinBorder(),
                    alignment: { vertical: 'center' },
                    fill: r % 2 === 0 ? { fgColor: { rgb: BRAND_SOFT } } : undefined,
                };
            }
        }
    }
    return ws;
}

/**
 * Sheet có tiêu đề + phụ đề (merge), header brand, body kẻ ô + zebra, hỗ trợ
 * wrap text, căn cột và tô màu theo giá trị cột (vd. cột "Kết quả").
 *
 * @param {{
 *   title?: string, subtitle?: string,
 *   header: string[], rows: Array<Array<string|number>>,
 *   colWidths?: number[], wrapCols?: number[],
 *   alignCols?: Record<number, 'left'|'center'|'right'>,
 *   colorCols?: Record<number, (v: any) => (string|undefined)>,
 * }} opts
 */
function buildSheet({
    title = '',
    subtitle = '',
    header,
    rows,
    colWidths = [],
    wrapCols = [],
    alignCols = {},
    colorCols = {},
}) {
    const matrix = [];
    if (title) matrix.push([title]);
    if (subtitle) matrix.push([subtitle]);
    if (title || subtitle) matrix.push([]); // dòng đệm
    const headerRow = matrix.length;
    matrix.push(header);
    rows.forEach((r) => matrix.push(r.map((v) => (v === null || v === undefined ? '' : v))));

    const ws = XLSX.utils.aoa_to_sheet(matrix);
    ws['!cols'] = colWidths.map((w) => ({ wch: w }));

    const lastCol = header.length - 1;
    const subtitleRow = title ? 1 : 0;

    ws['!merges'] = [];
    if (title) ws['!merges'].push({ s: { r: 0, c: 0 }, e: { r: 0, c: lastCol } });
    if (subtitle) ws['!merges'].push({ s: { r: subtitleRow, c: 0 }, e: { r: subtitleRow, c: lastCol } });

    ws['!rows'] = [];
    if (title) ws['!rows'][0] = { hpt: 24 };
    ws['!rows'][headerRow] = { hpt: 22 };

    const range = XLSX.utils.decode_range(ws['!ref']);
    for (let r = range.s.r; r <= range.e.r; r += 1) {
        for (let c = range.s.c; c <= lastCol; c += 1) {
            const ref = XLSX.utils.encode_cell({ r, c });
            const cell = ws[ref];
            if (title && r === 0) {
                if (cell && c === 0) cell.s = titleStyle();
                continue;
            }
            if (subtitle && r === subtitleRow) {
                if (cell && c === 0) cell.s = subtitleStyle();
                continue;
            }
            if (r === headerRow) {
                if (cell) cell.s = headerStyle();
                continue;
            }
            if (r > headerRow && cell) {
                const color = colorCols[c] ? colorCols[c](cell.v) : undefined;
                cell.s = bodyStyle({
                    zebra: (r - headerRow) % 2 === 0,
                    wrap: wrapCols.includes(c),
                    align: alignCols[c] ?? 'left',
                    color: color ?? INK,
                    bold: Boolean(color),
                });
            }
        }
    }
    return ws;
}

export function usePerformanceExport() {
    function exportDashboard(payload) {
        const wb = XLSX.utils.book_new();
        const label = payload?.filter?.label ?? '';
        const h = payload?.headline ?? {};

        // Sheet 1 — Tổng quan
        const overview = [
            ['BÁO CÁO HIỆU SUẤT', label],
            [],
            ['Chỉ số', 'Giá trị'],
            ['Task được giao', h.committed ?? 0],
            ['Task hoàn thành', h.done ?? 0],
            ['Tỷ lệ hoàn thành', `${h.completionRate ?? 0}%`],
            ['Tỷ lệ đúng hạn', `${h.onTimeRate ?? 0}%`],
            ['Hiệu suất TB nhân sự', `${h.avgScore ?? 0}%`],
            ['Xếp loại', h.grade ?? '—'],
        ];
        const wsOverview = aoaSheet(overview, { headerRow: 2, colWidths: [28, 22] });
        wsOverview['A1'].s = titleStyle();
        XLSX.utils.book_append_sheet(wb, wsOverview, 'Tong quan');

        // Sheet 2 — Nhân sự
        const people = payload?.people ?? [];
        const rows = [
            ['#', 'Thành viên', 'Vai trò', 'Giao', 'Xong', 'Đang làm', 'Quá hạn', 'Đúng hạn %', 'Chất lượng %', 'Story Point', 'Giờ', 'Điểm', 'Xếp loại'],
            ...people.map((p, i) => [
                i + 1, p.name, p.role ?? '', p.committed, p.done, p.inProgress,
                p.overdue, p.onTimeRate, p.qualityScore, p.storyPoints, p.hoursLogged, p.score, p.grade,
            ]),
        ];
        const wsPeople = aoaSheet(rows, { headerRow: 0, colWidths: [4, 26, 18, 8, 8, 10, 9, 11, 12, 12, 8, 8, 9] });
        XLSX.utils.book_append_sheet(wb, wsPeople, 'Nhan su');

        XLSX.writeFile(wb, `bao-cao-hieu-suat-${stamp()}.xlsx`);
    }

    /**
     * Xuất audit nhân sự — workbook chi tiết 4 sheet:
     *   Tổng quan · Kết quả theo kỳ · Chi tiết công việc · Ghi chú báo cáo ngày.
     */
    function exportAudit(audit, filter = {}) {
        if (!audit) return;
        const wb = XLSX.utils.book_new();
        const member = audit.member?.name ?? 'Nhân sự';
        const role = audit.member?.role ?? '—';
        const periodLabel = filter?.label ?? '';
        const weeks = audit.weeks ?? [];
        const s = audit.summary ?? {};

        // ── Sheet 1 — Tổng quan ──────────────────────────────────────────
        const overviewRows = [
            ['Nhân sự', member],
            ['Vai trò', role],
            ['Kỳ audit', periodLabel || '—'],
            ['Số kỳ thống kê', s.weeks ?? weeks.length],
            ['Tổng cam kết', s.committed ?? 0],
            ['Đã hoàn thành', s.done ?? 0],
            ['Tỷ lệ cam kết đạt', `${s.commitmentRate ?? 0}%`],
            ['Hiệu suất trung bình', `${s.avgScore ?? 0}%`],
            ['Xếp loại', s.grade ?? '—'],
            ['Ngày xuất', new Date().toLocaleString('vi-VN')],
        ];
        const wsOverview = buildSheet({
            title: `AUDIT NHÂN SỰ — ${member}`,
            subtitle: periodLabel ? `Kỳ báo cáo: ${periodLabel}` : 'Timeline cam kết & kết quả theo kỳ',
            header: ['Chỉ số', 'Giá trị'],
            rows: overviewRows,
            colWidths: [26, 46],
            wrapCols: [1],
        });
        XLSX.utils.book_append_sheet(wb, wsOverview, 'Tong quan');

        // ── Sheet 2 — Kết quả theo kỳ ────────────────────────────────────
        const weekHeader = [
            'Kỳ', 'Khoảng thời gian', 'Cam kết', 'Hoàn thành', 'Lỡ hẹn',
            'Tỷ lệ đạt %', 'Hiệu suất %', 'Đúng hạn %', 'Chất lượng %', 'Xếp loại',
        ];
        const weekRows = weeks.map((w) => [
            w.label, w.range,
            w.summary.committed, w.summary.done, w.summary.missed,
            w.summary.committed > 0 ? w.summary.commitmentRate : '—',
            w.summary.committed > 0 ? w.scores.performance : '—',
            w.summary.committed > 0 ? w.scores.onTime : '—',
            w.summary.committed > 0 ? w.scores.quality : '—',
            w.summary.committed > 0 ? w.grade : '—',
        ]);
        const wsWeeks = buildSheet({
            title: 'KẾT QUẢ THEO TỪNG KỲ',
            header: weekHeader,
            rows: weekRows.length ? weekRows : [['—', 'Không có dữ liệu', '', '', '', '', '', '', '', '']],
            colWidths: [12, 22, 9, 11, 9, 11, 11, 11, 12, 9],
            alignCols: { 2: 'center', 3: 'center', 4: 'center', 5: 'center', 6: 'center', 7: 'center', 8: 'center', 9: 'center' },
        });
        XLSX.utils.book_append_sheet(wb, wsWeeks, 'Theo ky');

        // ── Sheet 3 — Chi tiết công việc cam kết ─────────────────────────
        const taskHeader = [
            'Kỳ', 'Công việc', 'Dự án', 'Trạng thái', 'Ưu tiên',
            'Story point', 'Hạn', 'Hoàn thành', 'Kết quả',
        ];
        const taskRows = [];
        weeks.forEach((w) => {
            (w.plan ?? []).forEach((t) => {
                taskRows.push([
                    w.label, t.title, t.project?.name ?? '—', t.statusLabel ?? '', t.priorityLabel ?? '',
                    t.storyPoints ?? '', t.dueDate ?? '', t.completedAt ?? '',
                    t.result === 'done' ? 'Đạt' : 'Lỡ hẹn',
                ]);
            });
        });
        const resultCol = taskHeader.length - 1;
        const wsTasks = buildSheet({
            title: 'CHI TIẾT CÔNG VIỆC CAM KẾT',
            header: taskHeader,
            rows: taskRows.length ? taskRows : [['—', 'Không có công việc cam kết trong kỳ', '', '', '', '', '', '', '']],
            colWidths: [12, 42, 22, 14, 12, 11, 12, 12, 11],
            wrapCols: [1],
            alignCols: { 5: 'center', 6: 'center', 7: 'center', 8: 'center' },
            colorCols: { [resultCol]: (v) => (v === 'Đạt' ? GREEN : v === 'Lỡ hẹn' ? ROSE : undefined) },
        });
        XLSX.utils.book_append_sheet(wb, wsTasks, 'Chi tiet cong viec');

        // ── Sheet 4 — Ghi chú báo cáo ngày (HTML → text sạch) ────────────
        const reportHeader = ['Kỳ', 'Ngày', 'Mục tiêu hôm nay', 'Kế hoạch ngày mai'];
        const reportRows = [];
        weeks.forEach((w) => {
            (w.reports ?? []).forEach((r) => {
                reportRows.push([
                    w.label, r.date,
                    richContentPlainText(r.goals) || '—',
                    richContentPlainText(r.plan) || '—',
                ]);
            });
        });
        const wsReports = buildSheet({
            title: 'GHI CHÚ BÁO CÁO NGÀY',
            header: reportHeader,
            rows: reportRows.length ? reportRows : [['—', '—', 'Không có báo cáo ngày trong kỳ', '']],
            colWidths: [12, 14, 50, 50],
            wrapCols: [2, 3],
            alignCols: { 1: 'center' },
        });
        XLSX.utils.book_append_sheet(wb, wsReports, 'Bao cao ngay');

        XLSX.writeFile(wb, `audit-${slug(member)}-${stamp()}.xlsx`);
    }

    function printReport() {
        window.print();
    }

    /** Xuất danh sách audit (trang hiện tại). */
    function exportAuditList(rows, filter = {}) {
        const wb = XLSX.utils.book_new();
        const periodLabel = filter?.label ?? '';

        const header = [
            '#', 'Thành viên', 'Vai trò', 'Đơn vị', 'Kỳ', 'Cam kết', 'Hoàn thành',
            'Tỷ lệ cam kết %', 'Hiệu suất %', 'Xếp loại', 'Hạng',
        ];
        const dataRows = (rows ?? []).map((r, i) => [
            i + 1,
            r.name,
            displayOrEmpty(r.role, EMPTY_LABELS.notUpdated),
            displayOrEmpty(r.unitName ?? r.teamName, EMPTY_LABELS.team),
            displayOrEmpty(r.periodLabel ?? periodLabel, EMPTY_LABELS.period),
            r.committed ?? 0,
            r.done ?? 0,
            r.commitmentRate ?? 0,
            r.avgScore ?? 0,
            auditGradeLabel(r.grade, (r.committed ?? 0) > 0),
            r.rank ?? '',
        ]);

        const ws = buildSheet({
            title: 'DANH SÁCH AUDIT NHÂN SỰ',
            subtitle: periodLabel ? `Kỳ: ${periodLabel}` : '',
            header,
            rows: dataRows.length ? dataRows : [['1', EMPTY_LABELS.generic, '', '', '', '', '', '', '', '', '']],
            colWidths: [4, 26, 18, 20, 22, 10, 12, 14, 12, 10, 8],
            alignCols: {
                0: 'center', 5: 'center', 6: 'center', 7: 'center', 8: 'center', 10: 'center',
            },
        });
        XLSX.utils.book_append_sheet(wb, ws, 'Danh sach');
        XLSX.writeFile(wb, `audit-danh-sach-${stamp()}.xlsx`);
    }

    return { exportDashboard, exportAudit, exportAuditList, printReport };
}

function stamp() {
    return new Date().toISOString().slice(0, 10);
}

function slug(s) {
    return (s || 'nhan-su')
        .normalize('NFD').replace(/[̀-ͯ]/g, '')
        .replace(/[đĐ]/g, 'd')
        .toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/(^-|-$)/g, '');
}
