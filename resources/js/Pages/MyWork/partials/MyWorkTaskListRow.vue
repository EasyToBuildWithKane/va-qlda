<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import ProgressBar from '@/shared/ui/ProgressBar.vue';
import MyWorkToneLabel from './MyWorkToneLabel.vue';
import { useFixedDropdownAnchor, resolveAnchorElement } from '@/shared/composables/useFixedDropdownAnchor';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';
import {
    formatTaskDate,
    isDueToday,
    projectLabel,
    hoursLabel,
    progressValue,
    dueToneClass,
    personName,
    toneDotClass,
} from '../utils/taskDisplay';

const props = defineProps({
    task: { type: Object, required: true },
    mode: { type: String, default: 'self' },
    statusOptions: { type: Array, default: () => [] },
    isColVisible: { type: Function, required: true },
});

const emit = defineEmits(['change-status', 'log-work', 'open']);

const statusOpen = ref(false);
const statusTriggerRef = ref(null);

const { panelStyle: statusPanelStyle } = useFixedDropdownAnchor(
    () => resolveAnchorElement(statusTriggerRef),
    statusOpen,
    { width: 168, zIndex: 120, preferDown: true, maxHeight: 280 },
);

const canChange = computed(
    () => (props.task.can?.contribute || props.task.can?.act_team) && props.task.can_change_status,
);

const overdue = computed(() => Boolean(props.task.is_late));
const dueToday = computed(() => isDueToday(props.task));
const project = computed(() => projectLabel(props.task));
const progress = computed(() => progressValue(props.task));
const projectColor = computed(() => props.task.project?.color || '#94a3b8');

const rowClass = computed(() => {
    if (overdue.value) {
        return 'bg-rose-50/90 hover:bg-rose-100/80 dark:bg-rose-950/35 dark:hover:bg-rose-950/50';
    }
    if (dueToday.value) {
        return 'hover:bg-amber-50/70 dark:hover:bg-amber-950/20';
    }
    return 'hover:bg-slate-50/90 dark:hover:bg-slate-800/50';
});

const stickyClass = computed(() => {
    if (overdue.value) {
        return 'bg-rose-50/90 group-hover:bg-rose-100/80 dark:bg-rose-950/35 dark:group-hover:bg-rose-950/50';
    }
    return 'bg-white group-hover:bg-slate-50 dark:bg-slate-900 dark:group-hover:bg-slate-800';
});

function pickStatus(value) {
    statusOpen.value = false;
    if (value !== props.task.status?.value) {
        emit('change-status', props.task, value);
    }
}

function onRowActivate() {
    emit('open', props.task);
}

function onRowKeydown(e) {
    if (e.key === 'Enter' || e.key === ' ') {
        e.preventDefault();
        onRowActivate();
    }
}
</script>

