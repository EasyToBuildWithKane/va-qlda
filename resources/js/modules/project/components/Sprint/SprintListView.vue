<script setup>
import { ref, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import SprintTaskRows from '@/modules/project/components/Sprint/SprintTaskRows.vue';
import { date } from '@/composables/useFormat';
import { sortSprints } from '@/composables/useSprintSort';
import { groupTasksByPhase } from '@/composables/useTaskPhaseGroups';
import { countTaskTree } from '@/composables/useTaskHierarchy';

const props = defineProps({
    sprints: { type: Array, default: () => [] },
    tasksBySprint: { type: Object, default: () => ({}) },
    backlogTasks: { type: Array, default: () => [] },
    allTasks: { type: Array, default: () => [] },
    sprintMetrics: { type: Function, required: true },
    expandedIds: { type: Set, required: true },
    projectId: { type: Number, required: true },
    statusOptions: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
});

const emit = defineEmits([
    'toggle-sprint',
    'open-sprint',
    'open-task',
    'edit-task',
    'add-task',
    'duplicate-sprint',
    'close-sprint',
    'delete-sprint',
    'reorder-sprints',
]);

const orderedSprints = ref([]);
watch(
    () => props.sprints,
    (list) => {
        orderedSprints.value = sortSprints(list ?? []);
    },
    { immediate: true, deep: true },
);

const dragId = ref(null);
const dropTargetId = ref(null);

const collapsedPhases = ref(new Set());

const phaseKey = (scopeId, phaseId) => `${scopeId}:phase-${phaseId}`;

const isPhaseOpen = (key) => !collapsedPhases.value.has(key);

const togglePhase = (key) => {
    const s = new Set(collapsedPhases.value);
    if (s.has(key)) s.delete(key);
    else s.add(key);
    collapsedPhases.value = s;
};

function formatSprintRange(start, end) {
    const a = start ? date(start) : '—';
    const b = end ? date(end) : '—';
    if (a === '—' && b === '—') return 'Chưa đặt lịch';
    return `${a} – ${b}`;
}

function sprintStatusTopClass(sprint) {
    const v = sprint?.status?.value;
    if (v === 'active') return 'border-t-brand';
    if (v === 'completed') return 'border-t-emerald-500';
    if (v === 'planned') return 'border-t-sky-400';
    return 'border-t-slate-200 dark:border-t-slate-600';
}

function onDragStart(sprintId, event) {
    if (!props.canManage) return;
    dragId.value = sprintId;
    dropTargetId.value = sprintId;
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(sprintId));
}

function onDragOver(sprintId, event) {
    if (!props.canManage || !dragId.value) return;
    event.preventDefault();
    dropTargetId.value = sprintId;
}

function onDragLeave(sprintId) {
    if (dropTargetId.value === sprintId) dropTargetId.value = null;
}

function reorderList(fromId, toId) {
    if (fromId === toId) return;
    const list = [...orderedSprints.value];
    const fromIdx = list.findIndex((s) => s.id === fromId);
    const toIdx = list.findIndex((s) => s.id === toId);
    if (fromIdx < 0 || toIdx < 0) return;
    const [moved] = list.splice(fromIdx, 1);
    list.splice(toIdx, 0, moved);
    orderedSprints.value = list;
    emit('reorder-sprints', list.map((s) => s.id));
}

function onDrop(sprintId) {
    const fromId = dragId.value;
    dragId.value = null;
    dropTargetId.value = null;
    if (!fromId || !props.canManage) return;
    reorderList(fromId, sprintId);
}

function onDragEnd() {
    dragId.value = null;
    dropTargetId.value = null;
}
</script>

