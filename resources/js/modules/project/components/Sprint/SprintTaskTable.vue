<script setup>
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { useSprintTaskStatusPatch } from '@/composables/useSprintTaskStatusPatch';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import { date } from '@/composables/useFormat';
import { getAssignees } from '@/composables/useSprintFilters';
import {
    getTaskSlaState,
    getTaskSlaToneClass,
    isTaskDateOverdue,
    isTaskOverdue,
} from '@/composables/useTaskTimeliness';
import { taskProgressFromStatus } from '@/shared/utils/taskProgress';

const props = defineProps({
    table: { type: Object, required: true },
    visibleColumns: { type: Array, required: true },
    sprintById: { type: Map, default: () => new Map() },
    statusOptions: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
    projectId: { type: Number, required: true },
});

const emit = defineEmits(['open-task', 'quick-create']);

const {
    paginated,
    selected,
    page,
    pageSize,
    pageCount,
    displayRows,
    toggleSort,
    toggleSelect,
    toggleSelectAll,
} = props.table;

const colVisible = (key) => props.visibleColumns.includes(key);

const allPageSelected = computed(() => {
    const ids = paginated.value.map((r) => r.task?.id ?? r.id);
    return ids.length > 0 && ids.every((id) => selected.value.has(id));
});

const prevPage = () => { page.value -= 1; };
const nextPage = () => { page.value += 1; };

const titlePad = (depth) => ({ paddingLeft: `${8 + (depth || 0) * 20}px` });

const { patchTaskStatus } = useSprintTaskStatusPatch(props.projectId, props.statusOptions);

const patchField = (row, payload) => {
    if (payload.status && Object.keys(payload).length === 1) {
        patchTaskStatus(row, payload.status);
        return;
    }
    router.patch(`/projects/${props.projectId}/tasks/${row.id}`, payload, {
        preserveScroll: true,
        only: ['tasks'],
    });
};

const canEditStatusFor = (row) => {
    if (!props.canContribute) return false;
    return row?.can_change_status !== false;
};

const statusDot = {
    slate: 'bg-slate-400', sky: 'bg-sky-500', violet: 'bg-violet-500',
    emerald: 'bg-emerald-500', rose: 'bg-rose-500', amber: 'bg-amber-500',
};
</script>

