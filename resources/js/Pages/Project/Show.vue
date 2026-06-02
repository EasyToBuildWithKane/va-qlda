<script setup>
import { ref, computed, onMounted, toRef } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/Components/Project/Badge.vue';
import Avatar from '@/Components/Project/Avatar.vue';
import ProgressBar from '@/Components/Project/ProgressBar.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import ProjectTimelineCenter from '@/Components/Project/Timeline/ProjectTimelineCenter.vue';
import TaskBoard from '@/Components/Project/TaskBoard.vue';
import TaskDetailPanel from '@/Components/Project/Sprint/TaskDetailPanel.vue';
import TaskFormModal from '@/Components/Project/TaskFormModal.vue';
import SprintFormModal from '@/Components/Project/SprintFormModal.vue';
import BlockerFormModal from '@/Components/Project/BlockerFormModal.vue';
import DeadlineBanner from '@/Components/Project/Dashboard/DeadlineBanner.vue';
import DashboardViewToggle from '@/Components/Project/Dashboard/DashboardViewToggle.vue';
import GanttMini from '@/Components/Project/Dashboard/GanttMini.vue';
import RiskIssuePanel from '@/Components/Project/Dashboard/RiskIssuePanel.vue';
import RiskIssueDataTable from '@/Components/Project/Dashboard/RiskIssueDataTable.vue';
import ActivityFeed from '@/Components/Project/Dashboard/ActivityFeed.vue';
import ProjectOverviewCard from '@/Components/Project/Dashboard/ProjectOverviewCard.vue';
import ProjectDocumentsPanel from '@/Components/Project/Documents/ProjectDocumentsPanel.vue';
import SprintWorkspace from '@/Components/Project/Sprint/SprintWorkspace.vue';
import { date } from '@/composables/useFormat';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/composables/useToast';
import { useProjectDashboard } from '@/composables/useProjectDashboard';
import { useProjectExport } from '@/composables/useProjectExport';
import { normalizeEntities, normalizeKeyed, normalizeList } from '@/composables/useNormalizeList';

const props = defineProps({
    project: { type: Object, required: true },
    sprints: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    epics: { type: Array, default: () => [] },
    attachments: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({ employees: [], enums: {} }) },
});

const dialog = useDialog();
const toast = useToast();
const page = usePage();
const exporting = ref(false);
const enums = computed(() => props.options.enums || {});
const currentUserName = computed(() => page.props.auth?.user?.name?.split(' ').pop() || 'Thành viên');

const projectHeaderSubtitle = computed(() => {
    const parts = [props.project.code, props.project.status?.label].filter(Boolean);
    return parts.join(' · ') || 'Chi tiết dự án';
});

const projectIconColor = computed(() => {
    const c = props.project.color;
    return ['brand', 'sky', 'emerald', 'violet', 'amber', 'rose', 'cyan', 'slate'].includes(c) ? c : 'brand';
});
const canManage = computed(() => props.project.can?.manage);
const canContribute = computed(() => props.project.can?.contribute);
const projectMembers = computed(() => normalizeEntities(props.project?.members));

const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'overview' },
    { key: 'documents', label: 'Tài liệu', icon: 'documents' },
    { key: 'timeline', label: 'Tiến độ / Gantt', icon: 'timeline' },
    { key: 'board', label: 'Kanban', icon: 'board' },
    { key: 'sprints', label: 'Sprint', icon: 'sprint' },
    { key: 'blockers', label: 'Vướng mắc', icon: 'blockers' },
];
const tabList = normalizeKeyed(tabs);
const tab = ref('overview');

const documentCategories = computed(() =>
    normalizeList(props.options.enums?.projectAttachmentCategory).filter((c) => c?.value != null),
);
const attachmentCount = computed(() => props.attachments.length);

// --- Modal state ---
const taskModal = ref(false);
const editingTask = ref(null);
const detailTask = ref(null);
const taskDefaultStatus = ref('todo');
const sprintModal = ref(false);
const editingSprint = ref(null);
const blockerModal = ref(false);
const editingBlocker = ref(null);

const pid = props.project.id;

