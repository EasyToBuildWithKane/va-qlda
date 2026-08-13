import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const PRIORITY_WEIGHT = { urgent: 4, high: 3, medium: 2, low: 1 };

export function formatTaskDate(value) {
    if (!value) return null;
    const d = new Date(`${value}T00:00:00`);
    if (Number.isNaN(d.getTime())) return null;
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

export function overdueDays(task) {
    if (!task?.is_late || !task.due_date) return 0;
    const due = new Date(`${task.due_date}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.max(0, Math.round((today - due) / 86400000));
}

export function isDueToday(task) {
    if (!task?.due_date) return false;
    const due = new Date(`${task.due_date}T00:00:00`);
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return due.getTime() === today.getTime();
}

export function projectLabel(task) {
    const p = task?.project;
    if (!p) return null;
    if (p.code && p.name) return `${p.code} · ${p.name}`;
    return p.name || p.code || null;
}

export function hoursLabel(hours) {
    if (hours == null || hours === '' || Number(hours) <= 0) return null;
    const n = Number(hours);
    if (!Number.isFinite(n)) return null;
    return `${Number.isInteger(n) ? n : n.toFixed(1)}h`;
}

export function progressValue(task) {
    const p = Number(task?.progress ?? 0);
    return Number.isFinite(p) ? Math.min(100, Math.max(0, p)) : 0;
}

export function dueToneClass(task) {
    if (task?.is_late) return 'font-semibold text-rose-600';
    if (isDueToday(task)) return 'font-semibold text-amber-700';
    return 'text-slate-700 dark:text-slate-200';
}

/** Chấm màu — thay badge pill. */
export const TONE_DOT = {
    slate: 'bg-slate-400',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
    indigo: 'bg-indigo-500',
    teal: 'bg-teal-500',
    orange: 'bg-orange-500',
    brand: 'bg-brand',
};

/** Chữ màu — thay badge pill. */
export const TONE_TEXT = {
    slate: 'text-slate-600 dark:text-slate-300',
    sky: 'text-sky-700 dark:text-sky-300',
    violet: 'text-violet-700 dark:text-violet-300',
    emerald: 'text-emerald-700 dark:text-emerald-300',
    rose: 'text-rose-700 dark:text-rose-300',
    amber: 'text-amber-700 dark:text-amber-300',
    indigo: 'text-indigo-700 dark:text-indigo-300',
    teal: 'text-teal-700 dark:text-teal-300',
    orange: 'text-orange-700 dark:text-orange-300',
    brand: 'text-brand',
};

export function toneDotClass(color) {
    return TONE_DOT[color] || TONE_DOT.slate;
}

export function toneTextClass(color) {
    return TONE_TEXT[color] || TONE_TEXT.slate;
}

export function personName(person) {
    return displayOrEmpty(person?.name, EMPTY_LABELS.notUpdated);
}

function textValue(task, key) {
    switch (key) {
        case 'project':
            return projectLabel(task) ?? '';
        case 'status':
            return task.status?.label ?? '';
        case 'priority':
            return PRIORITY_WEIGHT[task.priority?.value] ?? 0;
        case 'due_date':
            return task.due_date ?? '';
        case 'progress':
            return progressValue(task);
        case 'logged_today':
            return Number(task.logged_today ?? 0);
        case 'estimate':
            return Number(task.estimate_hours ?? 0);
        case 'sprint':
            return task.sprint?.name ?? task.phase?.label ?? '';
        case 'phase':
            return task.phase?.label ?? '';
        case 'source':
            return task.source?.label ?? '';
        case 'start_date':
            return task.start_date ?? '';
        case 'story_points':
            return Number(task.story_points ?? 0);
        case 'actual_hours':
            return Number(task.actual_hours ?? 0);
        case 'milestone':
            return task.is_milestone ? 1 : 0;
        case 'sla':
            return task.sla_result?.label ?? '';
        case 'epic':
            return task.epic?.name ?? '';
        case 'parent':
            return task.parent?.title ?? '';
        case 'assignee':
            return task.assignee?.name ?? '';
        case 'reporter':
            return task.reporter?.name ?? '';
        case 'reviewer':
            return task.reviewer?.name ?? '';
        case 'title':
            return task.title ?? '';
        default:
            return task[key] ?? '';
    }
}

export function compareTasks(a, b, key) {
    const va = textValue(a, key);
    const vb = textValue(b, key);
    if (typeof va === 'number' && typeof vb === 'number') return va - vb;
    const emptyA = va === '' || va == null;
    const emptyB = vb === '' || vb == null;
    if (emptyA && emptyB) return 0;
    if (emptyA) return 1;
    if (emptyB) return -1;
    return String(va).localeCompare(String(vb), 'vi', { numeric: true, sensitivity: 'base' });
}

export function sortTasks(list, sortKey, sortDir) {
    if (!sortKey) return list;
    const dir = sortDir === 'desc' ? -1 : 1;
    return [...list].sort((a, b) => compareTasks(a, b, sortKey) * dir);
}
