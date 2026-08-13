<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import MyWorkTaskListRow from './MyWorkTaskListRow.vue';
import { MY_WORK_TABLE_COLUMNS } from '../config/columns';

const props = defineProps({
    groups: { type: Array, default: () => [] },
    openState: { type: Object, required: true },
    isColVisible: { type: Function, required: true },
    mode: { type: String, default: 'self' },
    statusOptions: { type: Array, default: () => [] },
    sortKey: { type: String, default: '' },
    sortDir: { type: String, default: 'asc' },
});

const emit = defineEmits(['toggle-group', 'sort', 'change-status', 'log-work', 'open']);

const SORTABLE = new Set([
    'title', 'project', 'status', 'priority', 'due_date', 'progress',
    'logged_today', 'estimate', 'sprint', 'phase', 'source', 'start_date',
    'story_points', 'actual_hours', 'milestone', 'sla', 'epic', 'parent',
    'assignee', 'reporter', 'reviewer',
]);

const colCount = computed(
    () => 2 + MY_WORK_TABLE_COLUMNS.filter((c) => props.isColVisible(c.key)).length,
);

function isOpen(key) {
    return props.openState[key] !== false;
}

function sortMark(key) {
    if (props.sortKey !== key) return '';
    return props.sortDir === 'desc' ? ' ↓' : ' ↑';
}

function onSort(key) {
    if (!SORTABLE.has(key)) return;
    emit('sort', key);
}

function headerClass(key) {
    return SORTABLE.has(key)
        ? 'cursor-pointer select-none hover:text-slate-700 dark:hover:text-slate-200'
        : '';
}

function showCol(key) {
    return props.isColVisible(key);
}
</script>

