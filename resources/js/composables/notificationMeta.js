/** Labels & styling for notification categories / priorities (mirrors PHP enums). */

export const NOTIFICATION_TABS = [
    { id: 'all', label: 'Tất cả' },
    { id: 'unread', label: 'Chưa đọc' },
    { id: 'read', label: 'Đã đọc' },
];

export const PRIORITY_STYLES = {
    critical: 'bg-rose-100 text-rose-800 ring-rose-200 dark:bg-rose-950/60 dark:text-rose-200 dark:ring-rose-900',
    high: 'bg-amber-100 text-amber-900 ring-amber-200 dark:bg-amber-950/50 dark:text-amber-100 dark:ring-amber-900',
    medium: 'bg-sky-100 text-sky-800 ring-sky-200 dark:bg-sky-950/50 dark:text-sky-100 dark:ring-sky-900',
    low: 'bg-slate-100 text-slate-600 ring-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:ring-slate-700',
};

export const CATEGORY_ICONS = {
    task: 'task',
    sprint: 'sprint',
    project: 'projects',
    document: 'documents',
    comment: 'feedback',
    system: 'system-config',
    admin: 'account',
};

export function groupNotificationsByDate(items) {
    const groups = [];
    const map = new Map();

    for (const n of items) {
        const d = new Date(n.created_at);
        const key = d.toLocaleDateString('vi-VN', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
        if (!map.has(key)) {
            const g = { label: key, items: [] };
            map.set(key, g);
            groups.push(g);
        }
        map.get(key).items.push(n);
    }

    return groups;
}

export function formatTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleTimeString('vi-VN', { hour: '2-digit', minute: '2-digit' });
}
