<script setup>
import { ref, computed, onMounted, watch, toRef, defineAsyncComponent } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import { PROJECT_COLORS } from '@/modules/project/utils/projectColors';

const ProjectCalendar = defineAsyncComponent(
    () => import('@/modules/project/components/Calendar/ProjectCalendar.vue'),
);
import TaskBoard from '@/modules/project/components/TaskBoard.vue';
import TaskDetailPanel from '@/modules/project/components/Sprint/TaskDetailPanel.vue';
import TaskFormModal from '@/modules/project/components/TaskFormModal.vue';
import SprintFormModal from '@/modules/project/components/SprintFormModal.vue';
import DeadlineBanner from '@/modules/project/components/Dashboard/DeadlineBanner.vue';
import RiskIssuePanel from '@/modules/project/components/Dashboard/RiskIssuePanel.vue';
import RiskIssueDataTable from '@/modules/project/components/Dashboard/RiskIssueDataTable.vue';
import ProjectFeedbackPanel from '@/modules/project/components/Dashboard/ProjectFeedbackPanel.vue';
import ActivityFeed from '@/modules/project/components/Dashboard/ActivityFeed.vue';
import ProjectOverviewCard from '@/modules/project/components/Dashboard/ProjectOverviewCard.vue';
import ProjectShowSummaryBar from '@/modules/project/components/Dashboard/ProjectShowSummaryBar.vue';
import WorkloadTable from '@/modules/project/components/Dashboard/WorkloadTable.vue';
import ProjectDocumentsPanel from '@/modules/project/components/Documents/ProjectDocumentsPanel.vue';
import SprintWorkspace from '@/modules/project/components/Sprint/SprintWorkspace.vue';
import WeeklyReportWorkspace from '@/modules/project/components/WeeklyReport/WeeklyReportWorkspace.vue';
import TaskCompleteModal from '@/modules/project/components/Sprint/TaskCompleteModal.vue';
import { useTaskCompleteModal } from '@/composables/useTaskCompleteModal';
import { isTaskStatusLocked } from '@/composables/useTaskCompletion';
import { useToast } from '@/shared/composables/useToast';
import { useProjectDashboard } from '@/composables/useProjectDashboard';
import { normalizeEntities, normalizeKeyed, normalizeList } from '@/composables/useNormalizeList';

const props = defineProps({
    project: { type: Object, required: true },
    sprints: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    feedbacks: { type: Array, default: () => [] },
    feedbackSummary: { type: Object, default: () => ({}) },
    summary: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
    epics: { type: Array, default: () => [] },
    attachments: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({ employees: [], enums: {} }) },
    activityFeed: { type: Array, default: () => [] },
    weeklyReports: { type: Object, default: () => ({ sprint: null, weeks: [], current_week: 1 }) },
    weeklyReport: { type: Object, default: null },
});

const toast = useToast();
const page = usePage();
const enums = computed(() => props.options.enums || {});
const currentUserName = computed(() => page.props.auth?.user?.name?.split(' ').pop() || 'Thành viên');

const projectHeaderSubtitle = computed(() => {
    const parts = [props.project.code, props.project.status?.label].filter(Boolean);
    return parts.join(' · ') || 'Chi tiết dự án';
});

const projectIconColor = computed(() => {
    const c = props.project.color;
    return PROJECT_COLORS.includes(c) ? c : 'brand';
});
const canManage = computed(() => props.project.can?.manage);
const canContribute = computed(() => props.project.can?.contribute);
const projectMembers = computed(() => normalizeEntities(props.project?.members));

const tabs = [
    { key: 'overview', label: 'Tổng quan', icon: 'overview' },
    { key: 'documents', label: 'Tài liệu', icon: 'documents' },
    { key: 'timeline', label: 'Lịch dự án', icon: 'calendar' },
    { key: 'board', label: 'Kanban', icon: 'board' },
    { key: 'sprints', label: 'Sprint', icon: 'sprint' },
    { key: 'blockers', label: 'Vướng mắc', icon: 'blockers' },
    { key: 'feedback', label: 'Phản hồi', icon: 'feedback' },
    { key: 'weekly', label: 'Báo cáo tuần', icon: 'weekly' },
];
const tabList = normalizeKeyed(tabs);
const tab = ref('overview');

const documentCategories = computed(() =>
    normalizeList(props.options.enums?.projectAttachmentCategory).filter((c) => c?.value != null),
);
const attachmentCount = computed(() => props.attachments.filter((a) => !a.is_folder).length);