<template>
  <div class="my-work-table-wrap overflow-x-auto">
    <table class="w-full min-w-[48rem] border-collapse text-sm">
      <thead class="sticky top-0 z-[2] border-b border-slate-200 bg-slate-50/95 text-left text-[11px] font-semibold uppercase tracking-wide text-slate-500 backdrop-blur-sm dark:border-slate-700 dark:bg-slate-800/90">
        <tr>
          <th
            class="sticky left-0 z-[3] min-w-[16rem] bg-slate-50/95 px-3 py-2.5 dark:bg-slate-800/90 sm:px-4"
            :class="headerClass('title')"
            @click="onSort('title')"
          >
            Công việc{{ sortMark('title') }}
          </th>
          <th
            v-if="showCol('project')"
            class="min-w-[9rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('project')"
            @click="onSort('project')"
          >
            Dự án{{ sortMark('project') }}
          </th>
          <th
            v-if="showCol('status')"
            class="min-w-[8.5rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('status')"
            @click="onSort('status')"
          >
            Trạng thái{{ sortMark('status') }}
          </th>
          <th
            v-if="showCol('priority')"
            class="min-w-[6.5rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('priority')"
            @click="onSort('priority')"
          >
            Ưu tiên{{ sortMark('priority') }}
          </th>
          <th
            v-if="showCol('due_date')"
            class="min-w-[7rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('due_date')"
            @click="onSort('due_date')"
          >
            Hạn{{ sortMark('due_date') }}
          </th>
          <th
            v-if="showCol('progress')"
            class="min-w-[8rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('progress')"
            @click="onSort('progress')"
          >
            Tiến độ{{ sortMark('progress') }}
          </th>
          <th
            v-if="showCol('logged_today')"
            class="min-w-[6.5rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('logged_today')"
            @click="onSort('logged_today')"
          >
            Giờ hôm nay{{ sortMark('logged_today') }}
          </th>
          <th
            v-if="showCol('estimate')"
            class="min-w-[5.5rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('estimate')"
            @click="onSort('estimate')"
          >
            Dự kiến{{ sortMark('estimate') }}
          </th>
          <th
            v-if="showCol('sprint')"
            class="min-w-[8rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('sprint')"
            @click="onSort('sprint')"
          >
            Sprint{{ sortMark('sprint') }}
          </th>
          <th
            v-if="showCol('phase')"
            class="min-w-[7rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('phase')"
            @click="onSort('phase')"
          >
            Giai đoạn{{ sortMark('phase') }}
          </th>
          <th
            v-if="showCol('source')"
            class="min-w-[8rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('source')"
            @click="onSort('source')"
          >
            Nguồn{{ sortMark('source') }}
          </th>
          <th
            v-if="showCol('start_date')"
            class="min-w-[7rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('start_date')"
            @click="onSort('start_date')"
          >
            Ngày bắt đầu{{ sortMark('start_date') }}
          </th>
          <th
            v-if="showCol('story_points')"
            class="min-w-[5.5rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('story_points')"
            @click="onSort('story_points')"
          >
            SP{{ sortMark('story_points') }}
          </th>
          <th
            v-if="showCol('actual_hours')"
            class="min-w-[6rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('actual_hours')"
            @click="onSort('actual_hours')"
          >
            Giờ thực tế{{ sortMark('actual_hours') }}
          </th>
          <th
            v-if="showCol('milestone')"
            class="min-w-[4.5rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('milestone')"
            @click="onSort('milestone')"
          >
            Mốc{{ sortMark('milestone') }}
          </th>
          <th
            v-if="showCol('sla')"
            class="min-w-[6.5rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('sla')"
            @click="onSort('sla')"
          >
            SLA{{ sortMark('sla') }}
          </th>
          <th
            v-if="showCol('epic')"
            class="min-w-[8rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('epic')"
            @click="onSort('epic')"
          >
            Epic{{ sortMark('epic') }}
          </th>
          <th
            v-if="showCol('parent')"
            class="min-w-[9rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('parent')"
            @click="onSort('parent')"
          >
            Việc cha{{ sortMark('parent') }}
          </th>
          <th
            v-if="showCol('assignee')"
            class="min-w-[9rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('assignee')"
            @click="onSort('assignee')"
          >
            Người làm{{ sortMark('assignee') }}
          </th>
          <th
            v-if="showCol('reporter')"
            class="min-w-[9rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('reporter')"
            @click="onSort('reporter')"
          >
            Người giao{{ sortMark('reporter') }}
          </th>
          <th
            v-if="showCol('reviewer')"
            class="min-w-[9rem] px-3 py-2.5 sm:px-4"
            :class="headerClass('reviewer')"
            @click="onSort('reviewer')"
          >
            Người duyệt{{ sortMark('reviewer') }}
          </th>
          <th class="sticky right-0 z-[3] w-[7.5rem] bg-slate-50/95 px-2 py-2.5 text-right dark:bg-slate-800/90 sm:px-3">
            Thao tác
          </th>
        </tr>
      </thead>
      <tbody>
        <template
          v-for="group in groups"
          :key="group.key"
        >
          <tr
            v-show="group.tasks.length > 0 || group.alwaysShow"
            class="border-y border-slate-100 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-800/50"
          >
            <td
              :colspan="colCount"
              class="px-3 py-2 sm:px-4"
            >
              <button
                type="button"
                class="flex w-full items-center gap-2 text-left"
                @click="emit('toggle-group', group.key)"
              >
                <AppIcon
                  :name="isOpen(group.key) ? 'chevron-down' : 'chevron-right'"
                  :size="15"
                  class="text-slate-400"
                />
                <span
                  v-if="group.color"
                  class="h-2.5 w-2.5 shrink-0 rounded-full"
                  :style="{ backgroundColor: group.color }"
                />
                <AppIcon
                  v-else
                  :name="group.icon || 'task'"
                  :size="15"
                  :class="group.key === 'overdue' ? 'text-rose-500' : 'text-slate-400'"
                />
                <span class="text-sm font-semibold text-slate-700 dark:text-slate-200">{{ group.label }}</span>
                <span class="rounded-full bg-white px-1.5 py-0.5 text-[11px] font-medium text-slate-500 ring-1 ring-slate-200 dark:bg-slate-900 dark:ring-slate-700">
                  {{ group.tasks.length }}
                </span>
              </button>
            </td>
          </tr>
          <template v-if="isOpen(group.key)">
            <tr v-if="group.tasks.length === 0">
              <td
                :colspan="colCount"
                class="px-3 py-3 text-xs text-slate-400 sm:px-4"
              >
                Không có việc nào.
              </td>
            </tr>
            <MyWorkTaskListRow
              v-for="task in group.tasks"
              :key="task.id"
              :task="task"
              :mode="mode"
              :status-options="statusOptions"
              :is-col-visible="isColVisible"
              @change-status="(t, s) => emit('change-status', t, s)"
              @log-work="(t, p) => emit('log-work', t, p)"
              @open="(t) => emit('open', t)"
            />
          </template>
        </template>
      </tbody>
    </table>
  </div>
</template>
