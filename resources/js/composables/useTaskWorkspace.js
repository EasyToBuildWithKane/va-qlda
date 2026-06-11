import { computed, ref, unref, isRef } from 'vue';
import { getAssignees } from '@/composables/useSprintFilters';
import { getSubtaskStats } from '@/composables/useTaskHierarchy';
import { isTaskOverdue } from '@/composables/useTaskTimeliness';

const PANEL_WIDTH_KEY = 'va-qlda.taskPanel.width';
const MIN_WIDTH = 400;
const MAX_WIDTH = 920;
const DEFAULT_WIDTH = 520;

export function taskDisplayId(task) {
    if (!task?.id) return '';
    const y = task.created_at ? new Date(task.created_at).getFullYear() : new Date().getFullYear();
    return `TASK-${y}-${String(task.id).padStart(3, '0')}`;
}

export function depId(dep) {
    return typeof dep === 'object' && dep !== null ? dep.id : dep;
}

/**
 * @param {import('vue').Ref|import('vue').ComputedRef} taskSource
 * @param {object} ctx
 */
export function useTaskWorkspace(taskSource, ctx = {}) {
    const project = computed(() => unref(ctx.project) ?? null);
    const sprints = computed(() => unref(ctx.sprints) ?? []);
    const blockers = computed(() => unref(ctx.blockers) ?? []);
    const attachments = computed(() => unref(ctx.attachments) ?? []);
    const allTasks = computed(() => unref(ctx.allTasks) ?? []);
    const epics = computed(() => unref(ctx.epics) ?? []);
    const currentEmployeeId = computed(() => unref(ctx.currentEmployeeId) ?? null);

    const task = computed(() => {
        let raw = unref(taskSource);
        if (isRef(raw)) raw = raw.value;
        return raw && typeof raw === 'object' && raw.id != null ? raw : null;
    });

    const assignees = computed(() => getAssignees(task.value || {}));

    const sprintLine = computed(() => {
        const t = task.value;
        if (!t) return '';
        const sprint = t.sprint?.name
            ?? sprints.value.find((s) => s.id === t.sprint_id)?.name
            ?? (t.sprint_id ? `Sprint #${t.sprint_id}` : 'Backlog');
        return sprint;
    });

    const epicLabel = computed(() => task.value?.epic?.name ?? null);

    const headerContext = computed(() => {
        const parts = [sprintLine.value];
        if (epicLabel.value) parts.push(epicLabel.value);
        return parts.join(' • ');
    });

    const isWatching = computed(() => {
        const id = currentEmployeeId.value;
        if (!id || !task.value?.watchers) return false;
        return task.value.watchers.some((w) => w.id === id);
    });

    const subtaskStats = computed(() => {
        const t = task.value;
        if (!t?.id || t.parent_id) return null;
        return getSubtaskStats(t, allTasks.value);
    });

    const estimateHours = computed(() => {
        const v = task.value?.estimate_hours;
        if (v != null && v !== '') return Number(v);
        const fromChildren = subtaskStats.value?.hours;
        return fromChildren > 0 ? fromChildren : null;
    });

    const estimateFromSubtasksOnly = computed(() => {
        const v = task.value?.estimate_hours;
        return (v == null || v === '') && (subtaskStats.value?.hours > 0);
    });

    const loggedHours = computed(() => {
        const v = task.value?.logged_hours;
        return v != null ? Number(v) : 0;
    });

    const remainingHours = computed(() => {
        if (estimateHours.value == null) return null;
        return Math.max(0, estimateHours.value - loggedHours.value);
    });

    const progressPct = computed(() => {
        const n = Number(task.value?.progress);
        if (!Number.isFinite(n)) return 0;
        return Math.min(100, Math.max(0, Math.round(n)));
    });

    const worklogBurnPct = computed(() => {
        if (!estimateHours.value || estimateHours.value <= 0) return null;
        return Math.min(100, Math.round((loggedHours.value / estimateHours.value) * 100));
    });

    const taskBlockers = computed(() =>
        blockers.value.filter(
            (b) => b?.id != null && b.task_id === task.value?.id && !['resolved', 'closed'].includes(b.status?.value),
        ),
    );

    const resolveRelatedTask = (d) => {
        if (d == null) return null;
        if (typeof d === 'object') return d.id != null ? d : null;
        return allTasks.value.find((x) => x.id === d) ?? null;
    };

    const relatedBlockedBy = computed(() => {
        const t = task.value;
        if (!t?.dependencies?.length) return [];
        return (t.dependencies || []).map(resolveRelatedTask).filter((r) => r?.id != null);
    });

    const relatedBlocking = computed(() => {
        const t = task.value;
        if (!t?.dependents?.length) return [];
        return (t.dependents || []).map(resolveRelatedTask).filter((r) => r?.id != null);
    });

    const projectDocs = computed(() => {
        const groups = [
            { key: 'ba', label: 'BRD / SRS / FRS', hint: 'Tài liệu BA', categories: ['ba'] },
            { key: 'uiux', label: 'UI / UX Design', hint: 'Wireframe & mockup', categories: ['uiux'] },
            { key: 'customer', label: 'Tài liệu khách hàng', hint: 'Brief & xác nhận', categories: ['customer'] },
            { key: 'customer_data', label: 'Data & import', hint: 'Excel / CSV', categories: ['customer_data'] },
            { key: 'images', label: 'Hình ảnh & media', hint: 'Screenshot', categories: ['images'] },
        ];
        return groups
            .map((g) => ({
                ...g,
                files: attachments.value.filter((a) => a?.id != null && g.categories.includes(a.category)),
            }))
            .filter((g) => g.files.length > 0);
    });

    const ACTIVITY_UI = {
        created: { icon: 'add', tone: 'brand' },
        updated: { icon: 'edit', tone: 'slate' },
        status_changed: { icon: 'task', tone: 'sky' },
        comment: { icon: 'comment', tone: 'violet' },
        attachment: { icon: 'documents', tone: 'amber' },
        worklog: { icon: 'timer', tone: 'sky' },
        watcher: { icon: 'eye', tone: 'slate' },
        subtask: { icon: 'add', tone: 'emerald' },
        blocked: { icon: 'blockers', tone: 'rose' },
    };

    const activityTimeline = computed(() => {
        const t = task.value;
        if (!t) return [];

        if (t.activities?.length) {
            return [...t.activities]
                .filter((a) => a?.id != null)
                .map((a) => {
                    const ui = ACTIVITY_UI[a.event] || { icon: 'info', tone: 'slate' };
                    return {
                        id: `act-${a.id}`,
                        type: a.event,
                        at: a.created_at,
                        icon: ui.icon,
                        tone: ui.tone,
                        title: a.description,
                        detail: a.employee?.name ? `Bởi ${a.employee.name}` : '',
                        meta: a.meta,
                    };
                })
                .filter((ev) => ev.id)
                .sort((a, b) => new Date(b.at) - new Date(a.at));
        }

        const items = [];
        if (t.created_at) {
            items.push({
                id: `created-${t.id}`,
                type: 'created',
                at: t.created_at,
                icon: 'add',
                tone: 'brand',
                title: 'Tạo công việc',
                detail: t.reporter?.name ? `Bởi ${t.reporter.name}` : '',
            });
        }
        (t.worklogs || []).filter((w) => w?.id != null).forEach((w) => {
            items.push({
                id: `wl-${w.id}`,
                type: 'worklog',
                at: w.date ? `${w.date}T12:00:00` : t.updated_at,
                icon: 'timer',
                tone: 'sky',
                title: 'Ghi nhận thời gian',
                detail: `${w.hours}h${w.employee?.name ? ` · ${w.employee.name}` : ''}`,
            });
        });
        return items.sort((a, b) => new Date(b.at) - new Date(a.at));
    });

    const kpiCards = computed(() => {
        const t = task.value;
        if (!t) return [];
        return [
            { key: 'status', label: 'Trạng thái', icon: 'status', value: t.status?.label, color: t.status?.color },
            { key: 'priority', label: 'Ưu tiên', icon: 'flag', value: t.priority?.label, color: t.priority?.color },
            { key: 'progress', label: 'Tiến độ', icon: 'progress', value: `${progressPct.value}%`, color: progressPct.value >= 100 ? 'emerald' : 'sky' },
            {
                key: 'estimate',
                label: estimateFromSubtasksOnly.value ? 'Ước tính (con)' : 'Ước tính',
                icon: 'timer',
                value: estimateHours.value != null ? `${estimateHours.value}h` : '—',
                color: 'slate',
            },
            { key: 'logged', label: 'Đã log', icon: 'clock', value: `${loggedHours.value}h`, color: loggedHours.value > (estimateHours.value || Infinity) ? 'rose' : 'emerald' },
            { key: 'start', label: 'Bắt đầu', icon: 'calendar', value: t.start_date ? formatShortDate(t.start_date) : '—', color: 'slate' },
            { key: 'due', label: 'Deadline', icon: 'calendar-check', value: t.due_date ? formatShortDate(t.due_date) : '—', color: isTaskOverdue(t) ? 'rose' : 'slate' },
            { key: 'phase', label: 'Giai đoạn', icon: 'template', value: t.phase?.label || '—', color: 'violet' },
            { key: 'points', label: 'Story pts', icon: 'flag', value: t.story_points != null ? String(t.story_points) : '—', color: 'amber' },
        ];
    });

    return {
        task,
        assignees,
        sprintLine,
        epicLabel,
        headerContext,
        isWatching,
        epics,
        estimateHours,
        estimateFromSubtasksOnly,
        subtaskStats,
        loggedHours,
        remainingHours,
        progressPct,
        worklogBurnPct,
        taskBlockers,
        relatedBlockedBy,
        relatedBlocking,
        projectDocs,
        activityTimeline,
        kpiCards,
        projectName: computed(() => project.value?.name ?? ''),
        projectCode: computed(() => project.value?.code ?? ''),
        isOverdue: computed(() => isTaskOverdue(task.value)),
    };
}