// --- Modal state ---
const taskModal = ref(false);
const editingTask = ref(null);
const detailTask = ref(null);
const taskPanelTab = ref('overview');
const taskDefaultStatus = ref('todo');
const sprintModal = ref(false);
const editingSprint = ref(null);
const pid = props.project.id;

const openTaskDetail = (t, { panelTab } = {}) => {
    if (!t) return;
    const id = typeof t === 'object' ? t?.id : t;
    if (id == null) return;
    taskPanelTab.value = panelTab || 'overview';
    const fresh = props.tasks.find((x) => x?.id === id);
    detailTask.value = fresh ?? (typeof t === 'object' && t?.id != null ? t : null);
    syncTaskInUrl();
};
const closeTaskDetail = () => {
    detailTask.value = null;
    taskPanelTab.value = 'overview';
    syncTaskInUrl();
};
const openTaskModal = (t = null, status = 'todo') => { editingTask.value = t; taskDefaultStatus.value = status; taskModal.value = true; };
const syncTaskInUrl = () => {
    const u = new URL(window.location.href);
    if (detailTask.value?.id) {
        u.searchParams.set('task', String(detailTask.value.id));
        if (taskPanelTab.value === 'collaboration') {
            u.searchParams.set('discussion', '1');
        } else {
            u.searchParams.delete('discussion');
        }
    } else {
        u.searchParams.delete('task');
        u.searchParams.delete('discussion');
    }
    window.history.replaceState(window.history.state, '', u);
};

const onTaskPanelTabChange = (key) => {
    taskPanelTab.value = key;
    syncTaskInUrl();
};
const openTaskEditFromDetail = (t) => {
    detailTask.value = null;
    openTaskModal(t);
};
const onTaskDetailUpdated = () => {
    if (!detailTask.value) return;
    const fresh = props.tasks.find((t) => t.id === detailTask.value.id);
    if (fresh) detailTask.value = fresh;
};

watch(
    () => props.tasks,
    () => {
        if (detailTask.value) onTaskDetailUpdated();
    },
    { deep: true },
);

const ganttRevertPreviewId = ref(null);

const onGanttDate = ({ id, start, end }) => {
    ganttRevertPreviewId.value = null;
    router.put(`/projects/${pid}/tasks/${id}`, { start_date: start, due_date: end }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => toast.success('Đã cập nhật lịch công việc'),
        onError: () => {
            ganttRevertPreviewId.value = id;
            toast.error('Không lưu được lịch công việc. Vui lòng thử lại.');
        },
    });
};
const { requestComplete } = useTaskCompleteModal();

const onBoardMove = ({ id, status }) => {
    const task = props.tasks.find((t) => t.id === id);
    if (!task) return;
    if (isTaskStatusLocked(task) && status !== 'done') {
        toast.error('Công việc đã hoàn thành — không thể đổi trạng thái.');
        return;
    }
    if (status === 'done' && task.status?.value !== 'done') {
        requestComplete(task, { onSuccess: () => onTaskDetailUpdated() });
        return;
    }
    router.patch(`/projects/${pid}/tasks/${id}`, { status }, {
        preserveScroll: true,
        only: ['tasks'],
        onSuccess: () => onTaskDetailUpdated(),
    });
};

// ---- Overview computed ----
const openBlockerCount = computed(() => props.blockers.filter((b) => !['resolved', 'closed'].includes(b.status?.value)).length);
const openFeedbackCount = computed(() => props.feedbackSummary?.open ?? 0);
const canFeedbackCreate = computed(() => props.can?.feedbackCreate ?? false);
const canWeeklyGenerate = computed(() => props.can?.weeklyGenerate ?? false);

// ---- Dashboard (overview tab) ----
const riskPanelRef = ref(null);
const {
    activityLog,
    pushActivity,
    openIssueCount,
    daysLeft: dashboardDaysLeft,
    deadlineBanner,
    dismissBanner,
    showRiskPanel,
    showActivity,
    showProjectDetail,
    memberWorkload,
} = useProjectDashboard(pid, {
    project: toRef(() => props.project),
    tasks: toRef(() => props.tasks),
    blockers: toRef(() => props.blockers),
    sprints: toRef(() => props.sprints),
    members: projectMembers,
    activityFeed: toRef(() => props.activityFeed),
});

