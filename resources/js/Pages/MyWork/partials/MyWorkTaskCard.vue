<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import QuickWorklogPopover from './QuickWorklogPopover.vue';
import { useFixedDropdownAnchor, resolveAnchorElement } from '@/shared/composables/useFixedDropdownAnchor';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    task: { type: Object, required: true },
    mode: { type: String, default: 'self' },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['change-status', 'log-work', 'open']);

const statusOpen = ref(false);
const worklogOpen = ref(false);
const statusTriggerRef = ref(null);
const worklogTriggerRef = ref(null);

const { panelStyle: statusPanelStyle } = useFixedDropdownAnchor(
    () => resolveAnchorElement(statusTriggerRef),
    statusOpen,
    { width: 168, zIndex: 120, preferDown: true, maxHeight: 280 },
);

const dotClass = {
    slate: 'bg-slate-400',
    sky: 'bg-sky-500',
    violet: 'bg-violet-500',
    emerald: 'bg-emerald-500',
    rose: 'bg-rose-500',
    amber: 'bg-amber-500',
};

const canChange = computed(
    () => (props.task.can?.contribute || props.task.can?.act_team) && props.task.can_change_status,
);
const canLog = computed(() => props.mode === 'self' && props.task.can?.contribute);

function fmtDate(value) {
    if (!value) return null;
    const d = new Date(value + 'T00:00:00');
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
}

const dueLabel = computed(() => fmtDate(props.task.due_date));

