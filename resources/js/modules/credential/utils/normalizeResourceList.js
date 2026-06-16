/**
 * Chuẩn hóa danh sách từ Inertia resolve() hoặc API JsonResource (có thể bọc `data`).
 */
export function normalizeResourceList(raw) {
    if (!raw) return [];
    if (Array.isArray(raw)) return raw;
    if (Array.isArray(raw.data)) return raw.data;
    return [];
}
