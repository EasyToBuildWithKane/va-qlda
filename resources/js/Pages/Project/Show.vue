<script setup>
import { ref, computed, onMounted, watch, toRef } from 'vue';
import { Head, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/Layouts/AppLayout.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import ProjectTimelineCenter from '@/modules/project/components/Timeline/ProjectTimelineCenter.vue';
import TaskBoard from '@/modules/project/components/TaskBoard.vue';
import TaskDetailPanel from '@/modules/project/components/Sprint/TaskDetailPanel.vue';
import TaskFormModal from '@/modules/project/components/TaskFormModal.vue';
import SprintFormModal from '@/modules/project/components/SprintFormModal.vue';
import DeadlineBanner from '@/modules/project/components/Dashboard/DeadlineBanner.vue';
import DashboardViewToggle from '@/modules/project/components/Dashboard/DashboardViewToggle.vue';
import RiskIssuePanel from '@/modules/project/components/Dashboard/RiskIssuePanel.vue';
import RiskIssueDataTable from '@/modules/project/components/Dashboard/RiskIssueDataTable.vue';
import ProjectFeedbackPanel from '@/modules/project/components/Dashboard/ProjectFeedbackPanel.vue';
import ActivityFeed from '@/modules/project/components/Dashboard/ActivityFeed.vue';
import ProjectOverviewCard from '@/modules/project/components/Dashboard/ProjectOverviewCard.vue';
import ProjectDocumentsPanel from '@/modules/project/components/Documents/ProjectDocumentsPanel.vue';
import SprintWorkspace from '@/modules/project/components/Sprint/SprintWorkspace.vue';
import { useToast } from '@/shared/composables/useToast';
import { useProjectDashboard } from '@/composables/useProjectDashboard';
import { useProjectExport } from '@/composables/useProjectExport';
import { normalizeEntities, normalizeKeyed, normalizeList } from '@/composables/useNormalizeList';

const props = defineProps({
    project: { type: Object, required: true },
    sprints: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    feedbacks: { type: Array, default: () => [] },
    feedbackSummary: { type: Object, default: () => ({}) },
    can: { type: Object, default: () => ({}) },
    epics: { type: Array, default: () => [] },
    attachments: { type: Array, default: () => [] },
    options: { type: Object, default: () => ({ employees: [], enums: {} }) },
    activityFeed: { type: Array, default: () => [] },
});

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
    { key: 'feedback', label: 'Phản hồi', icon: 'feedback' },
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
            toast.error('Không lưu được ngày trên Gantt. Vui lòng thử lại.');
        },
    });
};
const onBoardMove = ({ id, status }) => router.patch(`/projects/${pid}/tasks/${id}`, { status }, {
    preserveScroll: true,
    only: ['tasks'],
    onSuccess: () => onTaskDetailUpdated(),
});

// ---- Overview computed ----
const completedTasks = computed(() => props.tasks.filter((t) => t.status.value === 'done').length);
const openBlockerCount = computed(() => props.blockers.filter((b) => !['resolved', 'closed'].includes(b.status?.value)).length);
const openFeedbackCount = computed(() => props.feedbackSummary?.open ?? 0);
const canFeedbackCreate = computed(() => props.can?.feedbackCreate ?? false);

// ---- Dashboard (overview tab) ----
const riskPanelRef = ref(null);
const {
    viewMode,
    activityLog,
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
} = useProjectDashboard(pid, {
    project: toRef(() => props.project),
    tasks: toRef(() => props.tasks),
    blockers: toRef(() => props.blockers),
    sprints: toRef(() => props.sprints),
    members: projectMembers,
    activityFeed: toRef(() => props.activityFeed),
});
const { exportReport } = useProjectExport();

