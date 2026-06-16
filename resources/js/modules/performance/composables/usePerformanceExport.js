import * as XLSX from 'xlsx-js-style';

/**
 * Xuất báo cáo hiệu suất ra Excel có style (brand) + in báo cáo.
 * Toàn bộ I/O Excel nằm ở composable — KHÔNG import xlsx trong .vue (theo
 * "tham chiếu vàng" của dự án).
 */
const BRAND = '9A0036';
const BRAND_SOFT = 'FDF2F6';

function headerStyle() {
    return {
        font: { bold: true, color: { rgb: 'FFFFFF' }, sz: 11 },
        fill: { fgColor: { rgb: BRAND } },
        alignment: { horizontal: 'center', vertical: 'center' },
        border: thinBorder(),
    };
}

function thinBorder() {
    const side = { style: 'thin', color: { rgb: 'E2E8F0' } };
    return { top: side, bottom: side, left: side, right: side };
}

function titleStyle() {
    return { font: { bold: true, sz: 14, color: { rgb: BRAND } } };
}

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

    function exportAudit(audit) {
        if (!audit) return;
        const wb = XLSX.utils.book_new();
        const member = audit.member?.name ?? '';

        const rows = [
            [`AUDIT NHÂN SỰ — ${member}`],
            [],
            ['Kỳ', 'Cam kết', 'Hoàn thành', 'Tỷ lệ %', 'Điểm', 'Đúng hạn %', 'Chất lượng %', 'Xếp loại'],
            ...(audit.weeks ?? []).map((w) => [
                w.label, w.summary.committed, w.summary.done, w.summary.commitmentRate,
                w.scores.performance, w.scores.onTime, w.scores.quality, w.grade,
            ]),
        ];
        const ws = aoaSheet(rows, { headerRow: 2, colWidths: [16, 10, 12, 9, 8, 11, 12, 9] });
        ws['A1'].s = titleStyle();
        XLSX.utils.book_append_sheet(wb, ws, 'Audit');
        XLSX.writeFile(wb, `audit-${slug(member)}-${stamp()}.xlsx`);
    }

    function printReport() {
        window.print();
    }

    return { exportDashboard, exportAudit, printReport };
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
