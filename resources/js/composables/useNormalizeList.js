/** Chuẩn hoá dữ liệu Inertia/Laravel → mảng object an toàn cho v-for. */
export function normalizeList(source) {
    if (!source) return [];
    if (Array.isArray(source)) return source.filter(Boolean);
    if (typeof source === 'object' && source.data != null) {
        const inner = source.data;
        if (Array.isArray(inner)) return inner.filter(Boolean);
        if (typeof inner === 'object') return Object.values(inner).filter(Boolean);
    }
    if (typeof source === 'object') {
        return Object.values(source).filter(Boolean);
    }
    return [];
}

/** Mảng entity có `id` — dùng cho :key="item.id" trong v-for. */
export function normalizeEntities(source) {
    return normalizeList(source).filter((x) => x != null && x.id != null);
}

/** Mảng object có field `key` (hoặc tên khác) — dùng cho :key="item.key" trong v-for. */
export function normalizeKeyed(source, field = 'key') {
    return normalizeList(source).filter((x) => x != null && x[field] != null);
}
