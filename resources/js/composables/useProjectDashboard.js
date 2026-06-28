import { computed, ref, watch } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { isTaskOverdue } from '@/composables/useTaskTimeliness';

/** @typedef {'low'|'medium'|'high'|'critical'} IssueSeverity */
/** @typedef {'open'|'in_progress'|'resolved'} IssueStatus */
/** @typedef {'pm'|'team'|'bgh'} DashboardView */
/** @typedef {'task_status_changed'|'task_created'|'member_added'|'sprint_started'|'issue_opened'} ActivityType */

const VIEW_KEY = (id) => `dashboard_view_${id}`;
const BANNER_KEY = (id) => `dashboard_deadline_banner_${id}`;

export const SEVERITY_BADGE = {
    critical: 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
    high: 'bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300',
    medium: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    low: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
};

export const GANTT_BAR = {
    todo: 'bg-slate-300 dark:bg-slate-600',
    in_progress: 'bg-sky-400 dark:bg-sky-500',
    in_review: 'bg-violet-400 dark:bg-violet-500',
    done: 'bg-emerald-400 dark:bg-emerald-500',
    blocked: 'bg-rose-400 dark:bg-rose-500',
};

export const ACTIVITY_DOT = {
    task_status_changed: 'bg-sky-400',
    task_created: 'bg-emerald-400',
    task_updated: 'bg-sky-300',
    task_deleted: 'bg-slate-400',
    member_added: 'bg-violet-400',
    member: 'bg-violet-400',
    sprint_started: 'bg-amber-400',
    issue_opened: 'bg-rose-400',
    issue_updated: 'bg-rose-300',
    issue_closed: 'bg-slate-400',
    issue_imported: 'bg-amber-500',
    project_created: 'bg-brand',
    project_updated: 'bg-indigo-400',
    document: 'bg-teal-400',
    comment: 'bg-slate-500',
    worklog: 'bg-cyan-400',
};

export function getTaskAssigneeIds(task) {
    if (task.assignees?.length) return task.assignees.map((a) => a.id);
    if (task.assignee?.id) return [task.assignee.id];
    return [];
}

/** @param {object} blocker */
export function blockerToIssue(blocker) {
    return {
        id: blocker.id,
        title: blocker.title,
        severity: blocker.severity?.value ?? 'medium',
        status: blocker.status?.value ?? 'open',
        assigneeId: blocker.owner?.id ?? null,
        dueDate: null,
        createdAt: blocker.raised_at ?? new Date().toISOString(),
    };
}

export function relativeTime(iso) {
    if (!iso) return '—';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.floor(diff / 60000);
    if (mins < 1) return 'vừa xong';
    if (mins < 60) return `${mins} phút trước`;
    const hrs = Math.floor(mins / 60);
    if (hrs < 24) return `${hrs} giờ trước`;
    const days = Math.floor(hrs / 24);
    if (days === 1) return 'hôm qua';
    if (days < 7) return `${days} ngày trước`;
    return new Date(iso).toLocaleDateString('vi-VN');
}

export function taskDeadlineClass(task) {
    if (isTaskOverdue(task)) return 'border-l-2 border-rose-400';
    if (!task.due_date || task.status?.value === 'done') return '';
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    const days = Math.ceil((new Date(`${task.due_date}T00:00:00`) - today) / 86400000);
    if (days <= 3) return 'border-l-2 border-amber-400';
    return '';
}

