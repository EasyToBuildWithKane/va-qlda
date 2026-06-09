<script setup>
import { ref, computed, toRef, onMounted } from 'vue';
import { router } from '@inertiajs/vue3';
import AppIcon from '@/Components/AppIcon.vue';
import SprintFormModal from '@/modules/project/components/SprintFormModal.vue';
import TaskFormModal from '@/modules/project/components/TaskFormModal.vue';
import SprintListView from '@/modules/project/components/Sprint/SprintListView.vue';
import SprintCalendarView from '@/modules/project/components/Sprint/SprintCalendarView.vue';
import TaskDetailPanel from '@/modules/project/components/Sprint/TaskDetailPanel.vue';
import SprintDataModal from '@/modules/project/components/Sprint/SprintDataModal.vue';
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
const detailTask = ref(null);
const sprintModal = ref(false);
const editingSprint = ref(null);
const taskModal = ref(false);
const editingTask = ref(null);
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
const openTaskModal = (t = null, status = 'todo', sprintId = null) => {
    editingTask.value = t;
    taskDefaultStatus.value = status;
    if (!t && sprintId) editingTask.value = { sprint_id: sprintId };
    taskModal.value = true;
};
const openTaskEditFromDetail = (t) => {
    detailTask.value = null;
    openTaskModal(t);
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

onMounted(() => {
    const s = new Set(expandedSprints.value);
    props.sprints.slice(0, 2).forEach((sp) => s.add(sp.id));
    expandedSprints.value = s;
});
</script>

<template>
  <div class="flex h-full min-h-0 flex-col bg-slate-50/50 dark:bg-slate-950">
    <!-- Header -->
    <div class="flex shrink-0 flex-wrap items-center justify-between gap-2 border-b border-slate-200/80 bg-white px-4 py-3 dark:border-slate-800 dark:bg-slate-900">
      <div>
        <h2 class="font-display text-lg font-bold text-slate-900 dark:text-slate-50">
          Sprint Workspace
        </h2>
        <p class="text-xs text-slate-500">
          Điều phối sprint, task và tiến độ dự án
        </p>
      </div>
      <button
        v-if="canManage"
        type="button"
        class="btn-primary text-sm"
        @click="openSprint()"
      >
        <AppIcon
          name="add"
          :size="15"
        /> Sprint
      </button>
    </div>

    <!-- Sticky toolbar -->
    <div class="sticky top-0 z-20 shrink-0 border-b border-slate-200/80 bg-white/95 px-3 py-2 backdrop-blur dark:border-slate-800 dark:bg-slate-900/95">
      <div class="flex flex-wrap items-center gap-2">
        <div class="relative min-w-[12rem] flex-1">
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
        @add-task="({ sprintId }) => openTaskModal(null, 'todo', sprintId)"
        @duplicate-sprint="duplicateSprint"
        @close-sprint="closeSprint"
        @delete-sprint="removeSprint"
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
      @close="detailTask = null"
      @edit="openTaskEditFromDetail"
      @open-task="openTask"
      @updated="onTaskDetailUpdated"
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