<template>
  <div class="overflow-x-auto rounded-xl border border-slate-200/80 bg-white dark:border-slate-700 dark:bg-slate-900">
    <div
      v-if="selected.value.size && canManage"
      class="flex flex-wrap items-center gap-2 border-b border-brand/20 bg-brand/5 px-3 py-2"
    >
      <span class="text-xs font-semibold text-brand">{{ selected.value.size }} đã chọn</span>
      <slot name="bulk-actions" />
    </div>
    <table class="w-full min-w-[900px] border-separate border-spacing-0 text-sm">
      <thead>
        <tr class="text-left text-[10px] font-semibold uppercase tracking-wider text-slate-500">
          <th
            v-if="canManage"
            class="sticky top-0 z-10 w-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
          >
            <input
              type="checkbox"
              :checked="allPageSelected"
              class="rounded"
              @change="toggleSelectAll(paginated.value.map((r) => r.task?.id ?? r.id))"
            >
          </th>
          <th
            class="sticky top-0 z-10 cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
            @click="toggleSort('id')"
          >
            ID
          </th>
          <th
            class="sticky top-0 z-10 min-w-[14rem] cursor-pointer border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
            @click="toggleSort('title')"
          >
            Công việc
          </th>
          <th
            v-if="colVisible('sprint')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
            @click="toggleSort('sprint')"
          >
            Sprint
          </th>
          <th
            v-if="colVisible('status')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
            @click="toggleSort('status')"
          >
            Trạng thái
          </th>
          <th
            v-if="colVisible('priority')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
          >
            Ưu tiên
          </th>
          <th
            v-if="colVisible('phase')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
          >
            Giai đoạn
          </th>
          <th
            v-if="colVisible('assignee')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
          >
            Người làm
          </th>
          <th
            v-if="colVisible('reviewer')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
          >
            Duyệt
          </th>
          <th
            v-if="colVisible('start_date')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
            @click="toggleSort('start_date')"
          >
            Bắt đầu
          </th>
          <th
            v-if="colVisible('due_date')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
            @click="toggleSort('due_date')"
          >
            Hạn
          </th>
          <th
            v-if="colVisible('estimate_hours')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
            @click="toggleSort('estimate_hours')"
          >
            Ước tính
          </th>
          <th
            v-if="colVisible('actual_hours')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
            @click="toggleSort('actual_hours')"
          >
            Thực tế
          </th>
          <th
            v-if="colVisible('sla')"
            class="sticky top-0 z-10 min-w-[7rem] border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
          >
            SLA
          </th>
          <th
            v-if="colVisible('progress')"
            class="sticky top-0 z-10 border-b border-slate-200 bg-slate-50 px-2 py-2 dark:bg-slate-800"
          >
            Tiến độ
          </th>
          <th class="sticky top-0 z-10 w-10 border-b border-slate-200 bg-slate-50 dark:bg-slate-800" />
        </tr>
      </thead>
      <tbody v-if="!paginated.value.length">
        <tr>
          <td
            :colspan="12"
            class="px-4 py-16 text-center text-slate-400"
          >
            <AppIcon
              name="task"
              :size="32"
              class="mx-auto mb-2 opacity-40"
            />
            <p>Không có task phù hợp bộ lọc.</p>
            <button
              v-if="canManage"
              type="button"
              class="btn-primary mt-3 text-sm"
              @click="emit('quick-create')"
            >
              <AppIcon
                name="add"
                :size="14"
              /> Tạo task
            </button>
          </td>
        </tr>
      </tbody>
      <tbody v-else>
        <tr
          v-for="{ task: row, depth, isSubtask } in paginated.value"
          :key="row.id"
          class="cursor-pointer transition hover:bg-slate-50/80 dark:hover:bg-slate-800/40"
          :class="[
            isTaskOverdue(row) ? 'bg-rose-50/20' : '',
            isSubtask ? 'bg-slate-50/30 dark:bg-slate-900/20' : '',
          ]"
          @click="emit('open-task', row)"
        >
          <td
            v-if="canManage"
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
            @click.stop
          >
            <input
              type="checkbox"
              :checked="selected.value.has(row.id)"
              class="rounded"
              @change="toggleSelect(row.id)"
            >
          </td>
          <td class="border-b border-slate-100 px-2 py-2 font-mono text-xs text-slate-500 dark:border-slate-800">
            <span
              v-if="isSubtask"
              class="text-slate-300"
            >↳</span>
            {{ row.id }}
          </td>
          <td
            class="border-b border-slate-100 py-2 pr-2 dark:border-slate-800"
            :style="titlePad(depth)"
          >
            <span
              class="line-clamp-2"
              :class="isSubtask ? 'text-sm text-slate-600 dark:text-slate-300' : 'font-medium text-slate-800 dark:text-slate-100'"
            >
              {{ row.title }}
            </span>
          </td>
          <td
            v-if="colVisible('sprint')"
            class="border-b border-slate-100 px-2 py-2 text-xs dark:border-slate-800"
          >
            {{ row.sprint_id ? sprintById.get(row.sprint_id)?.name : 'Backlog' }}
          </td>
          <td
            v-if="colVisible('status')"
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
            @click.stop
          >
            <select
              v-if="canEditStatusFor(row)"
              :value="row.status?.value"
              class="h-7 rounded-lg border border-slate-200 text-[10px] font-semibold dark:border-slate-600 dark:bg-slate-800"
              @change="patchField(row, { status: $event.target.value })"
            >
              <option
                v-for="o in statusOptions"
                :key="o.value"
                :value="o.value"
              >
                {{ o.label }}
              </option>
            </select>
            <span
              v-else
              class="flex items-center gap-1 text-xs"
            >
              <span
                class="h-2 w-2 rounded-full"
                :class="statusDot[row.status?.color] || 'bg-slate-400'"
              />
              {{ row.status?.label }}
            </span>
          </td>
          <td
            v-if="colVisible('priority')"
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
          >
            <Badge
              :label="row.priority?.label"
              :color="row.priority?.color"
            />
          </td>
          <td
            v-if="colVisible('phase')"
            class="border-b border-slate-100 px-2 py-2 text-xs dark:border-slate-800"
          >
            {{ row.phase?.label || '—' }}
          </td>
          <td
            v-if="colVisible('assignee')"
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
          >
            <div
              v-if="getAssignees(row).length"
              class="flex -space-x-1"
            >
              <Avatar
                v-for="a in getAssignees(row).slice(0, 2)"
                :key="a.id"
                :name="a.name"
                :src="a.avatar_path"
                :size="22"
              />
            </div>
            <span
              v-else
              class="text-slate-300"
            >—</span>
          </td>
          <td
            v-if="colVisible('reviewer')"
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
          >
            <Avatar
              v-if="row.reviewer"
              :name="row.reviewer.name"
              :src="row.reviewer.avatar_path"
              :size="22"
            />
            <span
              v-else
              class="text-slate-300"
            >—</span>
          </td>
          <td
            v-if="colVisible('start_date')"
            class="border-b border-slate-100 px-2 py-2 text-xs text-slate-500 dark:border-slate-800"
          >
            {{ date(row.start_date) || '—' }}
          </td>
          <td
            v-if="colVisible('due_date')"
            class="border-b border-slate-100 px-2 py-2 text-xs dark:border-slate-800"
            :class="isTaskDateOverdue(row) ? 'font-semibold text-rose-600' : 'text-slate-500'"
          >
            {{ date(row.due_date) || '—' }}
          </td>
          <td
            v-if="colVisible('estimate_hours')"
            class="border-b border-slate-100 px-2 py-2 text-xs dark:border-slate-800"
          >
            <span v-if="row.estimate_hours != null">{{ row.estimate_hours }}h</span>
            <span
              v-else
              class="text-slate-300"
            >—</span>
          </td>
          <td
            v-if="colVisible('actual_hours')"
            class="border-b border-slate-100 px-2 py-2 text-xs font-medium dark:border-slate-800"
            :class="row.actual_hours != null ? 'text-emerald-700 dark:text-emerald-400' : ''"
          >
            <span v-if="row.actual_hours != null">{{ row.actual_hours }}h</span>
            <span
              v-else
              class="font-normal text-slate-300"
            >—</span>
          </td>
          <td
            v-if="colVisible('sla')"
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
          >
            <span
              class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
              :class="getTaskSlaToneClass(getTaskSlaState(row).tone)"
              :title="getTaskSlaState(row).detail"
            >
              {{ getTaskSlaState(row).label }}
            </span>
            <p class="mt-0.5 line-clamp-2 text-[10px] text-slate-500">
              {{ getTaskSlaState(row).detail }}
            </p>
          </td>
          <td
            v-if="colVisible('progress')"
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
          >
            <div class="flex w-[4.5rem] items-center gap-1">
              <ProgressBar
                :value="taskProgressFromStatus(row.status)"
                :show-label="false"
                class="flex-1"
              />
              <span class="w-8 shrink-0 text-right text-[10px] font-medium text-slate-500">{{ taskProgressFromStatus(row.status) }}%</span>
            </div>
          </td>
          <td
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
            @click.stop
          >
            <AppIcon
              name="chevron-right"
              :size="14"
              class="text-slate-300"
            />
          </td>
        </tr>
      </tbody>
    </table>
    <div
      v-if="pageCount.value > 1"
      class="flex items-center justify-between border-t border-slate-100 px-3 py-2 text-xs dark:border-slate-800"
    >
      <span class="text-slate-500">{{ displayRows.value.length }} kết quả</span>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="btn-ghost text-xs"
          :disabled="page.value <= 1"
          @click="prevPage"
        >
          Trước
        </button>
        <span>{{ page.value }} / {{ pageCount.value }}</span>
        <button
          type="button"
          class="btn-ghost text-xs"
          :disabled="page.value >= pageCount.value"
          @click="nextPage"
        >
          Sau
        </button>
        <select
          v-model.number="pageSize.value"
          class="input py-0.5 text-xs"
        >
          <option :value="10">
            10
          </option>
          <option :value="25">
            25
          </option>
          <option :value="50">
            50
          </option>
          <option :value="100">
            100
          </option>
        </select>
      </div>
    </div>
  </div>
</template>
