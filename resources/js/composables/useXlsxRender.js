/**
 * Excel → HTML giữ nguyên định dạng bản gốc.
 *
 * `sheet_to_json` vứt bỏ merge/màu/border/độ rộng cột, nên ở đây dựng bảng
 * trực tiếp từ ô của worksheet. Yêu cầu workbook đọc với `cellStyles: true`,
 * nếu không `cell.s` sẽ rỗng và bảng trả về sẽ không có màu.
 */

const DEFAULT_COL_WIDTH_PX = 84;
const DEFAULT_ROW_HEIGHT_PX = 22;
const MAX_COLS = 200;

function escapeHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

/** Nhãn cột kiểu Excel: 0 → A, 25 → Z, 26 → AA. */
export function columnLabel(index) {
    let n = index;
    let label = '';
    while (n >= 0) {
        label = String.fromCharCode((n % 26) + 65) + label;
        n = Math.floor(n / 26) - 1;
    }
    return label;
}

/**
 * xlsx-js-style dùng nhiều dạng màu khác nhau (`rgb`, `fgColor`, indexed).
 * Chỉ nhận chuỗi hex thật; bỏ qua indexed vì cần bảng màu theme của file.
 */
function toCssColor(color) {
    if (!color) return '';
    const rgb = typeof color === 'string' ? color : color.rgb;
    if (typeof rgb !== 'string') return '';

    const hex = rgb.replace(/^#/, '').trim();
    if (/^[0-9a-fA-F]{8}$/.test(hex)) {
        // ARGB → bỏ alpha (Excel hầu như luôn dùng FF).
        return `#${hex.slice(2)}`;
    }
    if (/^[0-9a-fA-F]{6}$/.test(hex)) return `#${hex}`;
    return '';
}

const BORDER_WIDTH = {
    hair: '1px',
    thin: '1px',
    medium: '2px',
    thick: '3px',
    dotted: '1px',
    dashed: '1px',
    double: '3px',
};

const BORDER_STYLE = {
    dotted: 'dotted',
    dashed: 'dashed',
    double: 'double',
    hair: 'solid',
    thin: 'solid',
    medium: 'solid',
    thick: 'solid',
};

function borderCss(side) {
    if (!side || !side.style) return '';
    const width = BORDER_WIDTH[side.style] ?? '1px';
    const style = BORDER_STYLE[side.style] ?? 'solid';
    const color = toCssColor(side.color) || '#cbd5e1';
    return `${width} ${style} ${color}`;
}

const H_ALIGN = {
    left: 'left',
    center: 'center',
    right: 'right',
    justify: 'justify',
    centerContinuous: 'center',
    distributed: 'justify',
};

const V_ALIGN = {
    top: 'top',
    center: 'middle',
    bottom: 'bottom',
    justify: 'middle',
    distributed: 'middle',
};

/**
 * Style của xlsx-js-style khi đọc lại bị "phẳng": patternType/fgColor nằm ngay
 * trên `s` thay vì trong `s.fill`. Hỗ trợ cả hai dạng.
 */
function cellStyleCss(cell, isNumeric) {
    const s = cell?.s;
    const css = [];
    if (!s) {
        // Số canh phải là quy ước mặc định của Excel.
        return isNumeric ? 'text-align:right;' : '';
    }

    const fill = s.fill ?? s;
    const patternType = fill.patternType ?? fill.patternFill?.patternType;
    if (patternType !== 'none') {
        const bg = toCssColor(fill.fgColor ?? fill.bgColor);
        if (bg) css.push(`background-color:${bg}`);
    }

    const font = s.font ?? {};
    const fontColor = toCssColor(font.color);
    if (fontColor) css.push(`color:${fontColor}`);
    if (font.bold) css.push('font-weight:700');
    if (font.italic) css.push('font-style:italic');
    if (font.sz) css.push(`font-size:${Math.round(Number(font.sz) * 1.15)}px`);
    if (font.name) css.push(`font-family:${JSON.stringify(String(font.name))},sans-serif`);

    const decorations = [];
    if (font.underline) decorations.push('underline');
    if (font.strike) decorations.push('line-through');
    if (decorations.length) css.push(`text-decoration:${decorations.join(' ')}`);

    const align = s.alignment ?? {};
    const horizontal = H_ALIGN[align.horizontal];
    if (horizontal) css.push(`text-align:${horizontal}`);
    else if (isNumeric) css.push('text-align:right');

    const vertical = V_ALIGN[align.vertical];
    if (vertical) css.push(`vertical-align:${vertical}`);
    if (align.wrapText) css.push('white-space:pre-wrap');
    if (align.textRotation) {
        const deg = Number(align.textRotation);
        if (Number.isFinite(deg) && deg !== 0) {
            const angle = deg > 90 ? 90 - deg : -deg;
            css.push(`transform:rotate(${angle}deg)`);
        }
    }

    const border = s.border ?? {};
    ['top', 'right', 'bottom', 'left'].forEach((side) => {
        const value = borderCss(border[side]);
        if (value) css.push(`border-${side}:${value}`);
    });

    return css.length ? `${css.join(';')};` : '';
}

/** Map "ô bị nuốt bởi merge" → bỏ qua khi dựng bảng. */
function buildMergeMaps(merges) {
    const anchors = new Map();
    const covered = new Set();

    (merges ?? []).forEach((m) => {
        if (!m?.s || !m?.e) return;
        const { r: r1, c: c1 } = m.s;
        const { r: r2, c: c2 } = m.e;
        anchors.set(`${r1}:${c1}`, {
            rowspan: Math.max(1, r2 - r1 + 1),
            colspan: Math.max(1, c2 - c1 + 1),
        });
        for (let r = r1; r <= r2; r += 1) {
            for (let c = c1; c <= c2; c += 1) {
                if (r === r1 && c === c1) continue;
                covered.add(`${r}:${c}`);
            }
        }
    });

    return { anchors, covered };
}

function columnWidthPx(cols, index) {
    const col = cols?.[index];
    if (!col) return DEFAULT_COL_WIDTH_PX;
    if (Number.isFinite(col.wpx)) return Math.round(col.wpx);
    // wch = số ký tự; ~7px mỗi ký tự ở font mặc định của Excel.
    if (Number.isFinite(col.wch)) return Math.round(col.wch * 7 + 5);
    if (Number.isFinite(col.width)) return Math.round(col.width * 7 + 5);
    return DEFAULT_COL_WIDTH_PX;
}

function rowHeightPx(rows, index) {
    const row = rows?.[index];
    if (!row) return null;
    if (row.hidden) return 0;
    if (Number.isFinite(row.hpx)) return Math.round(row.hpx);
    if (Number.isFinite(row.hpt)) return Math.round(row.hpt * (96 / 72));
    return null;
}

/**
 * Giá trị hiển thị: ưu tiên `w` (đã áp number format của Excel) để giữ
 * tiền tệ / phần trăm / ngày đúng như bản gốc.
 */
function displayValue(cell) {
    if (!cell) return '';
    if (typeof cell.w === 'string' && cell.w !== '') return cell.w;
    if (cell.v == null) return '';
    if (cell.v instanceof Date && !Number.isNaN(cell.v.getTime())) {
        return cell.v.toLocaleDateString('vi-VN');
    }
    if (cell.t === 'b') return cell.v ? 'TRUE' : 'FALSE';
    return String(cell.v);
}

/**
 * Dựng HTML một trang của sheet.
 *
 * @param {object} XLSX  namespace xlsx-js-style (truyền vào để composable không tự import)
 * @param {object} sheet worksheet
 * @param {{ startRow?: number, endRow?: number, showGrid?: boolean }} options
 *        startRow/endRow là chỉ số dòng tuyệt đối trong sheet (0-based, đã gồm header).
 * @returns {{ html: string, totalRows: number, totalCols: number }}
 */
export function renderSheetToHtml(XLSX, sheet, options = {}) {
    if (!sheet || !sheet['!ref']) {
        return { html: '', totalRows: 0, totalCols: 0 };
    }

    const range = XLSX.utils.decode_range(sheet['!ref']);
    const totalRows = range.e.r - range.s.r + 1;
    const totalCols = Math.min(range.e.c - range.s.c + 1, MAX_COLS);

    const firstRow = Math.max(range.s.r, options.startRow ?? range.s.r);
    const lastRow = Math.min(range.e.r, options.endRow ?? range.e.r);
    const showGrid = options.showGrid !== false;

    const { anchors, covered } = buildMergeMaps(sheet['!merges']);
    const cols = sheet['!cols'];
    const rows = sheet['!rows'];
    const firstCol = range.s.c;
    const lastCol = firstCol + totalCols - 1;

    // <colgroup> giữ đúng độ rộng cột của Excel.
    const colGroup = [];
    colGroup.push('<col class="xr-gutter">');
    for (let c = firstCol; c <= lastCol; c += 1) {
        colGroup.push(`<col style="width:${columnWidthPx(cols, c)}px">`);
    }

    const headCells = [`<th class="xr-corner"></th>`];
    for (let c = firstCol; c <= lastCol; c += 1) {
        headCells.push(`<th class="xr-colhead">${columnLabel(c)}</th>`);
    }

    const bodyRows = [];
    for (let r = firstRow; r <= lastRow; r += 1) {
        const height = rowHeightPx(rows, r);
        if (height === 0) continue;

        const cells = [`<th class="xr-rowhead">${r + 1}</th>`];
        for (let c = firstCol; c <= lastCol; c += 1) {
            const key = `${r}:${c}`;
            if (covered.has(key)) continue;

            const address = XLSX.utils.encode_cell({ r, c });
            const cell = sheet[address];
            const span = anchors.get(key);
            const isNumeric = cell?.t === 'n';
            const text = displayValue(cell);
            const style = cellStyleCss(cell, isNumeric);

            const attrs = [];
            if (span?.rowspan > 1) attrs.push(`rowspan="${span.rowspan}"`);
            if (span?.colspan > 1) attrs.push(`colspan="${span.colspan}"`);
            if (style) attrs.push(`style="${style}"`);
            if (text) attrs.push(`title="${escapeHtml(text)}"`);

            cells.push(
                `<td ${attrs.join(' ')}>${escapeHtml(text)}</td>`,
            );
        }

        const rowStyle = height ? ` style="height:${height}px"` : '';
        bodyRows.push(`<tr${rowStyle}>${cells.join('')}</tr>`);
    }

    const gridClass = showGrid ? ' xr-grid' : '';
    const html = `
      <table class="xr-table${gridClass}">
        <colgroup>${colGroup.join('')}</colgroup>
        <thead><tr>${headCells.join('')}</tr></thead>
        <tbody>${bodyRows.join('')}</tbody>
      </table>
    `;

    return { html, totalRows, totalCols };
}

export { DEFAULT_ROW_HEIGHT_PX };