onMounted(() => {
    const params = new URLSearchParams(window.location.search);
    const t = params.get('tab');
    if (t && tabList.some((x) => x.key === t)) {
        tab.value = t;
    }
    const taskId = params.get('task');
    if (taskId) {
        const id = Number(taskId);
        const found = props.tasks.find((x) => x?.id === id);
        if (found) {
            taskPanelTab.value = params.get('discussion') === '1' ? 'collaboration' : 'overview';
            detailTask.value = found;
        }
    }
});

watch(tab, (t) => {
    const u = new URL(window.location.href);
    u.searchParams.set('tab', t);
    window.history.replaceState(window.history.state, '', u);
});

const workloadSectionRef = ref(null);

function onSummaryNavigate(target) {
    if (target === 'members') {
        workloadSectionRef.value?.scrollIntoView?.({ behavior: 'smooth', block: 'start' });
        return;
    }
    if (['board', 'sprints', 'blockers'].includes(target)) {
        tab.value = target;
    }
}

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
      />
    </template>

    <!-- Full-height flex column -->
    <div class="flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden bg-slate-50">
      <!-- ── Tab strip (pill, đồng bộ typography text-xs/sm) ── -->
      <nav
        class="flex shrink-0 items-center overflow-x-auto overscroll-x-contain border-b border-slate-200 bg-white px-2 py-1.5 sm:px-3"
        aria-label="Tab dự án"
      >
        <div
          class="inline-flex min-w-0 items-stretch gap-0.5 rounded-lg border border-slate-200/90 bg-slate-100/90 p-0.5 dark:border-slate-700 dark:bg-slate-800/60"
          role="group"
        >
          <button
            v-for="t in tabList"
            :key="t.key"
            type="button"
            class="inline-flex shrink-0 items-center gap-1.5 whitespace-nowrap rounded-md px-2 py-1.5 text-xs font-medium transition sm:px-2.5 sm:text-sm"
            :class="tab === t.key
              ? 'bg-white text-brand shadow-sm dark:bg-slate-700 dark:text-brand-100'
              : 'text-slate-500 hover:text-slate-700 dark:text-slate-400 dark:hover:text-slate-200'"
            @click="tab = t.key"
          >
            <AppIcon
              :name="t.icon"
              :size="14"
            />
            {{ t.label }}
            <span
              v-if="t.key === 'documents' && attachmentCount"
              class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-sky-100 px-1 text-[10px] font-semibold tabular-nums text-sky-700 dark:bg-sky-950 dark:text-sky-300"
            >{{ attachmentCount }}</span>
            <span
              v-if="t.key === 'blockers' && openBlockerCount"
              class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-100 px-1 text-[10px] font-semibold tabular-nums text-rose-700 dark:bg-rose-950 dark:text-rose-300"
            >{{ openBlockerCount }}</span>
            <span
              v-if="t.key === 'feedback' && openFeedbackCount"
              class="inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-100 px-1 text-[10px] font-semibold tabular-nums text-amber-800 dark:bg-amber-950 dark:text-amber-200"
            >{{ openFeedbackCount }}</span>
          </button>
        </div>
      </nav>

      <!-- ── Tab content: fills all remaining height ── -->
      <div class="flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden">
        <!-- ===== DOCUMENTS ===== -->
        <div
          v-show="tab === 'documents'"
          class="documents-tab-viewport flex min-h-0 flex-1 flex-col overflow-hidden"
        >
          <ProjectDocumentsPanel
            class="h-full min-h-0 flex-1"
            :project-id="project.id"
            :attachments="attachments"
            :categories="documentCategories"
            :can-upload="canContribute"
            :can-edit="canContribute"
            :can-delete="canManage"
          />
        </div>

        <!-- ===== LỊCH DỰ ÁN ===== -->
        <div
          v-if="tab === 'timeline'"
          class="h-full"
        >
          <ProjectCalendar
            :project="project"
            :tasks="tasks"
            :sprints="sprints"
            :can-manage="canManage"
            :can-contribute="canContribute"
            :revert-task-id="ganttRevertPreviewId"
            @create-task="openTaskModal()"
            @select-task="(id) => openTaskDetail(tasks.find((t) => t.id == id))"
            @date-change="onGanttDate"
          />
        </div>

        <!-- ===== OVERVIEW (Dashboard) ===== -->
        <div
          v-show="tab === 'overview'"
          class="flex h-full w-full min-w-0 flex-col overflow-x-hidden overflow-y-auto dark:bg-slate-950"
        >
          <ProjectShowSummaryBar
            :summary="summary"
            @navigate="onSummaryNavigate"
          />
          <div class="w-full min-w-0 space-y-4 p-4 sm:space-y-5 sm:p-5 lg:p-6">
            <DeadlineBanner
              v-if="deadlineBanner"
              :banner="deadlineBanner"
              @dismiss="dismissBanner"
            />

            <div
              v-if="showProjectDetail || showActivity"
              class="grid min-w-0 gap-5 lg:grid-cols-3"
            >
              <ProjectOverviewCard
                v-if="showProjectDetail"
                :project="project"
                :days-left="dashboardDaysLeft"
                :class="showActivity ? 'lg:col-span-2' : 'lg:col-span-3'"
              />

              <div
                v-if="showActivity"
                :class="showProjectDetail ? '' : 'lg:col-span-3'"
              >
                <ActivityFeed :activities="activityLog" />
              </div>
            </div>

            <div
              ref="workloadSectionRef"
              class="scroll-mt-4"
            >
              <WorkloadTable :rows="memberWorkload" />
            </div>

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
        <div
          v-show="tab === 'board'"
          class="flex h-full flex-col overflow-hidden"
        >
          <div class="flex shrink-0 items-center justify-between border-b border-slate-200 bg-white px-5 py-3">
            <h2 class="font-display font-semibold text-slate-800">
              Bảng Kanban
            </h2>
            <button
              v-if="canManage"
              class="btn-primary text-sm"
              @click="openTaskModal()"
            >
              <AppIcon
                name="add"
                :size="15"
              /> Công việc
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
        <div
          v-show="tab === 'sprints'"
          class="h-full min-h-0 overflow-hidden"
        >
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
        <div
          v-show="tab === 'blockers'"
          class="h-full w-full min-w-0 overflow-x-hidden overflow-y-auto dark:bg-slate-950"
        >
          <div class="w-full min-w-0 p-4 sm:p-5 lg:p-6">
            <RiskIssueDataTable
              layout="page"
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

        <!-- ===== FEEDBACK ===== -->
        <div
          v-show="tab === 'feedback'"
          class="flex h-full min-h-0 w-full min-w-0 flex-col overflow-hidden dark:bg-slate-950"
        >
          <div class="flex min-h-0 w-full min-w-0 flex-1 flex-col p-4 sm:p-5 lg:p-6">
            <ProjectFeedbackPanel
              class="min-h-0 flex-1"
              :project-id="project.id"
              :project-code="project.code"
              :project-name="project.name"
              :feedbacks="feedbacks"
              :summary="feedbackSummary"
              :employees="options.employees"
              :category-options="enums.feedbackCategory || []"
              :status-options="enums.feedbackStatus || []"
              :priority-options="enums.taskPriority || []"
              :can-create="canFeedbackCreate"
            />
          </div>
        </div>

        <!-- ===== BÁO CÁO TUẦN (Weekly Report) ===== -->
        <div
          v-show="tab === 'weekly'"
          class="h-full min-h-0 overflow-hidden"
        >
          <WeeklyReportWorkspace
            :project-id="project.id"
            :overview="weeklyReports"
            :detail="weeklyReport"
            :can-generate="canWeeklyGenerate"
          />
        </div>
      </div>
    </div>

    <TaskDetailPanel
      v-if="detailTask"
      :task="detailTask"
      :initial-panel-tab="taskPanelTab"
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
      :can-delete="canManage"
      @close="closeTaskDetail"
      @edit="openTaskEditFromDetail"
      @open-task="openTaskDetail"
      @updated="onTaskDetailUpdated"
      @panel-tab-change="onTaskPanelTabChange"
      @deleted="closeTaskDetail"
    />

    <!-- ===== Modals ===== -->
    <TaskFormModal
      :show="taskModal"
      :project-id="project.id"
      :task="editingTask"
      :sprints="sprints"
      :employees="options.employees"
      :tasks="tasks"
      :status-options="enums.taskStatus || []"
      :priority-options="enums.taskPriority || []"
      :phase-options="enums.taskPhase || []"
      :default-status="taskDefaultStatus"
      @close="taskModal = false"
      @saved="onTaskSaved"
    />
    <SprintFormModal
      :show="sprintModal"
      :project-id="project.id"
      :sprint="editingSprint"
      :status-options="enums.sprintStatus || []"
      @close="sprintModal = false"
      @saved="onSprintSaved"
    />
    <TaskCompleteModal :project-id="project.id" />
  </AppLayout>
</template>

<style scoped>
.documents-tab-viewport {
    background: rgb(248 250 252);
}

:global(.dark) .documents-tab-viewport {
    background: rgb(2 6 23);
}
</style>