const overdueDays = computed(() => {
    if (!props.task.is_late || !props.task.due_date) return 0;
    const due = new Date(props.task.due_date + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return Math.max(0, Math.round((today - due) / 86400000));
});

const isDueToday = computed(() => {
    if (!props.task.due_date) return false;
    const due = new Date(props.task.due_date + 'T00:00:00');
    const today = new Date();
    today.setHours(0, 0, 0, 0);
    return due.getTime() === today.getTime();
});

const progress = computed(() => {
    const p = Number(props.task.progress ?? 0);
    return Number.isFinite(p) ? Math.min(100, Math.max(0, p)) : 0;
});

const projectLabel = computed(() => {
    const p = props.task.project;
    if (!p) return null;
    if (p.code && p.name) return `${p.code} · ${p.name}`;
    return p.name || p.code || null;
});

const projectColor = computed(() => props.task.project?.color || '#94a3b8');

const estimateLabel = computed(() => {
    const h = props.task.estimate_hours;
    if (h == null || h <= 0) return null;
    return `${Number.isInteger(h) ? h : h.toFixed(1)}h`;
});

const dueToneClass = computed(() => {
    if (props.task.is_late) return 'text-rose-600 font-semibold';
    if (isDueToday.value) return 'text-amber-700 font-semibold';
    return 'text-slate-600 dark:text-slate-300';
});

function pickStatus(value) {
    statusOpen.value = false;
    if (value !== props.task.status?.value) {
        emit('change-status', props.task, value);
    }
}

function onLog(payload) {
    worklogOpen.value = false;
    emit('log-work', props.task, payload);
}
</script>

<template>
  <div
    class="group relative overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-slate-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-900"
  >
    <div
      class="absolute inset-y-0 left-0 w-1"
      :style="{ backgroundColor: projectColor }"
    />

    <!-- Hàng chính: tiêu đề | meta | tiến độ | trạng thái | thao tác -->
    <div class="flex flex-col gap-2.5 py-2.5 pl-3.5 pr-2.5 sm:flex-row sm:items-center sm:gap-3">
      <!-- Cột 1: dự án + tiêu đề -->
      <button
        type="button"
        class="min-w-0 flex-1 text-left"
        @click="emit('open', task)"
      >
        <p
          v-if="projectLabel"
          class="truncate text-[11px] font-semibold text-brand"
          :title="projectLabel"
        >
          {{ projectLabel }}
        </p>
        <p
          class="truncate text-sm font-semibold text-slate-800 group-hover:text-brand dark:text-slate-100"
          :title="task.title"
        >
          {{ task.title }}
        </p>
        <div class="mt-1 flex flex-wrap items-center gap-1.5 sm:hidden">
          <span
            v-if="overdueDays > 0"
            class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700"
          >
            Quá hạn {{ overdueDays }}d
          </span>
          <span
            v-else-if="isDueToday"
            class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700"
          >
            Hôm nay
          </span>
          <Badge
            v-if="task.priority"
            :label="task.priority.label"
            :color="task.priority.color"
          />
        </div>
      </button>

      <!-- Cột 2: nhãn (desktop) -->
      <div class="hidden min-w-0 shrink-0 items-center gap-1.5 sm:flex lg:max-w-[14rem] xl:max-w-none">
        <span
          v-if="overdueDays > 0"
          class="inline-flex shrink-0 items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300"
          :title="`Quá hạn ${overdueDays} ngày`"
        >
          <AppIcon
            name="alert"
            :size="11"
          />
          QH {{ overdueDays }}d
        </span>
        <span
          v-else-if="isDueToday"
          class="inline-flex shrink-0 items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
        >
          <AppIcon
            name="calendar-clock"
            :size="11"
          />
          Hôm nay
        </span>
        <Badge
          v-if="task.priority"
          :label="task.priority.label"
          :color="task.priority.color"
        />
        <Badge
          v-if="task.phase"
          :label="task.phase.label"
          :color="task.phase.color"
        />
        <Badge
          v-if="task.sprint"
          :label="task.sprint.name"
          color="slate"
        />
        <Badge
          v-if="task.is_milestone"
          label="Mốc"
          color="amber"
        />
      </div>

      <!-- Cột 3: hạn + giờ -->
      <div class="flex shrink-0 items-center gap-3 text-[11px] sm:w-[7.5rem] sm:flex-col sm:items-start sm:gap-0.5 lg:w-auto lg:flex-row lg:items-center lg:gap-3">
        <span
          class="inline-flex items-center gap-1"
          :class="dueToneClass"
          :title="task.due_date ? 'Hạn hoàn thành' : 'Chưa chọn hạn'"
        >
          <AppIcon
            name="clock"
            :size="12"
          />
          {{ displayOrEmpty(dueLabel, 'Chưa hạn') }}
        </span>
        <span
          v-if="estimateLabel"
          class="inline-flex items-center gap-1 text-slate-500"
          title="Giờ dự kiến"
        >
          <AppIcon
            name="gauge"
            :size="12"
          />
          {{ estimateLabel }}
        </span>
        <span
          v-if="task.logged_today > 0"
          class="inline-flex items-center gap-1 font-medium text-emerald-600"
          title="Giờ đã ghi hôm nay"
        >
          <AppIcon
            name="worklog"
            :size="12"
          />
          {{ task.logged_today }}h
        </span>
      </div>

      <!-- Cột 4: tiến độ -->
      <div
        class="flex shrink-0 items-center gap-2 sm:w-28"
        title="Tiến độ"
      >
        <div class="h-1.5 min-w-0 flex-1 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800">
          <div
            class="h-full rounded-full bg-brand/70 transition-all"
            :style="{ width: progress + '%' }"
          />
        </div>
        <span class="w-8 shrink-0 text-right text-[11px] font-semibold tabular-nums text-slate-500">
          {{ progress }}%
        </span>
      </div>

      <!-- Cột 5: trạng thái + thao tác -->
      <div class="flex shrink-0 items-center justify-end gap-0.5 border-t border-slate-100 pt-2 sm:border-0 sm:pt-0 dark:border-slate-800">
        <div class="relative">
          <button
            ref="statusTriggerRef"
            type="button"
            :disabled="!canChange"
            class="inline-flex items-center gap-1 rounded-lg border border-transparent px-1.5 py-1 transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="canChange ? 'hover:border-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800' : ''"
            :title="canChange ? 'Đổi trạng thái' : 'Bạn không có quyền đổi trạng thái việc này'"
            aria-haspopup="listbox"
            :aria-expanded="statusOpen"
            @click="statusOpen = !statusOpen"
          >
            <Badge
              v-if="task.status"
              :label="task.status.label"
              :color="task.status.color"
            />
            <AppIcon
              v-if="canChange"
              name="chevron-down"
              :size="13"
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
                      :class="dotClass[opt.color] || dotClass.slate"
                    />
                    {{ opt.label }}
                  </button>
                </li>
              </ul>
            </div>
          </Teleport>
        </div>

        <button
          v-if="canLog"
          ref="worklogTriggerRef"
          type="button"
          class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-brand dark:hover:bg-slate-800"
          title="Ghi giờ nhanh"
          @click="worklogOpen = !worklogOpen"
        >
          <AppIcon
            name="worklog"
            :size="15"
          />
        </button>
        <QuickWorklogPopover
          v-if="canLog"
          :open="worklogOpen"
          :anchor-ref="worklogTriggerRef"
          :task-title="task.title"
          @submit="onLog"
          @close="worklogOpen = false"
        />

        <button
          type="button"
          class="inline-flex h-8 items-center gap-1 rounded-lg px-2 text-xs font-medium text-slate-500 transition hover:bg-brand/5 hover:text-brand dark:hover:bg-slate-800"
          title="Xem chi tiết"
          @click="emit('open', task)"
        >
          <AppIcon
            name="eye"
            :size="15"
          />
          <span class="hidden xl:inline">Chi tiết</span>
        </button>
      </div>
    </div>
  </div>
</template>
