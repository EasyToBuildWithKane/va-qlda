<script setup>
import { computed, ref, toRef, onMounted } from 'vue';
import TaskCard from '@/modules/project/components/TaskCard.vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import { date } from '@/composables/useFormat';
import { groupTasksByPhase } from '@/composables/useTaskPhaseGroups';
import { normalizeKeyed, normalizeEntities } from '@/composables/useNormalizeList';
import { useSprintWorkspace } from '@/composables/useSprintWorkspace';
import { countTaskTree, filterRootTasks, getSubtaskStats } from '@/composables/useTaskHierarchy';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    sprints: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] }, // [{value,label,color}]
    canEdit: { type: Boolean, default: false },
});

const emit = defineEmits(['move', 'view', 'add']);

const dragId = ref(null);
const expandedSprints = ref(new Set());
const collapsedPhases = ref(new Set());

const { tasksBySprint, backlogTasks } = useSprintWorkspace(
    toRef(() => props.sprints),
    toRef(() => props.tasks),
    toRef(() => ({})),
);

/** Cột Kanban — khớp TaskStatus::board() + blocked. */
const BOARD_STATUS_ORDER = ['todo', 'in_progress', 'in_review', 'done', 'blocked'];

const boardStatuses = computed(() =>
    BOARD_STATUS_ORDER.map((v) => props.statuses.find((s) => s.value === v)).filter(Boolean),
);

const columnGridStyle = computed(() => ({
    gridTemplateColumns: `repeat(${boardStatuses.value.length}, minmax(0, 1fr))`,
}));

const columnsFor = (taskList) =>
    boardStatuses.value.map((s) => ({
        ...s,
        tasks: filterRootTasks(normalizeEntities(taskList || [])).filter((t) => t.status?.value === s.value),
    }));

const subtaskStats = (task) => getSubtaskStats(task, props.tasks);

const phasesFor = (taskList) => normalizeKeyed(groupTasksByPhase(taskList || []));

const phaseKey = (scopeId, phaseId) => `${scopeId}:phase-${phaseId}`;

const isPhaseOpen = (key) => !collapsedPhases.value.has(key);

const togglePhase = (key) => {
    const s = new Set(collapsedPhases.value);
    if (s.has(key)) s.delete(key);
    else s.add(key);
    collapsedPhases.value = s;
};

const toggleSprint = (id) => {
    const s = new Set(expandedSprints.value);
    if (s.has(id)) s.delete(id);
    else s.add(id);
    expandedSprints.value = s;
};

const onDrop = (statusValue, taskList) => {
    if (dragId.value === null) return;
    const task = taskList.find((t) => t.id === dragId.value) ?? props.tasks.find((t) => t.id === dragId.value);
    if (task && task.status?.value !== statusValue) {
        emit('move', { id: dragId.value, status: statusValue });
    }
    dragId.value = null;
};

const dotClass = (color) => ({
    'bg-slate-400': color === 'slate',
    'bg-sky-500': color === 'sky',
    'bg-violet-500': color === 'violet',
    'bg-emerald-500': color === 'emerald',
    'bg-rose-500': color === 'rose',
});

onMounted(() => {
    const s = new Set(expandedSprints.value);
    props.sprints.slice(0, 2).forEach((sp) => s.add(sp.id));
    expandedSprints.value = s;
});
</script>

