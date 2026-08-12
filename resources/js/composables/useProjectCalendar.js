import { computed, toValue } from 'vue';
import { isTaskOverdue } from '@/composables/useTaskTimeliness';

/**
 * Bảng màu lịch theo trạng thái công việc.
 * main: viền + chấm · soft: nền · text: chữ.
 */
export const CALENDAR_STATUS_PALETTE = {
    todo: { main: '#64748b', soft: '#f1f5f9', text: '#334155' },
    in_progress: { main: '#0ea5e9', soft: '#e0f2fe', text: '#075985' },
    in_review: { main: '#f59e0b', soft: '#fef3c7', text: '#92400e' },
    done: { main: '#10b981', soft: '#d1fae5', text: '#065f46' },
    blocked: { main: '#ef4444', soft: '#fee2e2', text: '#991b1b' },
    on_hold: { main: '#a855f7', soft: '#f3e8ff', text: '#6b21a8' },
};

const FALLBACK_PALETTE = { main: '#64748b', soft: '#f1f5f9', text: '#334155' };
export const MILESTONE_PALETTE = { main: '#7c3aed', soft: '#ede9fe', text: '#5b21b6' };

const parseDate = (x) => (x ? new Date(`${x}T00:00:00`) : null);

const pad = (n) => String(n).padStart(2, '0');
const toYmd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

/** Cộng n ngày vào chuỗi YYYY-MM-DD. */
const addDays = (ymd, n) => {
    const d = parseDate(ymd);
    if (!d) return null;
    d.setDate(d.getDate() + n);
    return toYmd(d);
};

const getAssignees = (t) => (t.assignees?.length ? t.assignees : t.assignee ? [t.assignee] : []);

const isMilestone = (t) =>
    !!(t.is_milestone || t.type?.value === 'milestone'
        || /milestone|go ?live|uat complete|sprint \d+ complete/i.test(t.title || ''));

export const paletteFor = (task) => {
    if (isMilestone(task)) return MILESTONE_PALETTE;
    return CALENDAR_STATUS_PALETTE[task.status?.value] || FALLBACK_PALETTE;
};

const normalizeSearch = (q) => String(q || '')
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .toLowerCase()
    .trim();

/**
 * Lịch dự án từ danh sách task (client-side, không cần feed server).
 * @param {{
 *   tasks: import('vue').MaybeRefOrGetter<Array>,
 *   sprints?: import('vue').MaybeRefOrGetter<Array>,
 *   editable?: import('vue').MaybeRefOrGetter<boolean>,
 *   filters?: import('vue').MaybeRefOrGetter<{
 *     search?: string,
 *     status?: string|null,
 *     sprintId?: number|null,
 *     assigneeId?: number|null,
 *     kpi?: string|null,
 *   }>,
 * }} props
 */
