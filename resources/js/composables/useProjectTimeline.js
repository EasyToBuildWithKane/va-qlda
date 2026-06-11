import { computed, ref, reactive, toValue } from 'vue';
import { isTaskOverdue } from '@/composables/useTaskTimeliness';
import { groupTasksByPhase } from '@/composables/useTaskPhaseGroups';
import { buildTaskDisplayRows, countTaskTree } from '@/composables/useTaskHierarchy';
import { sortSprints } from '@/composables/useSprintSort';

export const TIMELINE_STATUS_BAR = {
    todo: 'bg-slate-400',
    in_progress: 'bg-sky-500',
    in_review: 'bg-amber-500',
    done: 'bg-emerald-500',
    blocked: 'bg-rose-500',
};

export function useProjectTimeline(props) {
    const project = computed(() => toValue(props.project));
    const tasks = computed(() => toValue(props.tasks) || []);
    const sprints = computed(() => toValue(props.sprints) || []);

    const GROUP_BY_KEY = 'va-qlda.timeline.groupBy';

    const viewMode = ref('week');
    const workspaceTab = ref('timeline');
    const zoom = ref(1);
    const groupBy = ref(
        typeof localStorage !== 'undefined'
        && ['sprint', 'phase', 'nested'].includes(localStorage.getItem(GROUP_BY_KEY))
            ? localStorage.getItem(GROUP_BY_KEY)
            : 'nested',
    );
    const collapsedGroups = reactive(new Set());

    const filter = ref({
        search: '',
        assignee: 'all',
        status: 'all',
        priority: 'all',
        sprint: 'all',
        department: 'all',
        dateRange: 'all',
        quick: 'all',
    });

    const startOfToday = () => {
        const d = new Date();
        d.setHours(0, 0, 0, 0);
        return d;
    };

    const parseDate = (x) => (x ? new Date(`${x}T00:00:00`) : null);
    const stripDiacritics = (x) =>
        String(x || '')
            .normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .toLowerCase();

    const getAssignees = (t) => (t.assignees?.length ? t.assignees : t.assignee ? [t.assignee] : []);
    const isDone = (t) => t.status?.value === 'done';
    const isBlocked = (t) => ['blocked', 'on_hold'].includes(t.status?.value);
    const isMilestone = (t) =>
        !!(t.is_milestone || t.type?.value === 'milestone' || /milestone|approved|go live|uat complete|sprint \d+ complete/i.test(t.title || ''));

    const taskById = computed(() => {
        const map = new Map();
        tasks.value.forEach((t) => map.set(t.id, t));
        return map;
    });

    const isOverdue = (t) => isTaskOverdue(t);

    const resolveSchedule = (task) => {
        if (!task || isMilestone(task)) return null;
        let start = task.start_date;
        let due = task.due_date;
        if (task.parent_id && (!start || !due)) {
            const parent = taskById.value.get(task.parent_id);
            start = start || parent?.start_date;
            due = due || parent?.due_date;
        }
        if (!start || !due) return null;
        return { start_date: start, due_date: due };
    };

    const pushTaskRows = (rows, roots, groupKey) => {
        buildTaskDisplayRows(roots, tasks.value, { includeSubtasks: true }).forEach(({ task, depth, parent }) => {
            rows.push({ type: 'task', task, groupKey, depth, parent });
        });
    };

    const uniqueAssignees = computed(() => {
        const map = new Map();
        tasks.value.forEach((t) => {
            getAssignees(t).forEach((a) => {
                if (a?.id && !map.has(a.id)) map.set(a.id, a);
            });
        });
        return [...map.values()];
    });

    const filteredTasks = computed(() => {
        const f = filter.value;
        const today = startOfToday();
        const endWeek = new Date(today);
        endWeek.setDate(endWeek.getDate() + (7 - (endWeek.getDay() || 7)));
        const endMonth = new Date(today.getFullYear(), today.getMonth() + 1, 0);

        return tasks.value.filter((t) => {
            const hay = stripDiacritics(
                `${t.title} ${t.description || ''} ${getAssignees(t)
                    .map((a) => a.name)
                    .join(' ')}`,
            );
            if (f.search && !hay.includes(stripDiacritics(f.search))) return false;
            if (f.assignee !== 'all' && !getAssignees(t).some((a) => String(a.id) === f.assignee)) return false;
            if (f.status !== 'all' && t.status?.value !== f.status) return false;
            if (f.priority !== 'all' && t.priority?.value !== f.priority) return false;
            if (f.sprint !== 'all' && String(t.sprint_id || '') !== f.sprint) return false;
            if (f.department !== 'all' && String(t.department_id || t.department?.id || '') !== f.department) return false;

            const due = parseDate(t.due_date);
            if (f.dateRange === 'today' && !(due && due.getTime() === today.getTime())) return false;
            if (f.dateRange === 'week' && !(due && due >= today && due <= endWeek)) return false;
            if (f.dateRange === 'month' && !(due && due >= today && due <= endMonth)) return false;
            if (f.quick === 'overdue' && !isOverdue(t)) return false;
            if (f.quick === 'blocked' && !isBlocked(t)) return false;
            return true;
        });
    });

    const scheduledTasks = computed(() =>
        filteredTasks.value.filter((t) => resolveSchedule(t) !== null),
    );

    const milestones = computed(() => filteredTasks.value.filter(isMilestone));

    const kpis = computed(() => {
        const list = tasks.value;
        return {
            total: list.length,
            completed: list.filter(isDone).length,
            inProgress: list.filter((t) => t.status?.value === 'in_progress').length,
            overdue: list.filter(isOverdue).length,
            milestones: list.filter(isMilestone).length,
        };
    });

    const nearestDeadline = computed(() => {
        const open = tasks.value.filter((t) => !isDone(t) && t.due_date);
        if (!open.length) return null;
        open.sort((a, b) => new Date(a.due_date) - new Date(b.due_date));
        return open[0].due_date;
    });

    const projectHealth = computed(() => {
        if (kpis.value.overdue >= 8) return { label: 'Critical', emoji: '🔴', tone: 'rose' };
        if (kpis.value.overdue >= 3) return { label: 'At Risk', emoji: '🟡', tone: 'amber' };
        return { label: 'Healthy', emoji: '🟢', tone: 'emerald' };
    });

    const sprintById = computed(() => {
        const map = new Map();
        sprints.value.forEach((s) => map.set(s.id, s));
        return map;
    });

    const tasksForSprintGroup = (sprintId) => {
        const list = filteredTasks.value.filter((t) => !isMilestone(t) && !t.parent_id && t.sprint_id === sprintId);
        return list.sort((a, b) => {
            const sa = parseDate(a.start_date)?.getTime() ?? Number.MAX_SAFE_INTEGER;
            const sb = parseDate(b.start_date)?.getTime() ?? Number.MAX_SAFE_INTEGER;
            return sa - sb || (a.order_column || 0) - (b.order_column || 0);
        });
    };

    /** Nhóm task theo sprint (giống tab Sprint). */
    const sprintGroups = computed(() => {
        const groups = [];
        const f = filter.value;

        sortSprints(sprints.value).forEach((s) => {
            if (f.sprint !== 'all' && String(s.id) !== f.sprint) return;
            const sprintTasks = tasksForSprintGroup(s.id);
            if (!sprintTasks.length) return;
            groups.push({
                key: `sprint-${s.id}`,
                sprint: s,
                label: s.name,
                meta: [s.start_date, s.end_date].filter(Boolean).length === 2
                    ? `${formatShortDate(s.start_date)} → ${formatShortDate(s.end_date)}`
                    : '',
                status: s.status,
                count: countTaskTree(sprintTasks, tasks.value),
                tasks: sprintTasks,
            });
        });

        if (f.sprint === 'all') {
            const backlog = filteredTasks.value
                .filter((t) => !isMilestone(t) && !t.parent_id && !t.sprint_id)
                .sort((a, b) => (a.order_column || 0) - (b.order_column || 0));
            if (backlog.length) {
                groups.push({
                    key: 'backlog',
                    sprint: null,
                    label: 'Backlog',
                    meta: 'Chưa gán sprint',
                    status: null,
                    count: countTaskTree(backlog, tasks.value),
                    tasks: backlog,
                });
            }
        }

        return groups;
    });

    const toggleGroup = (key) => {
        if (collapsedGroups.has(key)) collapsedGroups.delete(key);
        else collapsedGroups.add(key);
    };

    const isGroupOpen = (key) => !collapsedGroups.has(key);

    const togglePhase = toggleGroup;
    const isPhaseOpen = isGroupOpen;

    const range = computed(() => {
        const dates = [];
        if (project.value?.start_date) dates.push(parseDate(project.value.start_date));
        if (project.value?.due_date) dates.push(parseDate(project.value.due_date));
        scheduledTasks.value.forEach((t) => {
            const sched = resolveSchedule(t);
            if (sched) {
                dates.push(parseDate(sched.start_date), parseDate(sched.due_date));
            }
        });
        milestones.value.forEach((m) => dates.push(parseDate(m.due_date || m.start_date)));

        const valid = dates.filter(Boolean);
        const today = startOfToday();
        let start = valid.length ? new Date(Math.min(...valid.map((d) => d.getTime()))) : new Date(today);
        let end = valid.length ? new Date(Math.max(...valid.map((d) => d.getTime()))) : new Date(today);

        const pad = viewMode.value === 'day' ? 2 : viewMode.value === 'week' ? 14 : viewMode.value === 'quarter' ? 120 : 45;
        start.setDate(start.getDate() - pad);
        end.setDate(end.getDate() + pad);

        return { start, end, span: Math.max(end.getTime() - start.getTime(), 86400000) };
    });

    const columnWidth = computed(() => {
        const base = { day: 48, week: 72, month: 96, quarter: 120 }[viewMode.value] || 72;
        return Math.round(base * zoom.value);
    });

    const columns = computed(() => {
        const { start, end } = range.value;
        const cols = [];
        const cursor = new Date(start);

        while (cursor <= end && cols.length < 120) {
            const label =
                viewMode.value === 'day'
                    ? cursor.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' })
                    : viewMode.value === 'week'
                      ? `T${getWeekNumber(cursor)}`
                      : viewMode.value === 'quarter'
                        ? `Q${Math.floor(cursor.getMonth() / 3) + 1} ${cursor.getFullYear()}`
                        : cursor.toLocaleDateString('vi-VN', { month: 'short', year: '2-digit' });

            cols.push({ key: cursor.toISOString().slice(0, 10), label, date: new Date(cursor) });

            if (viewMode.value === 'day') cursor.setDate(cursor.getDate() + 1);
            else if (viewMode.value === 'week') cursor.setDate(cursor.getDate() + 7);
            else if (viewMode.value === 'quarter') cursor.setMonth(cursor.getMonth() + 3);
            else cursor.setMonth(cursor.getMonth() + 1);
        }
        return cols;
    });

    const timelineWidth = computed(() => columns.value.length * columnWidth.value);

    const todayPercent = computed(() => {
        const today = startOfToday().getTime();
        const { start, span } = range.value;
        const pct = ((today - start.getTime()) / span) * 100;
        return Math.min(100, Math.max(0, pct));
    });

    const barStyle = (task) => {
        const sched = resolveSchedule(task);
        if (!sched) return null;
        const s = parseDate(sched.start_date);
        const e = parseDate(sched.due_date);
        if (!s || !e) return null;
        const { start, span } = range.value;
        const left = ((s.getTime() - start.getTime()) / span) * 100;
        const width = ((e.getTime() - s.getTime()) / span) * 100;
        if (left > 100 || left + width < 0) return null;
        return {
            left: `${Math.max(0, left)}%`,
            width: `${Math.max(1.5, Math.min(100 - Math.max(0, left), width))}%`,
        };
    };

    const milestonePercent = (m) => {
        const d = parseDate(m.due_date || m.start_date);
        if (!d) return null;
        const { start, span } = range.value;
        return ((d.getTime() - start.getTime()) / span) * 100;
    };

    const resourceRows = computed(() => {
        const map = new Map();
        filteredTasks.value.forEach((t) => {
            getAssignees(t).forEach((a) => {
                if (!map.has(a.id)) map.set(a.id, { ...a, load: 0 });
                map.get(a.id).load += Number(t.estimate_hours || (t.status?.value === 'in_progress' ? 6 : 2));
            });
        });
        const cap = 40;
        return [...map.values()]
            .map((r) => ({
                ...r,
                pct: Math.min(100, Math.round((r.load / cap) * 100)),
                overloaded: r.load > cap * 0.85,
            }))
            .sort((a, b) => b.pct - a.pct);
    });

    const phaseOnlyGroups = computed(() => {
        const list = filteredTasks.value.filter((t) => !isMilestone(t) && !t.parent_id);
        return groupTasksByPhase(list);
    });

    const hasTimelineData = computed(() => {
        if (groupBy.value === 'phase') {
            return phaseOnlyGroups.value.some((g) => g.tasks.length > 0) || milestones.value.length > 0;
        }
        return sprintGroups.value.some((g) => g.tasks.length > 0) || milestones.value.length > 0;
    });

    const sprintSpanStyle = (sprint) => {
        if (!sprint?.start_date || !sprint?.end_date) return null;
        return barStyle({ start_date: sprint.start_date, due_date: sprint.end_date });
    };

    const appendPhaseBlocks = (rows, phaseTasks, parentKey) => {
        groupTasksByPhase(phaseTasks).forEach((ph) => {
            const phaseKey = `${parentKey}:phase-${ph.key}`;
            const treeRows = buildTaskDisplayRows(ph.tasks, tasks.value, { includeSubtasks: true });
            rows.push({
                type: 'phase',
                key: phaseKey,
                label: ph.label,
                meta: 'Giai đoạn SDLC',
                count: treeRows.length,
                indent: 1,
            });
            if (!isGroupOpen(phaseKey)) return;
            treeRows.forEach(({ task, depth, parent }) => rows.push({
                type: 'task',
                task,
                groupKey: phaseKey,
                depth,
                parent,
            }));
        });
    };

    /** Flat rows for virtual scroll + synced panels */
    const flatRows = computed(() => {
        const rows = [];

        if (groupBy.value === 'phase') {
            phaseOnlyGroups.value.forEach((ph) => {
                const key = `phase-top-${ph.key}`;
                rows.push({
                    type: 'phase',
                    key,
                    label: ph.label,
                    meta: 'Giai đoạn SDLC',
                    count: countTaskTree(ph.tasks, tasks.value),
                    indent: 0,
                });
                if (!isGroupOpen(key)) return;
                pushTaskRows(rows, ph.tasks, key);
            });
        } else {
            sprintGroups.value.forEach((group) => {
                rows.push({
                    type: 'sprint',
                    key: group.key,
                    label: group.label,
                    meta: group.meta,
                    status: group.status,
                    count: group.count,
                    sprint: group.sprint,
                });
                if (!isGroupOpen(group.key)) return;
                if (groupBy.value === 'nested') {
                    appendPhaseBlocks(rows, group.tasks, group.key);
                } else {
                    pushTaskRows(rows, group.tasks, group.key);
                }
            });
        }

        if (milestones.value.length) {
            rows.push({ type: 'milestone-header' });
            milestones.value.forEach((m) => rows.push({ type: 'milestone', milestone: m }));
        }
        return rows;
    });

    const dependencyLinks = computed(() => {
        const links = [];
        scheduledTasks.value.forEach((task) => {
            const style = barStyle(task);
            if (!style) return;
            (task.dependencies || []).forEach((dep) => {
                const depId = typeof dep === 'object' && dep !== null ? dep.id : dep;
                const pred = taskById.value.get(depId);
                if (!pred) return;
                const predStyle = barStyle(pred);
                if (!predStyle) return;
                links.push({
                    id: `${depId}-${task.id}`,
                    fromId: depId,
                    toId: task.id,
                    fromLeft: parseFloat(predStyle.left),
                    fromWidth: parseFloat(predStyle.width),
                    toLeft: parseFloat(style.left),
                    blocked: isBlocked(task),
                });
            });
        });
        return links;
    });

    const burndownSeries = computed(() => {
        const days = 14;
        const today = startOfToday();
        const scope = tasks.value.filter((t) => !isMilestone(t));
        const total = Math.max(scope.length, 1);
        const points = [];

        for (let i = days - 1; i >= 0; i--) {
            const d = new Date(today);
            d.setDate(d.getDate() - i);
            const doneCount = scope.filter((t) => {
                if (!isDone(t)) return false;
                const due = parseDate(t.due_date);
                return due && due <= d;
            }).length;
            const remaining = total - doneCount;
            const ideal = Math.round(total - (total / (days - 1)) * (days - 1 - i));
            points.push({
                label: d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }),
                remaining,
                ideal: Math.max(0, ideal),
            });
        }
        return points;
    });

    const sprintVelocity = computed(() => {
        return sprints.value.map((s) => {
            const sprintTasks = tasks.value.filter((t) => t.sprint_id === s.id);
            const done = sprintTasks.filter(isDone).length;
            const total = Math.max(sprintTasks.length, 1);
            return {
                id: s.id,
                name: s.name,
                pct: Math.round((done / total) * 100),
                done,
                total: sprintTasks.length,
            };
        }).filter((s) => s.total > 0);
    });

    const resetFilter = () => {
        filter.value = {
            search: '',
            assignee: 'all',
            status: 'all',
            priority: 'all',
            sprint: 'all',
            department: 'all',
            dateRange: 'all',
            quick: 'all',
        };
    };

    const setViewMode = (mode) => {
        viewMode.value = mode;
    };

    const setGroupBy = (mode) => {
        if (!['sprint', 'phase', 'nested'].includes(mode)) return;
        groupBy.value = mode;
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(GROUP_BY_KEY, mode);
        }
    };

    const zoomIn = () => {
        zoom.value = Math.min(1.75, Math.round((zoom.value + 0.25) * 100) / 100);
    };

    const zoomOut = () => {
        zoom.value = Math.max(0.5, Math.round((zoom.value - 0.25) * 100) / 100);
    };

    return {
        viewMode,
        workspaceTab,
        zoom,
        groupBy,
        setGroupBy,
        filter,
        filteredTasks,
        scheduledTasks,
        milestones,
        kpis,
        nearestDeadline,
        projectHealth,
        sprintGroups,
        sprintById,
        collapsedGroups,
        toggleGroup,
        isGroupOpen,
        togglePhase,
        isPhaseOpen,
        sprintSpanStyle,
        range,
        columns,
        columnWidth,
        timelineWidth,
        todayPercent,
        barStyle,
        milestonePercent,
        resourceRows,
        hasTimelineData,
        flatRows,
        dependencyLinks,
        burndownSeries,
        sprintVelocity,
        taskById,
        uniqueAssignees,
        resetFilter,
        setViewMode,
        zoomIn,
        zoomOut,
        getAssignees,
        isOverdue,
        isBlocked,
        isMilestone,
        TIMELINE_STATUS_BAR,
    };
}

function formatShortDate(iso) {
    if (!iso) return '';
    const d = new Date(`${iso}T00:00:00`);
    if (Number.isNaN(d.getTime())) return '';
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
}

function getWeekNumber(d) {
    const date = new Date(Date.UTC(d.getFullYear(), d.getMonth(), d.getDate()));
    const dayNum = date.getUTCDay() || 7;
    date.setUTCDate(date.getUTCDate() + 4 - dayNum);
    const yearStart = new Date(Date.UTC(date.getUTCFullYear(), 0, 1));
    return Math.ceil(((date - yearStart) / 86400000 + 1) / 7);
}
