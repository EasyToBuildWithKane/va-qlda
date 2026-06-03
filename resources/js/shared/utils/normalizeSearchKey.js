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

/** Tìm trên một chuỗi (giữ tương thích cũ). */
export function matchesSearchKey(text, query) {
    return matchesSearchQuery([text], query);
}

/**
 * Tìm trên nhiều trường: khớp chuỗi liên tục HOẶC mọi từ trong query đều xuất hiện (bỏ qua thứ tự).
 * VD: "Toàn Bùi" khớp "Bùi Quang Toàn"; "cntt" khớp email/chức danh nếu có trong fields.
 */
export function matchesSearchQuery(fields, query) {
    const q = normalizeSearchKey(query);
    if (!q) return true;
    const haystack = fields.map((f) => normalizeSearchKey(f)).filter(Boolean).join(' ');
    if (!haystack) return false;
    if (haystack.includes(q)) return true;
    const tokens = q.split(' ').filter(Boolean);
    return tokens.length > 0 && tokens.every((t) => haystack.includes(t));
}
