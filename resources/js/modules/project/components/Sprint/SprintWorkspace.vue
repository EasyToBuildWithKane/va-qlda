<script setup>
import { ref, computed, toRef, onMounted, onBeforeUnmount, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import SprintFormModal from '@/modules/project/components/SprintFormModal.vue';
import TaskFormModal from '@/modules/project/components/TaskFormModal.vue';
import SprintListView from '@/modules/project/components/Sprint/SprintListView.vue';
import SprintCalendarView from '@/modules/project/components/Sprint/SprintCalendarView.vue';
import TaskDetailPanel from '@/modules/project/components/Sprint/TaskDetailPanel.vue';
import SprintDataModal from '@/modules/project/components/Sprint/SprintDataModal.vue';
import TaskCompleteModal from '@/modules/project/components/Sprint/TaskCompleteModal.vue';
import { useSprintWorkspace } from '@/composables/useSprintWorkspace';
import { filterRootTasks } from '@/composables/useTaskHierarchy';
import { useSprintTaskTable } from '@/composables/useSprintTaskTable';
import { useSprintReconcile } from '@/composables/useSprintReconcile';
import { useDialog } from '@/composables/useDialog';
import { useToast } from '@/shared/composables/useToast';
import EmptyState from '@/shared/ui/EmptyState.vue';

const props = defineProps({
    project: { type: Object, required: true },
    sprints: { type: Array, default: () => [] },
    tasks: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    epics: { type: Array, default: () => [] },
    attachments: { type: Array, default: () => [] },
    employees: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
});

const dialog = useDialog();
const toast = useToast();
const pid = props.project.id;

const {
    sprintById,
    sprintMetrics,
} = useSprintWorkspace(toRef(() => props.sprints), toRef(() => props.tasks), toRef(() => props.project));

const globalSearch = ref('');

const table = useSprintTaskTable(toRef(() => props.tasks), {
    globalSearch,
    sprintById,
});

const { issues, summary } = useSprintReconcile(toRef(() => props.sprints), toRef(() => props.tasks));

/** Task theo sprint sau khi lọc tìm kiếm toolbar. */
const filteredTasksBySprint = computed(() => {
    const map = {};
    props.sprints.forEach((s) => { map[s.id] = []; });
    filterRootTasks(table.filtered.value).forEach((t) => {
        if (t.sprint_id && map[t.sprint_id]) {
            map[t.sprint_id].push(t);
        }
    });
    return map;
});

const filteredBacklogTasks = computed(() => filterRootTasks(table.filtered.value).filter((t) => !t.sprint_id));

const listWorkspaceEmpty = computed(() => !props.sprints.length && !filteredBacklogTasks.value.length);
const listSearchEmpty = computed(() => listWorkspaceEmpty.value && globalSearch.value.trim().length > 0);

const calendarEmpty = computed(() => !table.filtered.value.length);
const calendarSearchEmpty = computed(() => calendarEmpty.value && globalSearch.value.trim().length > 0);

const viewMode = ref('list');
const dataModalOpen = ref(false);
const dataModalTab = ref('import');
const expandedSprints = ref(new Set());
const emailMenuOpen = ref(false);
const emailMenuRef = ref(null);
const detailTask = ref(null);
const sprintModal = ref(false);
const editingSprint = ref(null);
const taskModal = ref(false);
const editingTask = ref(null);
const taskSprintPreset = ref(null);
const taskDefaultStatus = ref('todo');

const toggleSprint = (id) => {
    const s = new Set(expandedSprints.value);
    s.has(id) ? s.delete(id) : s.add(id);
    expandedSprints.value = s;
};

const openTask = (t) => {
    if (!t) return;
    const id = typeof t === 'object' ? t?.id : t;
    if (id == null) return;
    detailTask.value = props.tasks.find((x) => x?.id === id) ?? (typeof t === 'object' && t?.id != null ? t : null);
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
const openTaskModal = (t = null, status = 'todo', sprintId = null) => {
    editingTask.value = t?.id != null ? t : null;
    taskSprintPreset.value = (!t?.id && sprintId) ? sprintId : null;
    taskDefaultStatus.value = status;
    taskModal.value = true;
};
const openTaskEdit = (t) => {
    if (!t?.id) return;
    openTaskModal(t);
};
const openTaskEditFromDetail = (t) => {
    detailTask.value = null;
    openTaskEdit(t);
};
const openSprint = (s = null) => { editingSprint.value = s; sprintModal.value = true; };

const duplicateSprint = async (s) => {
    router.post(`/projects/${pid}/sprints`, {
        name: `${s.name} (bản sao)`,
        goal: s.goal,
        status: 'planned',
        start_date: s.start_date,
        end_date: s.end_date,
    }, { preserveScroll: true, onSuccess: () => toast.success('Đã nhân bản sprint') });
};

const closeSprint = (s) => {
    router.put(`/projects/${pid}/sprints/${s.id}`, {
        name: s.name,
        goal: s.goal,
        status: 'completed',
        start_date: s.start_date,
        end_date: s.end_date,
    }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã đóng sprint'),
    });
};

const removeSprint = async (s) => {
    if (!await dialog.confirm({ title: 'Xoá sprint', message: `Xoá "${s.name}"?`, tone: 'danger', confirmText: 'Xoá' })) return;
    router.delete(`/projects/${pid}/sprints/${s.id}`, { preserveScroll: true });
};

const removeTask = async (t) => {
    if (!t?.id || !props.canManage) return;
    const childCount = props.tasks.filter((x) => x.parent_id === t.id).length;
    let message = `Xoá công việc «${t.title}»? Hành động không thể hoàn tác.`;
    if (childCount) {
        message += ` ${childCount} công việc con sẽ tách khỏi công việc cha.`;
    }
    if (!await dialog.confirm({
        title: 'Xoá công việc',
        message,
        tone: 'danger',
        confirmText: 'Xoá',
    })) {
        return;
    }
    router.delete(`/projects/${pid}/tasks/${t.id}`, {
        preserveScroll: true,
        onSuccess: () => {
            if (detailTask.value?.id === t.id) {
                detailTask.value = null;
            }
            toast.success('Đã xoá công việc');
        },
        onError: () => toast.error('Không thể xoá công việc'),
    });
};

const reorderSprints = (ids) => {
    router.patch(`/projects/${pid}/sprints/reorder`, { ids }, {
        preserveScroll: true,
        onSuccess: () => toast.success('Đã cập nhật thứ tự sprint'),
        onError: () => toast.error('Không thể sắp xếp sprint'),
    });
};

const openDataModal = (t = 'import') => {
    dataModalTab.value = t;
    dataModalOpen.value = true;
};

const onReconcileFix = (issue) => {
    dataModalOpen.value = false;
    if (issue.taskId) {
        const t = props.tasks.find((x) => x.id === issue.taskId);
        if (t) openTask(t);
    } else if (issue.sprintId) {
        const s = props.sprints.find((x) => x.id === issue.sprintId);
        if (s) openSprint(s);
    }
};

const viewModes = [
    { key: 'list', label: 'Danh sách', icon: 'template' },
    { key: 'calendar', label: 'Lịch', icon: 'calendar' },
];

const emailSprintId = computed(() => {
    const fromExpanded = props.sprints.find((s) => expandedSprints.value.has(s.id));
    if (fromExpanded?.id != null) {
        return fromExpanded.id;
    }

    return props.sprints[0]?.id ?? null;
});

function onEmailClickOutside(e) {
    if (emailMenuRef.value && !emailMenuRef.value.contains(e.target)) {
        emailMenuOpen.value = false;
    }
}

const emailSending = ref(false);

function toastEmailFlash(page) {
    const flash = page?.props?.flash ?? {};
    if (flash.success) {
        toast.success(flash.success);
    } else if (flash.warning) {
        toast.warning(flash.warning);
    } else if (flash.error) {
        toast.error(flash.error);
    }
}

const emailPostOptions = {
    preserveScroll: true,
    onStart: () => { emailSending.value = true; },
    onFinish: () => { emailSending.value = false; },
    onSuccess: (page) => toastEmailFlash(page),
    onError: () => toast.error('Không gửi được email. Kiểm tra kết nối hoặc thử lại.'),
};

async function sendDailySummaryEmail() {
    emailMenuOpen.value = false;
    const sprint = props.sprints.find((s) => s.id === emailSprintId.value);
    const scope = sprint ? ` trong sprint «${sprint.name}»` : ' (phạm vi toàn dự án)';
    if (!await dialog.confirm({
        title: 'Gửi email tổng hợp trong ngày',
        message: `Gửi email tổng hợp công việc có hạn hoặc cập nhật hôm nay${scope} tới từng người phụ trách? Thư sẽ được xếp hàng trên hệ thống.`,
        confirmText: 'Gửi email',
    })) {
        return;
    }
    router.post(route('projects.email.daily-summary', pid), {
        sprint_id: emailSprintId.value ?? undefined,
    }, emailPostOptions);
}

async function sendSprintSummaryEmail() {
    if (!emailSprintId.value) return;
    emailMenuOpen.value = false;
    const sprint = props.sprints.find((s) => s.id === emailSprintId.value);
    const sprintLabel = sprint?.name ? `«${sprint.name}»` : 'sprint đang chọn';
    if (!await dialog.confirm({
        title: 'Gửi email tổng hợp sprint',
        message: `Gửi email danh sách công việc sprint ${sprintLabel} tới từng người phụ trách? Thư sẽ được xếp hàng trên hệ thống.`,
        confirmText: 'Gửi email',
    })) {
        return;
    }
    router.post(route('projects.email.sprint-summary', [pid, emailSprintId.value]), {}, emailPostOptions);
}

onMounted(() => {
    const s = new Set(expandedSprints.value);
    props.sprints.slice(0, 2).forEach((sp) => s.add(sp.id));
    expandedSprints.value = s;
    document.addEventListener('mousedown', onEmailClickOutside);
});

onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onEmailClickOutside);
});
</script>