export function useProjectDashboard(projectId, sources) {
    const page = usePage();
    const currentEmployeeId = computed(() => page.props.auth?.user?.employee_id ?? null);

    const viewMode = ref(
        (typeof localStorage !== 'undefined' && localStorage.getItem(VIEW_KEY(projectId))) || 'pm',
    );
    watch(viewMode, (v) => localStorage.setItem(VIEW_KEY(projectId), v));

    const bannerDismissed = ref(
        typeof sessionStorage !== 'undefined' && sessionStorage.getItem(BANNER_KEY(projectId)) === '1',
    );
    const dismissBanner = () => {
        bannerDismissed.value = true;
        sessionStorage.setItem(BANNER_KEY(projectId), '1');
    };

    // Feed hoạt động: chỉ lấy dữ liệu thật từ server (ProjectActivityFeedBuilder).
    // Không seed placeholder, không cache localStorage — tránh hiện dữ liệu giả khi rỗng.
    const loadActivity = () => {
        const fromServer = sources.activityFeed?.value ?? sources.activityFeed;
        return Array.isArray(fromServer)
            ? fromServer.filter((ev) => ev && ev.id != null)
            : [];
    };
    const activityLog = ref(loadActivity());

    watch(
        () => sources.activityFeed?.value ?? sources.activityFeed,
        () => {
            activityLog.value = loadActivity();
        },
        { deep: true },
    );

    // Cập nhật lạc quan trong phiên sau hành động của user; tải lại sẽ lấy bản chính thức từ server.
    const pushActivity = (type, message, meta = {}) => {
        activityLog.value = [
            { id: `${Date.now()}-${Math.random().toString(36).slice(2, 7)}`, type, message, at: new Date().toISOString(), ...meta },
            ...activityLog.value,
        ].slice(0, 50);
    };

    const issues = computed(() => (sources.blockers?.value ?? []).map(blockerToIssue));
    const openIssueCount = computed(() => issues.value.filter((i) => !['resolved', 'closed'].includes(i.status)).length);

    const daysLeft = computed(() => {
        const p = sources.project?.value;
        const end = p?.due_date || p?.end_date;
        if (!end) return null;
        return Math.ceil((new Date(`${end}T00:00:00`) - new Date().setHours(0, 0, 0, 0)) / 86400000);
    });

    const deadlineBanner = computed(() => {
        if (bannerDismissed.value || daysLeft.value === null) return null;
        if (daysLeft.value <= 0) {
            return { tone: 'danger', text: `Dự án đã quá hạn ${Math.abs(daysLeft.value)} ngày` };
        }
        if (daysLeft.value <= 7) {
            return { tone: 'warning', text: `Còn ${daysLeft.value} ngày đến deadline` };
        }
        return null;
    });

    const deadlineHint = computed(() => {
        if (daysLeft.value === null || daysLeft.value > 14 || daysLeft.value <= 7) return null;
        return `Còn ${daysLeft.value} ngày đến hạn`;
    });

    const showExport = computed(() => viewMode.value === 'pm' || viewMode.value === 'bgh');
    const showRiskPanel = computed(() => viewMode.value === 'pm' || viewMode.value === 'bgh');
    const showWorkload = computed(() => viewMode.value === 'pm');
    const showActivity = computed(() => viewMode.value === 'pm');
    const showProjectDetail = computed(() => viewMode.value === 'pm' || viewMode.value === 'team');
    const showGantt = computed(() => viewMode.value !== 'team');
    const showStatusChart = computed(() => true);
    const showMyTasks = computed(() => viewMode.value === 'team');

    const filteredTasks = computed(() => {
        const all = sources.tasks?.value ?? [];
        if (viewMode.value !== 'team' || !currentEmployeeId.value) return all;
        return all.filter((t) => getTaskAssigneeIds(t).includes(currentEmployeeId.value));
    });

    const memberWorkload = computed(() => {
        const members = sources.members?.value ?? [];
        const tasks = sources.tasks?.value ?? [];
        return members.map((m) => {
            const assigned = tasks.filter((t) => getTaskAssigneeIds(t).includes(m.id));
            const activeTasks = assigned.filter((t) => t.status?.value !== 'done');
            const totalHours = activeTasks.reduce((s, t) => s + Number(t.estimate_hours || 0), 0);
            const done = assigned.filter((t) => t.status?.value === 'done').length;
            const personalProgress = assigned.length ? Math.round((done / assigned.length) * 100) : 0;
            const overloaded = activeTasks.length > 3 || totalHours > 40;
            let progressColor = 'bg-amber-500';
            if (personalProgress > 80) progressColor = 'bg-emerald-500';
            else if (personalProgress >= 40) progressColor = 'bg-sky-500';
            return { member: m, activeTasks: activeTasks.length, totalHours, personalProgress, progressColor, overloaded };
        });
    });

    return {
        viewMode,
        currentEmployeeId,
        activityLog,
        pushActivity,
        issues,
        openIssueCount,
        daysLeft,
        deadlineBanner,
        deadlineHint,
        bannerDismissed,
        dismissBanner,
        showExport,
        showRiskPanel,
        showWorkload,
        showActivity,
        showProjectDetail,
        showGantt,
        showStatusChart,
        showMyTasks,
        filteredTasks,
        memberWorkload,
    };
}