onMounted(() => {
    seedActivityIfEmpty();
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
    <div class="flex min-h-0 w-full min-w-0 flex-1 flex-col overflow-hidden bg-slate-50">
      <!-- ── Compact tab strip ── -->
      <nav
        class="flex shrink-0 items-center gap-0.5 overflow-x-auto overscroll-x-contain border-b border-slate-200 bg-white px-2 sm:px-3"
        aria-label="Tab dự án"
      >
        <button
          v-for="t in tabList"
          :key="t.key"
          class="flex shrink-0 items-center gap-1.5 whitespace-nowrap border-b-2 px-2.5 py-2.5 text-sm font-medium transition sm:px-3"
          :class="tab === t.key
            ? 'border-brand text-brand'
            : 'border-transparent text-slate-500 hover:text-slate-700'"
          @click="tab = t.key"
        >
          <AppIcon
            :name="t.icon"
            :size="15"
          />
          {{ t.label }}
          <span
            v-if="t.key === 'documents' && attachmentCount"
            class="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-sky-100 px-1 text-[10px] font-bold text-sky-600"
          >{{ attachmentCount }}</span>
          <span
            v-if="t.key === 'blockers' && openBlockerCount"
            class="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-rose-100 px-1 text-[10px] font-bold text-rose-600"
          >{{ openBlockerCount }}</span>
          <span
            v-if="t.key === 'feedback' && openFeedbackCount"
            class="ml-0.5 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-amber-100 px-1 text-[10px] font-bold text-amber-700"
          >{{ openFeedbackCount }}</span>
        </button>
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

        <!-- ===== TIMELINE / GANTT ===== -->
        <div
          v-show="tab === 'timeline'"
          class="h-full"
        >
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
        <div
          v-show="tab === 'overview'"
          class="h-full w-full min-w-0 overflow-x-hidden overflow-y-auto dark:bg-slate-950"
        >
          <div class="w-full min-w-0 space-y-4 p-4 sm:space-y-5 sm:p-5 lg:p-6">
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
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Tiến độ tổng
                </p>
                <p class="mt-1 font-display text-2xl font-bold text-brand">
                  {{ project.progress ?? 0 }}%
                </p>
                <ProgressBar
                  :value="project.progress"
                  class="mt-2"
                />
              </div>
              <div class="card p-4 dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Thành viên
                </p>
                <p class="mt-1 font-display text-2xl font-bold text-brand">
                  {{ projectMembers.length }}
                </p>
                <div class="mt-1.5 flex -space-x-1">
                  <Avatar
                    v-for="m in projectMembers.slice(0,5)"
                    :key="m.id"
                    :name="m.name"
                    :src="m.avatar_path"
                    :size="20"
                    class="ring-1 ring-white dark:ring-slate-900"
                  />
                </div>
              </div>
              <div class="card p-4 dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Công việc
                </p>
                <p class="mt-1 font-display text-2xl font-bold text-slate-800 dark:text-slate-100">
                  {{ tasks.length }}
                </p>
                <p class="mt-1 text-xs text-emerald-600 dark:text-emerald-400">
                  {{ completedTasks }} hoàn thành
                </p>
              </div>
              <div class="card p-4 dark:border-slate-700 dark:bg-slate-900">
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Sprint
                </p>
                <p class="mt-1 font-display text-2xl font-bold text-sky-600 dark:text-sky-400">
                  {{ sprints.length }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                  {{ sprints.filter(s => s.status?.value === 'active').length }} đang chạy
                </p>
              </div>
              <button
                type="button"
                class="card p-4 text-left transition hover:ring-2 hover:ring-rose-200 dark:border-slate-700 dark:bg-slate-900 dark:hover:ring-rose-800"
                @click="scrollToRiskPanel"
              >
                <p class="text-xs text-slate-500 dark:text-slate-400">
                  Vướng mắc mở
                </p>
                <p
                  class="mt-1 font-display text-2xl font-bold"
                  :class="openBlockerCount ? 'text-rose-500' : 'text-slate-400'"
                >
                  {{ openBlockerCount }}
                </p>
                <p class="mt-1 text-xs text-slate-400">
                  {{ blockers.length }} tổng cộng · Nhấn để xem
                </p>
              </button>
            </div>

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
      @close="closeTaskDetail"
      @edit="openTaskEditFromDetail"
      @open-task="openTaskDetail"
      @updated="onTaskDetailUpdated"
      @panel-tab-change="onTaskPanelTabChange"
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