function formatShortDate(iso) {
    const d = new Date(`${iso}T00:00:00`);
    if (Number.isNaN(d.getTime())) return iso;
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
}

export function useTaskPanelLayout() {
    const width = ref(
        typeof localStorage !== 'undefined'
            ? Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, Number(localStorage.getItem(PANEL_WIDTH_KEY)) || DEFAULT_WIDTH))
            : DEFAULT_WIDTH,
    );
    const fullscreen = ref(false);
    const resizing = ref(false);

    const persistWidth = () => {
        if (typeof localStorage !== 'undefined') {
            localStorage.setItem(PANEL_WIDTH_KEY, String(width.value));
        }
    };

    const startResize = (e) => {
        e.preventDefault();
        resizing.value = true;
        const startX = e.clientX;
        const startW = width.value;

        const onMove = (ev) => {
            const delta = startX - ev.clientX;
            width.value = Math.min(MAX_WIDTH, Math.max(MIN_WIDTH, startW + delta));
        };
        const onUp = () => {
            resizing.value = false;
            persistWidth();
            window.removeEventListener('mousemove', onMove);
            window.removeEventListener('mouseup', onUp);
        };
        window.addEventListener('mousemove', onMove);
        window.addEventListener('mouseup', onUp);
    };

    const toggleFullscreen = () => {
        fullscreen.value = !fullscreen.value;
    };

    const panelStyle = computed(() =>
        fullscreen.value
            ? { width: '100%', maxWidth: '100%' }
            : { width: `${width.value}px`, maxWidth: '100vw' },
    );

    return { width, fullscreen, resizing, startResize, toggleFullscreen, panelStyle };
}
