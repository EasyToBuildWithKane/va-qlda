/** Chuẩn hóa chuỗi tìm kiếm (bỏ dấu, lowercase) — dùng cho autocomplete tiếng Việt. */
export function normalizeSearchKey(val) {
    return String(val ?? '')
        .trim()
        .toLowerCase()
        .replace(/đ/g, 'd')
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .replace(/\s+/g, ' ');
}

export function matchesSearchKey(text, query) {
    const q = normalizeSearchKey(query);
    if (!q) return true;
    return normalizeSearchKey(text).includes(q);
}