<template>
  <div class="h-full space-y-3 overflow-y-auto overflow-x-hidden pr-1">
    <div
      v-for="s in sprints"
      :key="s.id"
      class="overflow-hidden rounded-xl border border-slate-200/80 bg-white shadow-sm dark:border-slate-700 dark:bg-slate-900"
    >
      <button
        type="button"
        class="flex w-full items-center gap-3 px-4 py-3 text-left transition hover:bg-slate-50/80 dark:hover:bg-slate-800/50"
        @click="toggleSprint(s.id)"
      >
        <AppIcon
          :name="expandedSprints.has(s.id) ? 'chevron-down' : 'chevron-right'"
          :size="16"
          class="shrink-0 text-slate-400"
        />
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-center gap-2">
            <span class="font-display font-semibold text-slate-800 dark:text-slate-100">{{ s.name }}</span>
            <Badge
              v-if="s.status"
              :label="s.status.label"
              :color="s.status.color"
            />
            <span class="text-xs text-slate-400">{{ date(s.start_date) }} → {{ date(s.end_date) }}</span>
          </div>
          <p
            v-if="s.goal"
            class="mt-0.5 truncate text-xs text-slate-500"
          >
            {{ s.goal }}
          </p>
        </div>
        <span class="shrink-0 text-xs text-slate-500">{{ countTaskTree(tasksBySprint[s.id] || [], props.tasks) }} task</span>
      </button>

      <div
        v-if="expandedSprints.has(s.id)"
        class="border-t border-slate-100 px-3 py-3 dark:border-slate-800"
      >
        <p
          v-if="!(tasksBySprint[s.id] || []).length"
          class="py-4 text-center text-sm text-slate-400"
        >
          Chưa có task trong sprint này.
        </p>
        <div
          v-else
          class="space-y-3"
        >
          <div
            v-for="ph in phasesFor(tasksBySprint[s.id])"
            :key="ph.key"
            class="overflow-hidden rounded-lg border border-violet-100/80 dark:border-violet-900/40"
          >
            <button
              type="button"
              class="flex w-full items-center gap-2 bg-violet-50/80 px-3 py-2 text-left transition hover:bg-violet-50 dark:bg-violet-950/30 dark:hover:bg-violet-950/50"
              @click="togglePhase(phaseKey(s.id, ph.key))"
            >
              <AppIcon
                :name="isPhaseOpen(phaseKey(s.id, ph.key)) ? 'chevron-down' : 'chevron-right'"
                :size="14"
                class="shrink-0 text-violet-400"
              />
              <span class="min-w-0 flex-1 text-xs font-semibold text-violet-900 dark:text-violet-200">{{ ph.label }}</span>
              <span class="shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-900/50 dark:text-violet-300">
                {{ countTaskTree(ph.tasks, props.tasks) }}
              </span>
            </button>

            <div
              v-if="isPhaseOpen(phaseKey(s.id, ph.key))"
              class="grid min-w-0 gap-2 border-t border-violet-100/80 p-2 dark:border-violet-900/40"
              :style="columnGridStyle"
              @dragover.prevent
            >
              <div
                v-for="col in columnsFor(ph.tasks)"
                :key="col.value"
                class="flex min-w-0 flex-col rounded-lg bg-slate-100/70 dark:bg-slate-800/50"
                @drop="onDrop(col.value, ph.tasks)"
              >
                <div class="flex items-center justify-between gap-1 px-2 py-2">
                  <span class="flex min-w-0 items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200">
                    <span
                      class="h-1.5 w-1.5 shrink-0 rounded-full"
                      :class="dotClass(col.color)"
                    />
                    <span class="truncate">{{ col.label }}</span>
                    <span class="shrink-0 font-normal text-slate-400">{{ col.tasks.length }}</span>
                  </span>
                  <button
                    v-if="canEdit"
                    type="button"
                    class="grid h-6 w-6 shrink-0 place-items-center rounded text-slate-400 hover:bg-white hover:text-slate-600 dark:hover:bg-slate-700"
                    title="Thêm công việc"
                    @click="emit('add', col.value)"
                  >
                    <AppIcon
                      name="add"
                      :size="14"
                    />
                  </button>
                </div>
                <div class="min-w-0 space-y-1.5 px-1.5 pb-2">
                  <TaskCard
                    v-for="task in col.tasks"
                    :key="task.id"
                    :task="task"
                    :subtasks="subtaskStats(task)"
                    :draggable="canEdit"
                    class="!p-2.5"
                    @click="emit('view', task)"
                    @dragstart="dragId = task.id"
                  />
                  <p
                    v-if="col.tasks.length === 0"
                    class="px-1 py-4 text-center text-[10px] text-slate-400"
                  >
                    Trống
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Backlog -->
    <div
      v-if="backlogTasks.length"
      class="overflow-hidden rounded-xl border border-dashed border-slate-300 bg-slate-50/50 dark:border-slate-600 dark:bg-slate-900/50"
    >
      <button
        type="button"
        class="flex w-full items-center gap-3 px-4 py-3 text-left"
        @click="toggleSprint('backlog')"
      >
        <AppIcon
          :name="expandedSprints.has('backlog') ? 'chevron-down' : 'chevron-right'"
          :size="16"
          class="shrink-0 text-slate-400"
        />
        <span class="font-semibold text-slate-600 dark:text-slate-300">Backlog</span>
        <span class="text-xs text-slate-400">{{ backlogTasks.length }} task</span>
      </button>

      <div
        v-if="expandedSprints.has('backlog')"
        class="space-y-3 border-t border-slate-200 px-3 py-3 dark:border-slate-700"
      >
        <div
          v-for="ph in phasesFor(backlogTasks)"
          :key="ph.key"
          class="overflow-hidden rounded-lg border border-violet-100/80 dark:border-violet-900/40"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 bg-violet-50/80 px-3 py-2 text-left transition hover:bg-violet-50 dark:bg-violet-950/30"
            @click="togglePhase(phaseKey('backlog', ph.key))"
          >
            <AppIcon
              :name="isPhaseOpen(phaseKey('backlog', ph.key)) ? 'chevron-down' : 'chevron-right'"
              :size="14"
              class="shrink-0 text-violet-400"
            />
            <span class="flex-1 text-xs font-semibold text-violet-900 dark:text-violet-200">{{ ph.label }}</span>
            <span class="rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-medium text-violet-700 dark:bg-violet-900/50 dark:text-violet-300">
              {{ countTaskTree(ph.tasks, props.tasks) }}
            </span>
          </button>

          <div
            v-if="isPhaseOpen(phaseKey('backlog', ph.key))"
            class="grid min-w-0 gap-2 border-t border-violet-100/80 p-2 dark:border-violet-900/40"
            :style="columnGridStyle"
          >
            <div
              v-for="col in columnsFor(ph.tasks)"
              :key="col.value"
              class="flex min-w-0 flex-col rounded-lg bg-slate-100/70 dark:bg-slate-800/50"
              @dragover.prevent
              @drop="onDrop(col.value, ph.tasks)"
            >
              <div class="flex items-center justify-between gap-1 px-2 py-2">
                <span class="flex min-w-0 items-center gap-1.5 text-xs font-semibold text-slate-700 dark:text-slate-200">
                  <span
                    class="h-1.5 w-1.5 shrink-0 rounded-full"
                    :class="dotClass(col.color)"
                  />
                  <span class="truncate">{{ col.label }}</span>
                  <span class="shrink-0 font-normal text-slate-400">{{ col.tasks.length }}</span>
                </span>
                <button
                  v-if="canEdit"
                  type="button"
                  class="grid h-6 w-6 shrink-0 place-items-center rounded text-slate-400 hover:bg-white hover:text-slate-600"
                  title="Thêm công việc"
                  @click="emit('add', col.value)"
                >
                  <AppIcon
                    name="add"
                    :size="14"
                  />
                </button>
              </div>
              <div class="min-w-0 space-y-1.5 px-1.5 pb-2">
                <TaskCard
                  v-for="task in col.tasks"
                  :key="task.id"
                  :task="task"
                  :subtasks="subtaskStats(task)"
                  :draggable="canEdit"
                  class="!p-2.5"
                  @click="emit('view', task)"
                  @dragstart="dragId = task.id"
                />
                <p
                  v-if="col.tasks.length === 0"
                  class="px-1 py-4 text-center text-[10px] text-slate-400"
                >
                  Trống
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <p
      v-if="!sprints.length && !tasks.length"
      class="py-12 text-center text-sm text-slate-400"
    >
      Chưa có sprint hay công việc. Tạo sprint hoặc công việc để bắt đầu.
    </p>
  </div>
</template>