const openTaskDetail = (t) => {
    if (!t) return;
    const id = typeof t === 'object' ? t?.id : t;
    if (id == null) return;
    const fresh = props.tasks.find((x) => x?.id === id);
    detailTask.value = fresh ?? (typeof t === 'object' && t?.id != null ? t : null);
};
const openTaskModal = (t = null, status = 'todo') => { editingTask.value = t; taskDefaultStatus.value = status; taskModal.value = true; };
const openTaskEditFromDetail = (t) => {
    detailTask.value = null;
    openTaskModal(t);
};
const onTaskDetailUpdated = () => {
    if (!detailTask.value) return;
    const fresh = props.tasks.find((t) => t.id === detailTask.value.id);
    if (fresh) detailTask.value = fresh;
};
const openSprint = (s = null) => { editingSprint.value = s; sprintModal.value = true; };
const openBlocker = (b = null) => { editingBlocker.value = b; blockerModal.value = true; };

const ganttRevertPreviewId = ref(null);

const onGanttDate = ({ id, start, end }) => {
    ganttRevertPreviewId.value = null;
    router.put(`/projects/${pid}/tasks/${id}`, { start_date: start, due_date: end }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => toast.success('Đã cập nhật lịch công việc'),
        onError: () => {
            ganttRevertPreviewId.value = id;
            toast.error('Không lưu được ngày trên Gantt. Vui lòng thử lại.');
        },
    });
};
const onGanttProgress = ({ id, progress }) => router.patch(`/projects/${pid}/tasks/${id}`, { progress }, { preserveScroll: true });
const onBoardMove = ({ id, status }) => router.patch(`/projects/${pid}/tasks/${id}`, { status }, {
    preserveScroll: true,
    only: ['tasks'],
    onSuccess: () => onTaskDetailUpdated(),
});