export function useProjectCalendar(props) {
    const tasks = computed(() => toValue(props.tasks) || []);
    const sprints = computed(() => toValue(props.sprints) || []);
    const editable = computed(() => !!toValue(props.editable));
    const filters = computed(() => toValue(props.filters) || {});

    const sprintNameById = computed(() => {
        const map = new Map();
        sprints.value.forEach((s) => map.set(s.id, s.name));
        return map;
    });

    /** Khung ngày của một task: { start, endExclusive, single }. null nếu chưa có lịch. */
    const scheduleOf = (task) => {
        const milestone = isMilestone(task);
        const start = task.start_date || (milestone ? task.due_date : null);
        const due = task.due_date || task.start_date;
        if (milestone) {
            const day = task.due_date || task.start_date;
            return day ? { start: day, endExclusive: null, single: true } : null;
        }
        if (!start && !due) return null;
        const s = start || due;
        const e = due || start;
        if (s === e) return { start: s, endExclusive: null, single: true };
        return { start: s, endExclusive: addDays(e, 1), single: false };
    };

    const matchesFilters = (task) => {
        const f = filters.value;
        const people = getAssignees(task);
        const q = normalizeSearch(f.search);
        if (q) {
            const hay = normalizeSearch([
                task.title,
                task.id,
                task.status?.label,
                ...people.map((a) => a.name),
                task.sprint_id ? sprintNameById.value.get(task.sprint_id) : '',
            ].filter(Boolean).join(' '));
            if (!hay.includes(q)) return false;
        }
        if (f.status) {
            if (task.status?.value !== f.status) return false;
        }
        const sprintId = f.sprintId === '' || f.sprintId == null ? null : Number(f.sprintId);
        if (sprintId != null && !Number.isNaN(sprintId) && Number(task.sprint_id) !== sprintId) return false;
        const assigneeId = f.assigneeId === '' || f.assigneeId == null ? null : Number(f.assigneeId);
        if (assigneeId != null && !Number.isNaN(assigneeId)
            && !people.some((a) => Number(a.id) === assigneeId)) return false;

        const kpi = f.kpi || null;
        if (kpi === 'inProgress' && task.status?.value !== 'in_progress') return false;
        if (kpi === 'completed' && task.status?.value !== 'done') return false;
        if (kpi === 'overdue' && (isMilestone(task) || !isTaskOverdue(task))) return false;
        if (kpi === 'milestones' && !isMilestone(task)) return false;
        if (kpi === 'unscheduled' && scheduleOf(task) !== null) return false;
        return true;
    };

    const filteredTasks = computed(() => tasks.value.filter(matchesFilters));

    const toEvent = (task) => {
        const sched = scheduleOf(task);
        if (!sched) return null;
        const milestone = isMilestone(task);
        const palette = paletteFor(task);
        const overdue = !milestone && isTaskOverdue(task);
        const done = task.status?.value === 'done';
        const people = getAssignees(task);

        const ev = {
            id: String(task.id),
            title: task.title || 'Không tiêu đề',
            start: sched.start,
            allDay: true,
            backgroundColor: palette.soft,
            borderColor: palette.main,
            textColor: palette.text,
            editable: editable.value && !milestone,
            durationEditable: editable.value && !milestone && !sched.single,
            classNames: [
                'pc-ev',
                milestone ? 'pc-ev--milestone' : '',
                overdue ? 'pc-ev--overdue' : '',
                done ? 'pc-ev--done' : '',
            ].filter(Boolean),
            extendedProps: {
                taskId: task.id,
                milestone,
                overdue,
                done,
                accent: palette.main,
                status: task.status?.value || null,
                statusLabel: task.status?.label || null,
                priorityLabel: task.priority?.label || null,
                progress: Number(task.progress) || 0,
                sprintName: task.sprint_id ? sprintNameById.value.get(task.sprint_id) || null : null,
                assignees: people.map((a) => ({ id: a.id, name: a.name, avatar: a.avatar_path })),
                startDate: task.start_date || null,
                dueDate: task.due_date || null,
            },
        };
        if (sched.endExclusive) ev.end = sched.endExclusive;
        return ev;
    };

    const events = computed(() => {
        if (filters.value.kpi === 'unscheduled') return [];
        return filteredTasks.value.map(toEvent).filter(Boolean);
    });

    /** KPI luôn trên toàn bộ task (không theo filter) để chip vẫn phản ánh tổng quan. */
    const kpis = computed(() => {
        const list = tasks.value;
        const scheduled = list.filter((t) => scheduleOf(t) !== null).length;
        return {
            total: list.length,
            scheduled,
            unscheduled: list.length - scheduled,
            completed: list.filter((t) => t.status?.value === 'done').length,
            inProgress: list.filter((t) => t.status?.value === 'in_progress').length,
            overdue: list.filter((t) => !isMilestone(t) && isTaskOverdue(t)).length,
            milestones: list.filter(isMilestone).length,
        };
    });

    /** Chú thích màu — chỉ các trạng thái thực sự có trên lịch (sau filter). */
    const legend = computed(() => {
        const seen = new Map();
        let hasMilestone = false;
        const source = filters.value.kpi === 'unscheduled' ? [] : filteredTasks.value;
        source.forEach((t) => {
            if (scheduleOf(t) === null) return;
            if (isMilestone(t)) {
                hasMilestone = true;
                return;
            }
            const value = t.status?.value;
            if (value && !seen.has(value)) {
                seen.set(value, {
                    value,
                    label: t.status?.label || value,
                    color: (CALENDAR_STATUS_PALETTE[value] || FALLBACK_PALETTE).main,
                });
            }
        });
        const items = [...seen.values()];
        if (hasMilestone) {
            items.push({ value: '__milestone', label: 'Mốc dự án', color: MILESTONE_PALETTE.main, milestone: true });
        }
        return items;
    });

    const unscheduledTasks = computed(() =>
        filteredTasks.value
            .filter((t) => scheduleOf(t) === null)
            .map((t) => ({
                id: t.id,
                title: t.title || 'Không tiêu đề',
                status: t.status?.value || null,
                statusLabel: t.status?.label || null,
                priorityLabel: t.priority?.label || null,
                accent: paletteFor(t).main,
                assignees: getAssignees(t).map((a) => ({ id: a.id, name: a.name, avatar: a.avatar_path })),
                sprintName: t.sprint_id ? sprintNameById.value.get(t.sprint_id) || null : null,
            })),
    );

    const statusFilterOptions = computed(() => {
        const seen = new Map();
        tasks.value.forEach((t) => {
            const value = t.status?.value;
            if (value && !seen.has(value)) {
                seen.set(value, { value, label: t.status?.label || value });
            }
        });
        return [...seen.values()];
    });

    const assigneeFilterOptions = computed(() => {
        const seen = new Map();
        tasks.value.forEach((t) => {
            getAssignees(t).forEach((a) => {
                if (a?.id != null && !seen.has(a.id)) {
                    seen.set(a.id, { value: a.id, label: a.name || `Nhân sự #${a.id}` });
                }
            });
        });
        return [...seen.values()].sort((a, b) => a.label.localeCompare(b.label, 'vi'));
    });

    const sprintFilterOptions = computed(() =>
        sprints.value.map((s) => ({ value: s.id, label: s.name || `Sprint #${s.id}` })),
    );

    const hasScheduledData = computed(() => tasks.value.some((t) => scheduleOf(t) !== null));
    const hasVisibleEvents = computed(() => events.value.length > 0);
    const hasActiveFilters = computed(() => {
        const f = filters.value;
        return !!(f.search || f.status || f.sprintId != null || f.assigneeId != null || f.kpi);
    });

    return {
        events,
        kpis,
        legend,
        hasScheduledData,
        hasVisibleEvents,
        hasActiveFilters,
        unscheduledTasks,
        statusFilterOptions,
        assigneeFilterOptions,
        sprintFilterOptions,
        filteredTasks,
        toYmd,
        addDays,
        isMilestone,
        scheduleOf,
    };
}