<template>
  <tr
    class="group cursor-pointer border-l-[3px] transition-colors"
    :class="rowClass"
    :style="{ borderLeftColor: overdue ? '#e11d48' : projectColor }"
    tabindex="0"
    @click="onRowActivate"
    @keydown="onRowKeydown"
  >
    <td
      class="sticky left-0 z-[1] min-w-[16rem] max-w-[22rem] px-3 py-2.5 align-middle sm:px-4"
      :class="stickyClass"
    >
      <div class="min-w-0">
        <p
          class="truncate text-sm font-semibold group-hover:text-brand"
          :class="overdue ? 'text-rose-800 dark:text-rose-100' : 'text-slate-800 dark:text-slate-100'"
          :title="task.title"
        >
          {{ task.title }}
        </p>
        <p
          v-if="project && !isColVisible('project')"
          class="truncate text-[11px] font-medium text-brand"
          :title="project"
        >
          {{ project }}
        </p>
      </div>
    </td>

    <td
      v-if="isColVisible('project')"
      class="min-w-[9rem] max-w-[14rem] px-3 py-2.5 align-middle sm:px-4"
    >
      <p
        class="truncate text-xs font-semibold text-brand"
        :title="project"
      >
        {{ displayOrEmpty(project, 'Chưa gán dự án') }}
      </p>
    </td>

    <td
      v-if="isColVisible('status')"
      class="min-w-[8.5rem] px-3 py-2.5 align-middle sm:px-4"
      @click.stop
    >
      <div class="relative">
        <button
          ref="statusTriggerRef"
          type="button"
          :disabled="!canChange"
          class="inline-flex max-w-full items-center gap-1 rounded-md px-0.5 py-0.5 transition disabled:cursor-not-allowed disabled:opacity-60"
          :class="canChange ? 'hover:bg-white/70 dark:hover:bg-slate-800' : ''"
          :title="canChange ? 'Đổi trạng thái' : 'Bạn không có quyền đổi trạng thái việc này'"
          aria-haspopup="listbox"
          :aria-expanded="statusOpen"
          @click="statusOpen = !statusOpen"
        >
          <MyWorkToneLabel
            v-if="task.status"
            :label="task.status.label"
            :color="task.status.color"
          />
          <span
            v-else
            class="text-xs text-slate-400"
          >{{ EMPTY_LABELS.notUpdated }}</span>
          <AppIcon
            v-if="canChange"
            name="chevron-down"
            :size="12"
            class="shrink-0 text-slate-400"
          />
        </button>
        <Teleport to="body">
          <button
            v-if="statusOpen"
            type="button"
            class="fixed inset-0 z-[110] cursor-default bg-transparent"
            aria-label="Đóng"
            @click="statusOpen = false"
          />
          <div
            v-if="statusOpen"
            :style="statusPanelStyle"
            class="rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
            role="listbox"
          >
            <ul class="max-h-[inherit] overflow-y-auto">
              <li
                v-for="opt in statusOptions"
                :key="opt.value"
              >
                <button
                  type="button"
                  class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800"
                  :class="opt.value === task.status?.value ? 'font-semibold' : ''"
                  role="option"
                  :aria-selected="opt.value === task.status?.value"
                  @click="pickStatus(opt.value)"
                >
                  <span
                    class="h-2 w-2 shrink-0 rounded-full"
                    :class="toneDotClass(opt.color)"
                  />
                  {{ opt.label }}
                </button>
              </li>
            </ul>
          </div>
        </Teleport>
      </div>
    </td>

    <td
      v-if="isColVisible('priority')"
      class="min-w-[6.5rem] px-3 py-2.5 align-middle sm:px-4"
    >
      <MyWorkToneLabel
        v-if="task.priority"
        :label="task.priority.label"
        :color="task.priority.color"
      />
      <span
        v-else
        class="text-xs text-slate-400"
      >{{ EMPTY_LABELS.notUpdated }}</span>
    </td>

    <td
      v-if="isColVisible('due_date')"
      class="min-w-[7rem] whitespace-nowrap px-3 py-2.5 align-middle text-xs tabular-nums sm:px-4"
      :class="dueToneClass(task)"
    >
      {{ displayOrEmpty(formatTaskDate(task.due_date), 'Chưa hạn') }}
    </td>

    <td
      v-if="isColVisible('progress')"
      class="min-w-[8rem] px-3 py-2.5 align-middle sm:px-4"
    >
      <ProgressBar
        :value="progress"
        height="h-1.5"
      />
    </td>

    <td
      v-if="isColVisible('logged_today')"
      class="min-w-[6.5rem] px-3 py-2.5 align-middle text-xs tabular-nums sm:px-4"
      :class="task.logged_today > 0 ? 'font-medium text-emerald-600' : 'text-slate-500'"
    >
      {{ task.logged_today > 0 ? `${task.logged_today}h` : 'Chưa ghi giờ' }}
    </td>

    <td
      v-if="isColVisible('estimate')"
      class="min-w-[5.5rem] px-3 py-2.5 align-middle text-xs tabular-nums text-slate-600 sm:px-4"
    >
      {{ displayOrEmpty(hoursLabel(task.estimate_hours), EMPTY_LABELS.notUpdated) }}
    </td>

    <td
      v-if="isColVisible('phase')"
      class="min-w-[7rem] px-3 py-2.5 align-middle sm:px-4"
    >
      <MyWorkToneLabel
        v-if="task.phase"
        :label="task.phase.label"
        :color="task.phase.color"
      />
      <span
        v-else
        class="text-xs text-slate-400"
      >{{ EMPTY_LABELS.notUpdated }}</span>
    </td>

    <td
      v-if="isColVisible('source')"
      class="min-w-[8rem] px-3 py-2.5 align-middle text-xs text-slate-600 sm:px-4"
    >
      {{ displayOrEmpty(task.source?.label, EMPTY_LABELS.notUpdated) }}
    </td>

    <td
      v-if="isColVisible('start_date')"
      class="min-w-[7rem] whitespace-nowrap px-3 py-2.5 align-middle text-xs tabular-nums text-slate-600 sm:px-4"
    >
      {{ displayOrEmpty(formatTaskDate(task.start_date), EMPTY_LABELS.notUpdated) }}
    </td>

    <td
      v-if="isColVisible('story_points')"
      class="min-w-[5.5rem] px-3 py-2.5 align-middle text-xs tabular-nums text-slate-600 sm:px-4"
    >
      {{ displayOrEmpty(task.story_points, EMPTY_LABELS.notUpdated) }}
    </td>

    <td
      v-if="isColVisible('actual_hours')"
      class="min-w-[6rem] px-3 py-2.5 align-middle text-xs tabular-nums text-slate-600 sm:px-4"
    >
      {{ displayOrEmpty(hoursLabel(task.actual_hours), EMPTY_LABELS.notUpdated) }}
    </td>

    <td
      v-if="isColVisible('milestone')"
      class="min-w-[4.5rem] px-3 py-2.5 align-middle text-xs sm:px-4"
      :class="task.is_milestone ? 'font-medium text-amber-700' : 'text-slate-400'"
    >
      {{ task.is_milestone ? 'Mốc' : 'Không' }}
    </td>

    <td
      v-if="isColVisible('sla')"
      class="min-w-[6.5rem] px-3 py-2.5 align-middle sm:px-4"
    >
      <MyWorkToneLabel
        v-if="task.sla_result"
        :label="task.sla_result.label"
        :color="task.sla_result.color"
      />
      <span
        v-else
        class="text-xs text-slate-400"
      >{{ EMPTY_LABELS.notUpdated }}</span>
    </td>

    <td
      v-if="isColVisible('epic')"
      class="min-w-[8rem] max-w-[12rem] px-3 py-2.5 align-middle text-xs text-slate-600 sm:px-4"
    >
      <span class="line-clamp-2">{{ displayOrEmpty(task.epic?.name, EMPTY_LABELS.notUpdated) }}</span>
    </td>

    <td
      v-if="isColVisible('parent')"
      class="min-w-[9rem] max-w-[14rem] px-3 py-2.5 align-middle text-xs text-slate-600 sm:px-4"
    >
      <span class="line-clamp-2">{{ displayOrEmpty(task.parent?.title, EMPTY_LABELS.notUpdated) }}</span>
    </td>

    <td
      v-if="isColVisible('assignee')"
      class="min-w-[9rem] px-3 py-2.5 align-middle sm:px-4"
    >
      <div
        v-if="task.assignee"
        class="flex min-w-0 items-center gap-2"
      >
        <Avatar
          :name="task.assignee.name"
          :src="task.assignee.avatar_path"
          :size="24"
          class="shrink-0"
        />
        <span class="truncate text-xs text-slate-700 dark:text-slate-200">{{ task.assignee.name }}</span>
      </div>
      <span
        v-else
        class="text-xs text-slate-400"
      >{{ personName(null) }}</span>
    </td>

    <td
      v-if="isColVisible('reporter')"
      class="min-w-[9rem] px-3 py-2.5 align-middle sm:px-4"
    >
      <div
        v-if="task.reporter"
        class="flex min-w-0 items-center gap-2"
      >
        <Avatar
          :name="task.reporter.name"
          :src="task.reporter.avatar_path"
          :size="24"
          class="shrink-0"
        />
        <span class="truncate text-xs text-slate-700 dark:text-slate-200">{{ task.reporter.name }}</span>
      </div>
      <span
        v-else
        class="text-xs text-slate-400"
      >{{ personName(null) }}</span>
    </td>

    <td
      v-if="isColVisible('reviewer')"
      class="min-w-[9rem] px-3 py-2.5 align-middle sm:px-4"
    >
      <div
        v-if="task.reviewer"
        class="flex min-w-0 items-center gap-2"
      >
        <Avatar
          :name="task.reviewer.name"
          :src="task.reviewer.avatar_path"
          :size="24"
          class="shrink-0"
        />
        <span class="truncate text-xs text-slate-700 dark:text-slate-200">{{ task.reviewer.name }}</span>
      </div>
      <span
        v-else
        class="text-xs text-slate-400"
      >{{ personName(null) }}</span>
    </td>
  </tr>
</template>