<template>
  <div class="flex h-full min-h-0 flex-col bg-slate-50/50 dark:bg-slate-950">
    <!-- Toolbar -->
    <div class="sticky top-0 z-20 shrink-0 border-b border-slate-200/80 bg-white/95 px-3 py-2.5 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
      <div class="flex flex-wrap items-center gap-2">
        <div class="flex shrink-0 items-center gap-2 pr-1">
          <span class="grid h-8 w-8 place-items-center rounded-lg bg-brand-50 text-brand dark:bg-brand-950/40">
            <AppIcon
              name="sprint"
              :size="16"
            />
          </span>
          <div class="hidden min-w-0 sm:block">
            <p class="text-sm font-semibold leading-none text-slate-800 dark:text-slate-100">
              Sprint
            </p>
            <p class="mt-0.5 text-[10px] text-slate-400">
              {{ sprints.length }} chu kỳ
              <span v-if="sprints.filter((sp) => sp.status?.value === 'active').length">
                · {{ sprints.filter((sp) => sp.status?.value === 'active').length }} đang chạy
              </span>
            </p>
          </div>
        </div>
        <div class="relative min-w-[12rem] flex-1 lg:min-w-[16rem]">
          <AppIcon
            name="search"
            :size="14"
            class="absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
          />
          <input
            v-model="globalSearch"
            type="search"
            class="input w-full py-1.5 pl-8 text-sm"
            placeholder="Tìm task, sprint, người phụ trách, ID…"
          >
        </div>
        <div class="flex rounded-lg border border-slate-200 p-0.5 dark:border-slate-600">
          <button
            v-for="vm in viewModes"
            :key="vm.key"
            type="button"
            class="rounded-md px-2 py-1 text-xs font-medium transition"
            :class="viewMode === vm.key ? 'bg-brand text-white' : 'text-slate-500 hover:text-slate-800'"
            :title="vm.label"
            @click="viewMode = vm.key"
          >
            <AppIcon
              :name="vm.icon"
              :size="14"
            />
          </button>
        </div>
        <button
          type="button"
          class="btn-ghost border border-slate-200 text-xs dark:border-slate-600"
          @click="openDataModal('import')"
        >
          <AppIcon
            name="download"
            :size="14"
          /> Dữ liệu
          <span
            v-if="summary.errors"
            class="ml-1 rounded-full bg-rose-500 px-1 text-[10px] text-white"
          >{{ summary.errors }}</span>
        </button>
        <button
          v-if="canContribute"
          type="button"
          class="btn-primary text-xs"
          @click="openTaskModal()"
        >
          <AppIcon
            name="add"
            :size="14"
          /> Task
        </button>
        <button
          v-if="canManage"
          ref="emailMenuRef"
          type="button"
          class="relative inline-flex h-9 shrink-0 items-center gap-1 rounded-btn border border-slate-200 px-2.5 text-xs font-medium text-slate-600 hover:bg-slate-50 disabled:cursor-not-allowed disabled:opacity-60 dark:border-slate-600"
          :disabled="emailSending"
          @click="emailMenuOpen = !emailMenuOpen"
        >
          <AppIcon
            name="mail"
            :size="15"
          />
          <span>Gửi email</span>
          <AppIcon
            name="chevron-down"
            :size="12"
          />
          <div
            v-if="emailMenuOpen"
            class="absolute right-0 top-full z-30 mt-1 min-w-[12rem] rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-600 dark:bg-slate-900"
          >
            <button
              type="button"
              class="block w-full px-3 py-2 text-left text-xs text-slate-700 hover:bg-slate-50 disabled:cursor-not-allowed disabled:text-slate-300 dark:text-slate-200 dark:hover:bg-slate-800"
              :disabled="emailSending"
              @click="sendDailySummaryEmail"
            >
              Tổng hợp hôm nay
            </button>
            <button
              type="button"
              class="block w-full px-3 py-2 text-left text-xs disabled:cursor-not-allowed disabled:text-slate-300"
              :class="emailSprintId ? 'text-slate-700 hover:bg-slate-50 dark:text-slate-200 dark:hover:bg-slate-800' : 'text-slate-400'"
              :disabled="!emailSprintId || emailSending"
              :title="emailSprintId ? 'Gửi theo sprint đang mở đầu tiên' : 'Chưa có sprint'"
              @click="sendSprintSummaryEmail"
            >
              Tổng hợp sprint
            </button>
          </div>
        </button>
        <button
          v-if="canManage"
          type="button"
          class="btn-primary text-xs"
          @click="openSprint()"
        >
          <AppIcon
            name="add"
            :size="14"
          /> Sprint
        </button>
      </div>
    </div>

    <!-- Main content -->
    <div class="min-h-0 flex-1 overflow-y-auto p-4">
      <EmptyState
        v-if="viewMode === 'list' && listWorkspaceEmpty"
        :icon="listSearchEmpty ? 'search' : 'sprint'"
        :title="listSearchEmpty ? 'Không tìm thấy kết quả' : 'Chưa có sprint'"
        :description="listSearchEmpty
          ? 'Thử đổi từ khóa tìm kiếm hoặc xóa ô tìm kiếm.'
          : (canManage
            ? 'Tạo sprint đầu tiên để nhóm công việc theo chu kỳ và theo dõi tiến độ.'
            : 'Sprint và công việc sẽ hiển thị tại đây khi được tạo.')"
        :action="canManage && !listSearchEmpty ? 'Tạo sprint' : null"
        @action="openSprint()"
      />

      <SprintListView
        v-else-if="viewMode === 'list'"
        :sprints="sprints"
        :all-tasks="tasks"
        :tasks-by-sprint="filteredTasksBySprint"
        :backlog-tasks="filteredBacklogTasks"
        :sprint-metrics="sprintMetrics"
        :expanded-ids="expandedSprints"
        :project-id="pid"
        :status-options="enums.taskStatus || []"
        :can-manage="canManage"
        :can-contribute="canContribute"
        @toggle-sprint="toggleSprint"
        @open-sprint="openSprint"
        @open-task="openTask"
        @edit-task="openTaskEdit"
        @add-task="({ sprintId }) => openTaskModal(null, 'todo', sprintId)"
        @duplicate-sprint="duplicateSprint"
        @close-sprint="closeSprint"
        @delete-sprint="removeSprint"
        @delete-task="removeTask"
        @reorder-sprints="reorderSprints"
      />

      <EmptyState
        v-else-if="calendarEmpty"
        :icon="calendarSearchEmpty ? 'search' : 'calendar'"
        :title="calendarSearchEmpty ? 'Không tìm thấy task' : 'Chưa có task trên lịch'"
        :description="calendarSearchEmpty
          ? 'Không có task phù hợp từ khóa tìm kiếm.'
          : (canContribute
            ? 'Thêm task có hạn (due date) trong tháng hiện tại để xem trên lịch.'
            : 'Task có hạn trong tháng sẽ hiển thị trên lịch.')"
        :action="canContribute && !calendarSearchEmpty ? 'Thêm task' : null"
        @action="openTaskModal()"
      />

      <SprintCalendarView
        v-else
        :tasks="table.filtered.value"
        @open-task="openTask"
      />
    </div>

    <TaskDetailPanel
      v-if="detailTask"
      :task="detailTask"
      :project-id="pid"
      :project="project"
      :sprints="sprints"
      :employees="employees"
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
      @close="detailTask = null"
      @edit="openTaskEditFromDetail"
      @open-task="openTask"
      @updated="onTaskDetailUpdated"
      @deleted="detailTask = null"
    />

    <SprintFormModal
      :show="sprintModal"
      :project-id="pid"
      :sprint="editingSprint"
      :status-options="enums.sprintStatus || []"
      @close="sprintModal = false"
    />
    <TaskFormModal
      :show="taskModal"
      :project-id="pid"
      :task="editingTask"
      :initial-sprint-id="taskSprintPreset"
      :sprints="sprints"
      :employees="employees"
      :tasks="tasks"
      :status-options="enums.taskStatus || []"
      :priority-options="enums.taskPriority || []"
      :phase-options="enums.taskPhase || []"
      :default-status="taskDefaultStatus"
      @close="taskModal = false"
      @saved="onTaskDetailUpdated"
    />
    <TaskCompleteModal :project-id="pid" />

    <SprintDataModal
      :show="dataModalOpen"
      :initial-tab="dataModalTab"
      :project-id="pid"
      :project-code="project.code"
      :project-name="project.name"
      :sprints="sprints"
      :tasks="tasks"
      :filtered-tasks="table.filtered.value"
      :employees="employees"
      :enums="enums"
      :issues="issues"
      :summary="summary"
      :sprint-by-id="sprintById"
      :can-manage="canManage"
      @close="dataModalOpen = false"
      @imported="dataModalOpen = false"
      @fix="onReconcileFix"
    />
  </div>
</template>
