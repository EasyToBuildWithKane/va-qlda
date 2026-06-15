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

/**
 * Lịch dự án từ danh sách task (client-side, không cần feed server).
 * @param {{ tasks: import('vue').MaybeRefOrGetter<Array>, sprints?: any, editable?: any }} props
 */
export function useProjectCalendar(props) {
    const tasks = computed(() => toValue(props.tasks) || []);
    const sprints = computed(() => toValue(props.sprints) || []);
    const editable = computed(() => !!toValue(props.editable));

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

    const events = computed(() =>
        tasks.value.flatMap((task) => {
            const sched = scheduleOf(task);
            if (!sched) return [];
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
            return [ev];
        }),
    );

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

    /** Chú thích màu — chỉ các trạng thái thực sự có trên lịch. */
    const legend = computed(() => {
        const seen = new Map();
        let hasMilestone = false;
        tasks.value.forEach((t) => {
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

    const hasScheduledData = computed(() => events.value.length > 0);

    return {
        events,
        kpis,
        legend,
        hasScheduledData,
        toYmd,
        addDays,
        isMilestone,
    };
}
