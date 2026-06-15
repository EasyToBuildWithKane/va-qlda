<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import Badge from '@/shared/ui/Badge.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import { date } from '@/composables/useFormat';
import { getAssignees } from '@/composables/useSprintFilters';
import { useSprintTaskStatusPatch } from '@/composables/useSprintTaskStatusPatch';
import {
    getTaskSlaState,
    getTaskSlaToneClass,
    isTaskDateOverdue,
    isTaskOverdue,
} from '@/composables/useTaskTimeliness';
import {
    buildParentBlocks,
    getTaskSchedule,
    isSubtask,
} from '@/composables/useTaskHierarchy';
import { taskProgressFromStatus } from '@/shared/utils/taskProgress';
import { getTaskCompletionBadge } from '@/composables/useTaskCompletion';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    allTasks: { type: Array, default: () => [] },
    projectId: { type: Number, required: true },
    statusOptions: { type: Array, default: () => [] },
    canContribute: { type: Boolean, default: false },
    canManage: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});

const { patchTaskStatus } = useSprintTaskStatusPatch(props.projectId, props.statusOptions);
const emit = defineEmits(['open-task', 'edit-task', 'delete-task']);

const colCount = computed(() => (props.compact ? 10 : 11));
const taskPool = computed(() => (props.allTasks?.length ? props.allTasks : props.tasks));

const parentBlocks = computed(() => buildParentBlocks(props.tasks, taskPool.value));

/** Mặc định thu gọn con — bấm mũi tên trên dòng cha để mở. */
const expandedParents = ref(new Set());

const isChildrenOpen = (parentId) => expandedParents.value.has(parentId);

const toggleChildren = (parentId, e) => {
    e?.stopPropagation();
    const s = new Set(expandedParents.value);
    if (s.has(parentId)) s.delete(parentId);
    else s.add(parentId);
    expandedParents.value = s;
};

const visibleRows = computed(() => {
    const rows = [];
    for (const { parent, children } of parentBlocks.value) {
        rows.push({
            task: parent,
            isSubtask: false,
            parent: null,
            hasChildren: children.length > 0,
            childCount: children.length,
            childrenOpen: isChildrenOpen(parent.id),
        });
        if (children.length && isChildrenOpen(parent.id)) {
            children.forEach((child) => {
                rows.push({ task: child, isSubtask: true, parent, hasChildren: false });
            });
        }
    }
    return rows;
});

const statusDot = {
    slate: 'bg-slate-400',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
};

const assigneeOverflowTitle = (row) => {
    const list = getAssignees(row);
    if (list.length <= 3) return '';
    return list.slice(3).map((a) => a.name).join(', ');
};

const canEditStatusFor = (task) => {
    if (!props.canContribute) return false;
    return task?.can_change_status !== false;
};

const onStatusChange = (task, event) => {
    const prev = task?.status?.value;
    const next = event.target.value;
    if (next === prev) return;
    patchTaskStatus(task, next, {
        onCancel: () => { event.target.value = prev; },
        onError: () => { event.target.value = prev; },
    });
};

const scheduleFor = (row, parent) => getTaskSchedule(row, taskPool.value, parent);

const slaFor = (row, _parent) => {
    if (isSubtask(row)) return { label: '—', detail: 'Theo công việc cha', tone: 'slate' };
    return getTaskSlaState(row);
};

const isRowOverdue = (row, parent) => {
    if (isSubtask(row)) {
        const sch = scheduleFor(row, parent);
        return isTaskDateOverdue({ ...row, start_date: sch.start_date, due_date: sch.due_date });
    }
    return isTaskOverdue(row);
};

const isDateOverdue = (row, parent) => {
    const sch = scheduleFor(row, parent);
    return isTaskDateOverdue({ ...row, start_date: sch.start_date, due_date: sch.due_date });
};
</script>