<template>
  <div class="space-y-3">
    <p
      v-if="canManage && orderedSprints.length > 1"
      class="text-[11px] text-slate-500 dark:text-slate-400"
    >
      Kéo biểu tượng
      <AppIcon
        name="grip-vertical"
        :size="12"
        class="inline-block align-[-2px] text-slate-400"
      />
      để đổi thứ tự sprint.
    </p>

    <article
      v-for="(s, index) in orderedSprints"
      :key="s.id"
      class="overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-sm transition dark:border-slate-700 dark:bg-slate-900"
      :class="[
        'border-t-[3px]',
        sprintStatusTopClass(s),
        dragId === s.id ? 'opacity-60 ring-2 ring-brand/25' : '',
        dropTargetId === s.id && dragId && dragId !== s.id ? 'ring-2 ring-brand/40' : '',
      ]"
      @dragover="onDragOver(s.id, $event)"
      @dragleave="onDragLeave(s.id)"
      @drop.prevent="onDrop(s.id)"
    >
      <div class="flex items-stretch">
        <div
          v-if="canManage"
          class="flex w-9 shrink-0 flex-col items-center justify-center border-r border-slate-100 bg-slate-50/90 dark:border-slate-800 dark:bg-slate-800/40"
        >
          <button
            type="button"
            class="grid h-full min-h-[3.25rem] w-full cursor-grab place-items-center text-slate-400 transition hover:bg-slate-100 hover:text-slate-600 active:cursor-grabbing dark:hover:bg-slate-700 dark:hover:text-slate-200"
            draggable="true"
            title="Kéo để sắp xếp"
            aria-label="Kéo để sắp xếp sprint"
            @click.stop
            @dragstart="onDragStart(s.id, $event)"
            @dragend="onDragEnd"
          >
            <AppIcon
              name="grip-vertical"
              :size="16"
            />
          </button>
        </div>

        <div class="min-w-0 flex-1">
          <div
            class="group flex cursor-pointer items-start gap-2 border-b border-slate-100/90 px-3 py-3 transition hover:bg-slate-50/80 sm:items-center sm:gap-3 sm:px-4 dark:border-slate-800 dark:hover:bg-slate-800/35"
            @click="emit('toggle-sprint', s.id)"
          >
            <button
              type="button"
              class="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-lg border border-slate-200/80 bg-white text-slate-500 shadow-sm transition group-hover:border-slate-300 sm:mt-0 dark:border-slate-600 dark:bg-slate-800 dark:text-slate-300"
              :aria-expanded="expandedIds.has(s.id)"
              :aria-label="expandedIds.has(s.id) ? 'Thu gọn sprint' : 'Mở sprint'"
              @click.stop="emit('toggle-sprint', s.id)"
            >
              <AppIcon
                :name="expandedIds.has(s.id) ? 'chevron-down' : 'chevron-right'"
                :size="15"
              />
            </button>

            <span
              class="grid h-7 w-7 shrink-0 place-items-center rounded-lg bg-slate-100 text-[11px] font-bold tabular-nums text-slate-600 dark:bg-slate-800 dark:text-slate-300"
              :title="`Thứ tự ${index + 1}`"
            >
              {{ index + 1 }}
            </span>

            <div class="min-w-0 flex-1">
              <div class="flex flex-wrap items-center gap-x-2 gap-y-1">
                <h3 class="text-sm font-semibold leading-snug text-slate-900 dark:text-slate-50">
                  {{ s.name }}
                </h3>
                <Badge
                  v-if="s.status"
                  :label="s.status.label"
                  :color="s.status.color"
                  class="!px-2 !py-px !text-[10px] !font-semibold"
                />
                <span
                  v-if="sprintMetrics(s.id).lateCount"
                  class="inline-flex items-center gap-0.5 rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700 ring-1 ring-rose-200/80 dark:bg-rose-950/40 dark:text-rose-300"
                >
                  <AppIcon
                    name="clock"
                    :size="10"
                  />
                  {{ sprintMetrics(s.id).lateCount }} trễ
                </span>
              </div>
              <p class="mt-1.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[11px] text-slate-500 dark:text-slate-400">
                <span class="inline-flex items-center gap-1 rounded-md bg-slate-100/80 px-1.5 py-0.5 tabular-nums dark:bg-slate-800/80">
                  <AppIcon
                    name="calendar"
                    :size="11"
                    class="shrink-0 text-slate-400"
                  />
                  {{ formatSprintRange(s.start_date, s.end_date) }}
                </span>
                <span
                  class="hidden text-slate-300 sm:inline"
                  aria-hidden="true"
                >·</span>
                <span>{{ sprintMetrics(s.id).taskCount }} công việc</span>
                <span
                  class="hidden text-slate-300 sm:inline"
                  aria-hidden="true"
                >·</span>
                <span class="tabular-nums">Vel {{ sprintMetrics(s.id).velocity }}h</span>
              </p>
              <p
                v-if="sprintMetrics(s.id).doneCount"
                class="mt-1 flex flex-wrap gap-x-2 gap-y-0.5 text-[10px] text-slate-500 dark:text-slate-400"
              >
                <span class="tabular-nums">Kế hoạch {{ sprintMetrics(s.id).capacity }}h</span>
                <span aria-hidden="true">·</span>
                <span class="tabular-nums">Thực tế {{ sprintMetrics(s.id).totalActualHours }}h</span>
                <span
                  v-if="sprintMetrics(s.id).earlyCount"
                  class="font-medium text-emerald-600 dark:text-emerald-400"
                >Sớm {{ sprintMetrics(s.id).earlyCount }}</span>
                <span
                  v-if="sprintMetrics(s.id).onPlanCount"
                  class="font-medium text-amber-600 dark:text-amber-400"
                >Đúng {{ sprintMetrics(s.id).onPlanCount }}</span>
                <span
                  v-if="sprintMetrics(s.id).overSlaCount"
                  class="font-medium text-rose-600 dark:text-rose-400"
                >Vượt SLA {{ sprintMetrics(s.id).overSlaCount }}</span>
                <span
                  v-if="sprintMetrics(s.id).slaComplianceRate != null"
                  class="font-semibold text-slate-600 dark:text-slate-300"
                >SLA {{ sprintMetrics(s.id).slaComplianceRate }}%</span>
              </p>
              <p
                v-if="s.goal"
                class="mt-1.5 line-clamp-2 text-[11px] leading-relaxed text-slate-500 dark:text-slate-400"
                :title="s.goal"
              >
                {{ s.goal }}
              </p>
            </div>

            <div class="flex shrink-0 flex-col items-end gap-1 sm:min-w-[6.5rem]">
              <span class="text-[11px] font-semibold tabular-nums text-slate-600 dark:text-slate-300">
                {{ sprintMetrics(s.id).progress }}%
              </span>
              <div class="w-24 sm:w-28">
                <ProgressBar
                  :value="sprintMetrics(s.id).progress"
                  :show-label="false"
                />
              </div>
            </div>

            <div
              v-if="canManage"
              class="flex shrink-0 gap-0.5 rounded-lg border border-slate-200/80 bg-white p-0.5 shadow-sm dark:border-slate-600 dark:bg-slate-800"
              @click.stop
            >
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"
                title="Sửa sprint"
                @click="emit('open-sprint', s)"
              >
                <AppIcon
                  name="edit"
                  :size="14"
                />
              </button>
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"
                title="Nhân bản"
                @click="emit('duplicate-sprint', s)"
              >
                <AppIcon
                  name="copy"
                  :size="14"
                />
              </button>
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700 dark:hover:bg-slate-700"
                title="Đóng sprint"
                @click="emit('close-sprint', s)"
              >
                <AppIcon
                  name="check"
                  :size="14"
                />
              </button>
              <button
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                title="Xoá sprint"
                @click="emit('delete-sprint', s)"
              >
                <AppIcon
                  name="delete"
                  :size="14"
                />
              </button>
            </div>
          </div>

          <div
            v-if="expandedIds.has(s.id)"
            class="bg-slate-50/40 px-1 py-2 dark:bg-slate-950/30"
          >
            <div
              v-if="!(tasksBySprint[s.id] || []).length"
              class="px-3 py-4 text-center text-sm text-slate-400"
            >
              Chưa có công việc.
              <button
                v-if="canManage"
                type="button"
                class="text-brand hover:underline"
                @click="emit('add-task', { sprintId: s.id })"
              >
                Thêm công việc
              </button>
            </div>
            <div
              v-else
              class="space-y-1"
            >
              <div
                v-for="ph in groupTasksByPhase(tasksBySprint[s.id])"
                :key="ph.key"
                class="overflow-hidden rounded-lg border border-violet-100/80 bg-white dark:border-violet-900/40 dark:bg-slate-900"
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
                    {{ countTaskTree(ph.tasks, allTasks.length ? allTasks : tasksBySprint[s.id]) }}
                  </span>
                </button>
                <div
                  v-if="isPhaseOpen(phaseKey(s.id, ph.key))"
                  class="border-t border-violet-100/80 dark:border-violet-900/40"
                >
                  <SprintTaskRows
                    :tasks="ph.tasks"
                    :all-tasks="allTasks"
                    :project-id="projectId"
                    :status-options="statusOptions"
                    :can-contribute="canContribute"
                    @open-task="emit('open-task', $event)"
                    @edit-task="emit('edit-task', $event)"
                  />
                </div>
              </div>
              <div
                v-if="canManage"
                class="px-2 pb-1"
              >
                <button
                  type="button"
                  class="text-xs text-brand hover:underline"
                  @click="emit('add-task', { sprintId: s.id })"
                >
                  + Thêm công việc
                </button>
              </div>
            </div>
          </div>
        </div>
      </div>
    </article>

    <div
      v-if="backlogTasks.length"
      class="overflow-hidden rounded-xl border border-dashed border-slate-300 bg-slate-50/50 dark:border-slate-600 dark:bg-slate-900/50"
    >
      <div
        class="flex cursor-pointer items-center gap-3 border-b border-slate-200/80 px-3 py-3 sm:px-4 dark:border-slate-700"
        @click="emit('toggle-sprint', 'backlog')"
      >
        <AppIcon
          :name="expandedIds.has('backlog') ? 'chevron-down' : 'chevron-right'"
          :size="15"
          class="shrink-0 text-slate-400"
        />
        <div class="min-w-0 flex-1">
          <p class="text-sm font-semibold text-slate-700 dark:text-slate-200">
            Backlog
          </p>
          <p class="mt-0.5 text-[11px] text-slate-500">
            {{ backlogTasks.length }} công việc chưa gán sprint
          </p>
        </div>
      </div>
      <div
        v-if="expandedIds.has('backlog')"
        class="space-y-1 px-1 py-2"
      >
        <div
          v-for="ph in groupTasksByPhase(backlogTasks)"
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
              {{ countTaskTree(ph.tasks, allTasks.length ? allTasks : backlogTasks) }}
            </span>
          </button>
          <div
            v-if="isPhaseOpen(phaseKey('backlog', ph.key))"
            class="border-t border-violet-100/80 dark:border-violet-900/40"
          >
            <SprintTaskRows
              :tasks="ph.tasks"
              :all-tasks="allTasks"
              :project-id="projectId"
              :status-options="statusOptions"
              :can-contribute="canContribute"
              compact
              @open-task="emit('open-task', $event)"
              @edit-task="emit('edit-task', $event)"
            />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
