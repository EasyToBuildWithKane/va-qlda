import { date, hours as fmtHours } from '@/composables/useFormat';

export function sessionStatusColor(value) {
    if (value === 'in_progress') return 'amber';
    if (value === 'completed') return 'emerald';
    if (value === 'cancelled') return 'rose';
    return 'slate';
}

export function displaySessionDate(val) {
    return val ? date(val) : 'Chưa lên lịch';
}

export function displaySessionHours(val) {
    return val != null ? fmtHours(val) : 'Chưa ghi giờ';
}

export function displaySessionTimeRange(s) {
    if (s.start_time && s.end_time) return `${s.start_time.slice(0, 5)} – ${s.end_time.slice(0, 5)}`;
    if (s.start_time) return `Bắt đầu ${s.start_time.slice(0, 5)}`;
    if (s.end_time) return `Kết thúc ${s.end_time.slice(0, 5)}`;
    return 'Chưa xếp giờ';
}

export function displaySessionTopic(val) {
    return val?.trim() ? val : 'Chưa có chủ đề';
}

export function displayMaterialsCount(s) {
    const n = s.materials_count ?? s.materials?.length ?? 0;
    return n > 0 ? `${n} tài liệu` : 'Chưa có tài liệu';
}

export function displayAssignmentsCount(s) {
    const n = s.assignments_count ?? s.assignments?.length ?? 0;
    return n > 0 ? `${n} bài tập` : 'Chưa có bài tập';
}

/** @param {Array<object>} items */
export function groupSessionsByCourse(items) {
    const map = new Map();
    items.forEach((s) => {
        const key = s.course?.id ?? `orphan-${s.id}`;
        if (!map.has(key)) {
            map.set(key, {
                key,
                course: s.course,
                label: s.course ? `${s.course.code} · ${s.course.name}` : 'Chưa gán khóa học',
                items: [],
            });
        }
        map.get(key).items.push(s);
    });
    return [...map.values()]
        .map((g) => ({
            ...g,
            stats: groupStats(g.items),
        }))
        .sort((a, b) => a.label.localeCompare(b.label, 'vi'));
}

/** @param {Array<object>} items */
export function groupStats(items) {
    let completed = 0;
    let hours = 0;
    items.forEach((s) => {
        if (s.status?.value === 'completed') completed += 1;
        if (s.total_hours != null) hours += Number(s.total_hours);
    });
    return { completed, hours: Math.round(hours * 100) / 100, total: items.length };
}

export function formatMonthBounds(offsetMonths = 0) {
    const d = new Date();
    d.setDate(1);
    d.setMonth(d.getMonth() + offsetMonths);
    const from = d.toISOString().slice(0, 10);
    const end = new Date(d.getFullYear(), d.getMonth() + 1, 0);
    const to = end.toISOString().slice(0, 10);
    return { from, to };
}
