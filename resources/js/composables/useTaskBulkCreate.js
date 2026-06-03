/** @typedef {{ id: string, title: string, selected: boolean }} BulkRow */

export const BULK_MAX_ROWS = 50;

const SAMPLE_LINES = [
    'Phân tích yêu cầu nghiệp vụ',
    'Thiết kế giao diện màn hình chính',
    'Phát triển API tích hợp',
    'Kiểm thử & nghiệm thu',
];

let _seq = 0;
export const nextBulkRowId = () => `bulk-${Date.now()}-${++_seq}`;

/**
 * Chuẩn hoá dòng nhập: bỏ số thứ tự, bullet, tab từ Excel.
 * @param {string} line
 */
export function normalizeBulkLine(line) {
    return String(line ?? '')
        .replace(/^\s*[\d]+[.)-]\s*/, '')
        .replace(/^\s*[-*•]\s*/, '')
        .replace(/\t/g, ' ')
        .trim();
}

/**
 * @param {string} text
 * @returns {BulkRow[]}
 */
export function parseBulkText(text) {
    const lines = String(text ?? '').split(/\r?\n/);
    const rows = [];
    for (const line of lines) {
        const title = normalizeBulkLine(line);
        if (!title) continue;
        rows.push({ id: nextBulkRowId(), title, selected: true });
        if (rows.length >= BULK_MAX_ROWS) break;
    }
    return rows;
}

/**
 * @param {BulkRow[]} rows
 * @param {string[]} existingTitles
 */
export function validateBulkRows(rows, existingTitles = []) {
    const seen = new Map();
    const existing = new Set(
        (existingTitles || []).map((t) => String(t).toLowerCase().trim()).filter(Boolean),
    );

    return rows.map((row) => {
        const errors = [];
        const title = (row.title || '').trim();

        if (!title) {
            errors.push({ code: 'empty', message: 'Tiêu đề trống' });
        } else if (title.length > 255) {
            errors.push({ code: 'too_long', message: 'Tối đa 255 ký tự' });
        } else {
            const key = title.toLowerCase();
            if (seen.has(key)) {
                errors.push({ code: 'duplicate', message: 'Trùng trong danh sách' });
            } else {
                seen.set(key, true);
            }
            if (existing.has(key)) {
                errors.push({ code: 'exists', message: 'Đã có trong dự án' });
            }
        }

        return { ...row, title, errors };
    });
}

/** @param {BulkRow[]} rows */
export function rowsToBulkText(rows) {
    return rows.map((r) => r.title).join('\n');
}

export function getBulkSampleText() {
    return SAMPLE_LINES.join('\n');
}

/**
 * @param {ReturnType<typeof validateBulkRows>} validated
 */
export function bulkValidationSummary(validated) {
    const selected = validated.filter((r) => r.selected);
    const valid = selected.filter((r) => !r.errors?.length);
    const withErrors = selected.filter((r) => r.errors?.length);
    const duplicates = selected.filter((r) => r.errors?.some((e) => e.code === 'duplicate'));
    const exists = selected.filter((r) => r.errors?.some((e) => e.code === 'exists'));

    return {
        total: validated.length,
        selected: selected.length,
        valid: valid.length,
        invalid: withErrors.length,
        duplicates: duplicates.length,
        exists: exists.length,
        canSubmit: valid.length > 0 && valid.length <= BULK_MAX_ROWS,
    };
}