<template>
  <div class="overflow-x-auto">
    <table class="w-full min-w-[1020px] border-separate border-spacing-0 text-sm">
      <thead>
        <tr class="text-left text-[10px] font-semibold uppercase tracking-wider text-slate-400">
          <th class="w-9 border-b border-slate-100 px-1 py-1.5 dark:border-slate-800" />
          <th class="w-11 border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            ID
          </th>
          <th class="min-w-[18rem] border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            Công việc
          </th>
          <th class="w-[4.5rem] border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            Người làm
          </th>
          <th class="w-[6.5rem] border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            Trạng thái
          </th>
          <th
            v-if="!compact"
            class="w-20 border-b border-slate-100 px-2 py-1.5 dark:border-slate-800"
          >
            Ưu tiên
          </th>
          <th class="w-[4.5rem] border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            Bắt đầu
          </th>
          <th class="w-[4.5rem] border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            Hạn
          </th>
          <th class="w-14 border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            Giờ ƯT
          </th>
          <th class="min-w-[7.5rem] border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            SLA
          </th>
          <th class="w-[4.5rem] border-b border-slate-100 px-2 py-1.5 dark:border-slate-800">
            Tiến độ
          </th>
          <th class="w-[4.5rem] border-b border-slate-100 dark:border-slate-800" />
        </tr>
      </thead>
      <tbody v-if="!visibleRows.length">
        <tr>
          <td
            :colspan="colCount + 1"
            class="py-3 text-center text-xs text-slate-400"
          >
            Chưa có task.
          </td>
        </tr>
      </tbody>
      <tbody v-else>
        <tr
          v-for="entry in visibleRows"
          :key="entry.task.id"
          class="cursor-pointer transition hover:bg-slate-50/90 dark:hover:bg-slate-800/40"
          :class="[
            isRowOverdue(entry.task, entry.parent) ? 'bg-rose-50/30 dark:bg-rose-950/15' : '',
            entry.isSubtask ? 'bg-slate-50/50 dark:bg-slate-900/25' : '',
          ]"
          @click="emit('open-task', entry.task)"
        >
          <td
            class="border-b border-slate-100 px-1 py-2 dark:border-slate-800"
            @click.stop
          >
            <button
              v-if="entry.hasChildren"
              type="button"
              class="grid h-6 w-6 place-items-center rounded text-slate-400 hover:bg-slate-100 hover:text-slate-600 dark:hover:bg-slate-800"
              :title="entry.childrenOpen ? 'Thu gọn công việc con' : 'Mở công việc con'"
              @click="toggleChildren(entry.task.id, $event)"
            >
              <AppIcon
                :name="entry.childrenOpen ? 'chevron-down' : 'chevron-right'"
                :size="14"
              />
            </button>
          </td>
          <td
            class="border-b border-slate-100 px-2 py-2 font-mono text-xs dark:border-slate-800"
            :class="entry.isSubtask ? 'text-slate-400' : 'text-slate-500'"
          >
            <span
              v-if="entry.isSubtask"
              class="text-slate-300"
            >↳</span>
            #{{ entry.task.id }}
          </td>
          <td
            class="border-b border-slate-100 py-2 pr-2 dark:border-slate-800"
            :class="entry.isSubtask ? 'pl-6' : 'pl-2'"
          >
            <div class="flex min-w-0 items-center gap-1.5">
              <span
                class="line-clamp-2 leading-snug"
                :class="entry.isSubtask
                  ? 'text-xs text-slate-600 dark:text-slate-300'
                  : 'font-medium text-slate-800 dark:text-slate-100'"
              >
                {{ entry.task.title }}
              </span>
              <span
                v-if="entry.hasChildren && !entry.childrenOpen"
                class="shrink-0 rounded-full bg-slate-200 px-1.5 py-0.5 text-[10px] font-medium text-slate-600 dark:bg-slate-700 dark:text-slate-300"
              >
                {{ entry.childCount }} con
              </span>
              <span
                v-if="!entry.isSubtask && getTaskCompletionBadge(entry.task)"
                class="shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-semibold"
                :class="getTaskSlaToneClass(getTaskCompletionBadge(entry.task).tone)"
                :title="getTaskCompletionBadge(entry.task).detail"
              >
                {{ getTaskCompletionBadge(entry.task).label }}
              </span>
            </div>
          </td>
          <td class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
            <template v-if="!entry.isSubtask">
              <div
                v-if="getAssignees(entry.task).length"
                class="flex items-center -space-x-1"
              >
                <Avatar
                  v-for="a in getAssignees(entry.task).slice(0, 3)"
                  :key="a.id"
                  :name="a.name"
                  :src="a.avatar_path"
                  :size="22"
                />
                <span
                  v-if="getAssignees(entry.task).length > 3"
                  class="inline-grid h-[22px] min-w-[22px] place-items-center rounded-full bg-slate-100 px-1 text-[10px] font-semibold text-slate-600 ring-2 ring-white dark:bg-slate-700 dark:text-slate-200 dark:ring-slate-900"
                  :title="assigneeOverflowTitle(entry.task)"
                >
                  +{{ getAssignees(entry.task).length - 3 }}
                </span>
              </div>
              <span
                v-else
                class="text-slate-300"
              >—</span>
            </template>
            <span
              v-else
              class="text-[10px] text-slate-400"
            >—</span>
          </td>
          <td
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
            @click.stop
          >
            <select
              v-if="canEditStatusFor(entry.task)"
              :value="entry.task.status?.value"
              class="h-7 w-full max-w-[6.5rem] rounded-lg border border-slate-200 text-[10px] font-semibold dark:border-slate-600 dark:bg-slate-800"
              @change="onStatusChange(entry.task, $event)"
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
              v-else-if="entry.task.status?.label"
              class="flex items-center gap-1 text-xs"
            >
              <span
                class="h-2 w-2 shrink-0 rounded-full"
                :class="statusDot[entry.task.status?.color] || 'bg-slate-400'"
              />
              {{ entry.task.status?.label }}
            </span>
          </td>
          <td
            v-if="!compact"
            class="border-b border-slate-100 px-2 py-2 dark:border-slate-800"
          >
            <Badge
              v-if="!entry.isSubtask"
              :label="entry.task.priority?.label"
              :color="entry.task.priority?.color"
            />
            <span
              v-else
              class="text-[10px] text-slate-400"
            >—</span>
          </td>
          <td
            class="border-b border-slate-100 px-2 py-2 text-xs dark:border-slate-800"
            :class="entry.isSubtask ? 'text-slate-400 italic' : 'text-slate-500'"
          >
            <template v-if="entry.isSubtask">
              Theo cha
            </template>
            <template v-else>
              {{ date(scheduleFor(entry.task).start_date) || '—' }}
            </template>
          </td>
          <td
            class="border-b border-slate-100 px-2 py-2 text-xs dark:border-slate-800"
            :class="[
              entry.isSubtask ? 'text-slate-400 italic' : '',
              !entry.isSubtask && isDateOverdue(entry.task) ? 'font-semibold text-rose-600' : 'text-slate-500',
            ]"
          >
            <template v-if="entry.isSubtask">
              {{ date(scheduleFor(entry.task, entry.parent).due_date) || '—' }}
            </template>
            <template v-else>
              {{ date(scheduleFor(entry.task).due_date) || '—' }}
            </template>
          </td>
          <td
            class="border-b border-slate-100 px-2 py-2 text-xs font-medium dark:border-slate-800"
            :class="entry.isSubtask ? 'text-brand' : 'text-slate-500'"
          >
            <span v-if="entry.task.estimate_hours != null">{{ entry.task.estimate_hours }}h</span>
            <span
              v-else
              class="font-normal text-slate-300"
            >—</span>
          </td>
          <td class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
            <template v-if="!entry.isSubtask">
              <span
                class="inline-flex rounded-full px-2 py-0.5 text-[10px] font-semibold"
                :class="getTaskSlaToneClass(slaFor(entry.task).tone)"
                :title="slaFor(entry.task).detail"
              >
                {{ slaFor(entry.task).label }}
              </span>
            </template>
            <span
              v-else
              class="text-[10px] text-slate-400"
            >Theo cha</span>
          </td>
          <td class="border-b border-slate-100 px-2 py-2 dark:border-slate-800">
            <div
              v-if="!entry.isSubtask"
              class="flex items-center gap-1"
            >
              <ProgressBar
                :value="taskProgressFromStatus(entry.task.status)"
                :show-label="false"
                class="min-w-[2.5rem] flex-1"
              />
              <span class="w-8 shrink-0 text-right text-[10px] font-medium text-slate-500">{{ taskProgressFromStatus(entry.task.status) }}%</span>
            </div>
            <span
              v-else
              class="text-[10px] text-slate-400"
            >{{ taskProgressFromStatus(entry.task.status) }}%</span>
          </td>
          <td
            class="border-b border-slate-100 px-1 py-2 dark:border-slate-800"
            @click.stop
          >
            <div class="flex items-center justify-end gap-0.5">
              <button
                v-if="canContribute"
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 transition hover:bg-brand/10 hover:text-brand"
                title="Chỉnh sửa công việc"
                @click="emit('edit-task', entry.task)"
              >
                <AppIcon
                  name="edit"
                  :size="14"
                />
              </button>
              <button
                v-if="canManage"
                type="button"
                class="grid h-7 w-7 place-items-center rounded-md text-slate-400 transition hover:bg-rose-50 hover:text-rose-600 dark:hover:bg-rose-950/40"
                title="Xoá công việc"
                @click="emit('delete-task', entry.task)"
              >
                <AppIcon
                  name="delete"
                  :size="14"
                />
              </button>
              <AppIcon
                v-if="!canContribute && !canManage"
                name="chevron-right"
                :size="14"
                class="text-slate-300"
              />
            </div>
          </td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