const removeTask = async (t) => {
    if (await dialog.confirm({ title: 'Xoá công việc', message: `Xoá "${t.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/projects/${pid}/tasks/${t.id}`, { preserveScroll: true });
};
const removeSprint = async (s) => {
    if (await dialog.confirm({ title: 'Xoá sprint', message: `Xoá "${s.name}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/projects/${pid}/sprints/${s.id}`, { preserveScroll: true });
};
const removeBlocker = async (b) => {
    if (await dialog.confirm({ title: 'Xoá vướng mắc', message: `Xoá "${b.title}"?`, tone: 'danger', confirmText: 'Xoá' }))
        router.delete(`/blockers/${b.id}`, { preserveScroll: true });
};

// ---- Overview computed ----
const completedTasks = computed(() => props.tasks.filter((t) => t.status.value === 'done').length);
const openBlockerCount = computed(() => props.blockers.filter((b) => !['resolved', 'closed'].includes(b.status?.value)).length);

// ---- Sprint section ----
const expandedSprints = ref(new Set());
const toggleSprint = (id) => {
    const s = new Set(expandedSprints.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expandedSprints.value = s;
};
const isSprintOpen = (id) => expandedSprints.value.has(id);

const sprintTasks = computed(() => {
    const map = {};
    props.sprints.forEach((s) => { map[s.id] = []; });
    props.tasks.forEach((t) => { if (t.sprint_id && map[t.sprint_id]) map[t.sprint_id].push(t); });
    return map;
});

const backlogTasks = computed(() => props.tasks.filter((t) => !t.sprint_id));

const statusDot = {
    slate: 'bg-slate-400', sky: 'bg-sky-500', violet: 'bg-violet-500',
    emerald: 'bg-emerald-500', rose: 'bg-rose-500', amber: 'bg-amber-500',
};

const getAssignees = (t) => (t.assignees?.length ? t.assignees : (t.assignee ? [t.assignee] : []));

// ---- Dashboard (overview tab) ----
const riskPanelRef = ref(null);
const {
    viewMode,
    activityLog,
    activityVisible,
    showMoreActivity,
    pushActivity,
    seedActivityIfEmpty,
    openIssueCount,
    daysLeft: dashboardDaysLeft,
    deadlineBanner,
    dismissBanner,
    showExport,
    showRiskPanel,
    showActivity,
    showProjectDetail,
    showGantt,
} = useProjectDashboard(pid, {
    project: toRef(() => props.project),
    tasks: toRef(() => props.tasks),
    blockers: toRef(() => props.blockers),
    sprints: toRef(() => props.sprints),
    members: projectMembers,
});
const { exportReport } = useProjectExport();

onMounted(() => seedActivityIfEmpty());

const scrollToRiskPanel = () => riskPanelRef.value?.scrollHere();

const onExport = async () => {
    if (exporting.value) return;
    exporting.value = true;
    try {
        const filename = await exportReport({
            project: props.project,
            tasks: props.tasks,
            blockers: props.blockers,
            members: projectMembers.value,
        });
        toast.success(`Đã xuất báo cáo: ${filename}`);
    } catch (err) {
        console.error(err);
        toast.error('Không thể xuất báo cáo. Vui lòng thử lại.');
    } finally {
        exporting.value = false;
    }
};

const logTaskStatus = (task, status) => {
    const who = props.project.manager?.name?.split(' ').pop() || 'Thành viên';
    const label = enums.value.taskStatus?.find((s) => s.value === status)?.label ?? status;
    pushActivity('task_status_changed', `${who} chuyển [${task.title}] sang ${label}`);
};

const onBoardMoveWithLog = (payload) => {
    const task = props.tasks.find((t) => t.id === payload.id);
    if (task) logTaskStatus(task, payload.status);
    onBoardMove(payload);
};

const onRiskSaved = ({ type, title, count }) => {
    if (type === 'created') {
        pushActivity('issue_opened', `Mở vướng mắc mới: ${title}`);
    } else if (type === 'imported') {
        pushActivity('issue_imported', `Nhập ${count ?? 0} vướng mắc từ file Excel`);
    }
};

const onTaskSaved = () => {
    if (!editingTask.value) {
        pushActivity('task_created', `${currentUserName.value} tạo task mới`);
    }
    onTaskDetailUpdated();
};

const onSprintSaved = () => {
    if (!editingSprint.value) {
        const active = props.sprints.find((s) => s.status?.value === 'active');
        pushActivity('sprint_started', `${active?.name ?? 'Sprint mới'} bắt đầu`);
    }
};

</script>

<template>
    <Head :title="project.name" />
    <AppLayout :flush="true">
        <template #header>
            <PageHeader
                :title="project.name"
                :subtitle="projectHeaderSubtitle"
                icon="all-projects"
                :icon-color="projectIconColor"
                back-href="/projects"
            />
        </template>

        <!-- Full-height flex column -->
        <div class="flex h-full flex-col overflow-hidden bg-slate-50">
            <!-- ── Compact tab strip ── -->
            <nav class="flex shrink-0 items-center border-b border-slate-200 bg-white px-1">
                <button
                    v-for="t in tabList"
                    :key="t.key"
                    class="flex items-center gap-1.5 border-b-2 px-3 py-2.5 text-sm font-medium transition"
                    :class="tab === t.key
                        ? 'border-brand text-brand'
                        : 'border-transparent text-slate-500 hover:text-slate-700'"
                    @click="tab = t.key"
                >
                    <AppIcon :name="t.icon" :size="15" />
                    {{ t.label }}
                    <span
                        v-if="t.key === 'documents' && attachmentCount"
                        class="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-sky-100 px-1 text-[10px] font-bold text-sky-600"
                    >{{ attachmentCount }}</span>
                    <span
                        v-if="t.key === 'blockers' && openBlockerCount"
                        class="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-100 px-1 text-[10px] font-bold text-rose-600"
                    >{{ openBlockerCount }}</span>
                </button>
            </nav>

            <!-- ── Tab content: fills all remaining height ── -->
            <div class="min-h-0 flex-1 overflow-hidden">

                <!-- ===== DOCUMENTS ===== -->
                <div v-show="tab === 'documents'" class="h-full overflow-hidden dark:bg-slate-950">
                    <ProjectDocumentsPanel
                        :project-id="project.id"
                        :attachments="attachments"
                        :categories="documentCategories"
                        :can-upload="canContribute"
                        :can-edit="canContribute"
                        :can-delete="canManage"
                    />
                </div>

                <!-- ===== TIMELINE / GANTT ===== -->
                <div v-show="tab === 'timeline'" class="h-full">
                    <ProjectTimelineCenter
                        :project="project"
                        :tasks="tasks"
                        :sprints="sprints"
                        :blockers="blockers"
                        :enums="enums"
                        :can-manage="canManage"
                        :can-contribute="canContribute"
                        :revert-preview-task-id="ganttRevertPreviewId"
                        @create-task="openTaskModal()"
                        @select-task="(id) => openTaskDetail(tasks.find((t) => t.id == id))"
                        @date-change="onGanttDate"
                    />
                </div>

                <!-- ===== OVERVIEW (Dashboard) ===== -->
                <div v-show="tab === 'overview'" class="h-full overflow-x-hidden overflow-y-auto dark:bg-slate-950">
                    <div class="mx-auto min-w-0 max-w-[1400px] space-y-5 p-5">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <DashboardViewToggle v-model="viewMode" />
                            <button
                                v-if="showExport"
                                type="button"
                                class="btn-ghost inline-flex items-center gap-2 border border-slate-200 text-sm disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600 dark:text-slate-200"
                                :disabled="exporting"
                                @click="onExport"
                            >
                                <AppIcon
                                    :name="exporting ? 'refresh' : 'export'"
                                    :size="15"
                                    :class="exporting ? 'animate-spin' : ''"
                                />
                                {{ exporting ? 'Đang xuất…' : 'Xuất báo cáo' }}
                            </button>
                        </div>

                        <!-- KPI cards -->
                        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4 lg:grid-cols-5">
                            <div class="card p-4 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Tiến độ tổng</p>
                                <p class="mt-1 font-display text-2xl font-bold text-brand">{{ project.progress ?? 0 }}%</p>
                                <ProgressBar :value="project.progress" class="mt-2" />
                            </div>
                            <div class="card p-4 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Thành viên</p>
                                <p class="mt-1 font-display text-2xl font-bold text-brand">{{ projectMembers.length }}</p>
                                <div class="mt-1.5 flex -space-x-1">
                                    <Avatar v-for="m in projectMembers.slice(0,5)" :key="m.id" :name="m.name" :src="m.avatar_path" :size="20" class="ring-1 ring-white dark:ring-slate-900" />
                                </div>
                            </div>
                            <div class="card p-4 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Công việc</p>
                                <p class="mt-1 font-display text-2xl font-bold text-slate-800 dark:text-slate-100">{{ tasks.length }}</p>
                                <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">{{ completedTasks }} hoàn thành</p>
                            </div>
                            <div class="card p-4 dark:border-slate-700 dark:bg-slate-900">
                                <p class="text-xs text-slate-500 dark:text-slate-400">Sprint</p>
                                <p class="mt-1 font-display text-2xl font-bold text-sky-600 dark:text-sky-400">{{ sprints.length }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ sprints.filter(s => s.status?.value === 'active').length }} đang chạy</p>
                            </div>
                            <button
                                type="button"
                                class="card p-4 text-left transition hover:ring-2 hover:ring-rose-200 dark:border-slate-700 dark:bg-slate-900 dark:hover:ring-rose-800"
                                @click="scrollToRiskPanel"
                            >
                                <p class="text-xs text-slate-500 dark:text-slate-400">Vướng mắc mở</p>
                                <p class="mt-1 font-display text-2xl font-bold" :class="openBlockerCount ? 'text-rose-500' : 'text-slate-400'">{{ openBlockerCount }}</p>
                                <p class="mt-1 text-xs text-slate-400">{{ blockers.length }} tổng cộng · Nhấn để xem</p>
                            </button>
                        </div>

                        <DeadlineBanner
                            v-if="deadlineBanner"
                            :banner="deadlineBanner"
                            @dismiss="dismissBanner"
                        />

                        <div v-if="showProjectDetail || showActivity" class="grid min-w-0 gap-5 lg:grid-cols-3">
                            <ProjectOverviewCard
                                v-if="showProjectDetail"
                                :project="project"
                                :days-left="dashboardDaysLeft"
                                :class="showActivity ? 'lg:col-span-2' : 'lg:col-span-3'"
                            />

                            <div v-if="showActivity" :class="showProjectDetail ? '' : 'lg:col-span-3'">
                                <ActivityFeed
                                    :activities="activityLog"
                                    :visible-count="activityVisible"
                                    @show-more="showMoreActivity"
                                />
                            </div>
                        </div>

                        <GanttMini v-if="showGantt" :project="project" :tasks="tasks" />

                        <RiskIssuePanel
                            v-if="showRiskPanel"
                            ref="riskPanelRef"
                            :project-id="project.id"
                            :project-code="project.code"
                            :project-name="project.name"
                            :blockers="blockers"
                            :employees="options.employees"
                            :severity-options="enums.blockerSeverity || []"
                            :status-options="enums.blockerStatus || []"
                            :open-count="openIssueCount"
                            :can-manage="canManage"
                            :can-contribute="canContribute"
                            @saved="onRiskSaved"
                        />
                    </div>
                </div>

                <!-- ===== BOARD ===== -->
                <div v-show="tab === 'board'" class="flex h-full flex-col overflow-hidden">
                    <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
                        <h2 class="font-display font-semibold text-slate-800">Bảng Kanban</h2>
                        <button v-if="canManage" class="btn-primary text-sm" @click="openTaskModal()">
                            <AppIcon name="add" :size="15" /> Công việc
                        </button>
                    </div>
                    <div class="min-h-0 flex-1 overflow-hidden p-4">
                        <TaskBoard
                            :tasks="tasks"
                            :sprints="sprints"
                            :statuses="enums.taskStatus || []"
                            :can-edit="canContribute"
                            @move="onBoardMoveWithLog"
                            @view="openTaskDetail"
                            @add="(status) => openTaskModal(null, status)"
                        />
                    </div>
                </div>

                <!-- ===== SPRINT WORKSPACE ===== -->
                <div v-show="tab === 'sprints'" class="h-full min-h-0 overflow-hidden">
                    <SprintWorkspace
                        :project="project"
                        :sprints="sprints"
                        :tasks="tasks"
                        :blockers="blockers"
                        :epics="epics"
                        :attachments="attachments"
                        :employees="options.employees"
                        :enums="enums"
                        :can-manage="canManage"
                        :can-contribute="canContribute"
                    />
                </div>

                <!-- ===== BLOCKERS ===== -->
                <div v-show="tab === 'blockers'" class="h-full overflow-y-auto dark:bg-slate-950">
                    <div class="mx-auto min-w-0 max-w-[1600px] p-5">
                        <RiskIssueDataTable
                            :project-id="project.id"
                            :project-code="project.code"
                            :project-name="project.name"
                            :blockers="blockers"
                            :employees="options.employees"
                            :severity-options="enums.blockerSeverity || []"
                            :status-options="enums.blockerStatus || []"
                            :can-manage="canManage"
                            :can-contribute="canContribute"
                            @saved="onRiskSaved"
                        />
                    </div>
                </div>

            </div>
        </div>

        <TaskDetailPanel
            v-if="detailTask"
            :task="detailTask"
            :project-id="project.id"
            :project="project"
            :sprints="sprints"
            :employees="options.employees"
            :status-options="enums.taskStatus || []"
            :priority-options="enums.taskPriority || []"
            :phase-options="enums.taskPhase || []"
            :blockers="blockers"
            :attachments="attachments"
            :all-tasks="tasks"
            :epics="epics"
            :can-edit="canContribute"
            :can-comment="canContribute"
            @close="detailTask = null"
            @edit="openTaskEditFromDetail"
            @open-task="openTaskDetail"
            @updated="onTaskDetailUpdated"
        />

        <!-- ===== Modals ===== -->
        <TaskFormModal
            :show="taskModal" :project-id="project.id" :task="editingTask"
            :sprints="sprints" :employees="options.employees" :tasks="tasks"
            :status-options="enums.taskStatus || []" :priority-options="enums.taskPriority || []"
            :phase-options="enums.taskPhase || []"
            :default-status="taskDefaultStatus"
            @close="taskModal = false"
            @saved="onTaskSaved"
        />
        <SprintFormModal
            :show="sprintModal" :project-id="project.id" :sprint="editingSprint"
            :status-options="enums.sprintStatus || []"
            @close="sprintModal = false"
            @saved="onSprintSaved"
        />
        <BlockerFormModal
            :show="blockerModal"
            :blocker="editingBlocker"
            :projects="[{ id: project.id, name: project.name, code: project.code }]"
            :employees="options.employees"
            :severity-options="enums.blockerSeverity || []"
            :status-options="enums.blockerStatus || []"
            :default-project-id="project.id"
            :lock-project="true"
            :project-name="project.name"
            :project-code="project.code"
            @close="blockerModal = false"
        />
    </AppLayout>
</template>
