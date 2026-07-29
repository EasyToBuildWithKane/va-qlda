import axios from 'axios';
import XLSX from 'xlsx-js-style';
import { date, datetime } from '@/composables/useFormat';

const BRAND = '9A0036';
const SLATE_50 = 'F8FAFC';
const SLATE_200 = 'E2E8F0';
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
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
    cellAlt: {
        font: { sz: 10, color: { rgb: '334155' } },
        fill: { fgColor: { rgb: SLATE_50 } },
        alignment: { vertical: 'center', wrapText: true },
        border: borderThin(),
    },
};

function setCell(ws, r, c, value, style) {
    const ref = XLSX.utils.encode_cell({ r, c });
    ws[ref] = { v: value ?? '', t: typeof value === 'number' ? 'n' : 's', s: style };
}

function filterNote(filters) {
    const parts = [];
    if (filters?.q) parts.push(`Từ khóa: ${filters.q}`);
    if (filters?.category_id) parts.push(`Danh mục #${filters.category_id}`);
    if (filters?.tag) parts.push(`Thẻ: ${filters.tag}`);
    if (filters?.status) parts.push(`Trạng thái: ${filters.status}`);
    return parts.length ? parts.join(' · ') : 'Không lọc';
}

const HEADERS = ['STT', 'Tiêu đề', 'Danh mục', 'Trạng thái', 'Tác giả', 'Thẻ', 'Lượt xem', 'Cập nhật', 'Slug'];

function rowFromArticle(a, index) {
    const tags = (a.tags ?? []).map((t) => t.name).join(', ');
    return [
        index + 1,
        a.title ?? '',
        a.category?.name ?? '',
        a.status?.label ?? '',
        a.author?.full_name ?? '',
        tags,
        a.view_count ?? 0,
        a.updated_at ? datetime(a.updated_at) : '',
        a.slug ?? '',
    ];
}

function buildWorkbook(articles, filters) {
    const ws = {};
    const range = { s: { r: 0, c: 0 }, e: { r: 0, c: HEADERS.length - 1 } };

    setCell(ws, 0, 0, 'VA-Workspace — Cơ sở tri thức', S.title);
    setCell(ws, 1, 0, `Xuất: ${date(new Date().toISOString())} · ${filterNote(filters)} · Tối đa 200 bản ghi`, S.subtitle);

    const headerRow = 3;
    HEADERS.forEach((h, c) => setCell(ws, headerRow, c, h, S.header));

    articles.forEach((a, i) => {
        const r = headerRow + 1 + i;
        const style = i % 2 === 0 ? S.cell : S.cellAlt;
        rowFromArticle(a, i).forEach((val, c) => setCell(ws, r, c, val, style));
        range.e.r = r;
    });

    ws['!ref'] = XLSX.utils.encode_range(range);
    ws['!cols'] = [
        { wch: 5 }, { wch: 36 }, { wch: 18 }, { wch: 12 }, { wch: 20 },
        { wch: 24 }, { wch: 10 }, { wch: 18 }, { wch: 28 },
    ];

    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, 'Bai viet');
    return wb;
}

export async function fetchKbArticlesForExport(params) {
    const { data } = await axios.get(route('knowledge-base.export-data'), { params });
    return data;
}

export async function exportKbArticlesWorkbook({ params = {} }) {
    const { articles, filters } = await fetchKbArticlesForExport(params);
    const wb = buildWorkbook(articles ?? [], filters ?? {});
    const stamp = new Date().toISOString().slice(0, 10);
    XLSX.writeFile(wb, `VA_TriThuc_${stamp}.xlsx`);
    return `VA_TriThuc_${stamp}.xlsx`;
}

export function exportKbArticlesCsv(articles, filters = {}) {
    const lines = [
        ['VA-Workspace — Cơ sở tri thức', filterNote(filters)].join(','),
        HEADERS.join(','),
        ...(articles ?? []).map((a, i) => rowFromArticle(a, i).map((v) => `"${String(v).replace(/"/g, '""')}"`).join(',')),
    ];
    const blob = new Blob(['\uFEFF' + lines.join('\n')], { type: 'text/csv;charset=utf-8' });
    const stamp = new Date().toISOString().slice(0, 10);
    const name = `VA_TriThuc_${stamp}.csv`;
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = name;
    a.click();
    URL.revokeObjectURL(url);
    return name;
}
