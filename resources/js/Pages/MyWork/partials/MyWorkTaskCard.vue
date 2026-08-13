<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import MyWorkToneLabel from './MyWorkToneLabel.vue';
import QuickWorklogPopover from './QuickWorklogPopover.vue';
import { useFixedDropdownAnchor, resolveAnchorElement } from '@/shared/composables/useFixedDropdownAnchor';
import { displayOrEmpty } from '@/shared/utils/emptyDisplay';
import { toneDotClass } from '../utils/taskDisplay';

const props = defineProps({
    task: { type: Object, required: true },
    mode: { type: String, default: 'self' },
    hideProject: { type: Boolean, default: false },
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

const canChange = computed(
    () => (props.task.can?.contribute || props.task.can?.act_team) && props.task.can_change_status,
);
const canLog = computed(() => props.mode === 'self' && props.task.can?.contribute);

function fmtDate(value) {
    if (!value) return null;
    const d = new Date(`${value}T00:00:00`);
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
}

const dueLabel = computed(() => fmtDate(props.task.due_date));
const overdue = computed(() => Boolean(props.task.is_late));
const isDueToday = computed(() => {
    if (!props.task.due_date) return false;
    const due = new Date(`${props.task.due_date}T00:00:00`);
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
    return 'text-slate-700 dark:text-slate-200';
});

const metaCells = computed(() => [
    {
        key: 'due',
        label: 'Hạn',
        value: displayOrEmpty(dueLabel.value, 'Chưa hạn'),
        valueClass: dueToneClass.value,
    },
    {
        key: 'estimate',
        label: 'Dự kiến',
        value: displayOrEmpty(estimateLabel.value, 'Chưa cập nhật'),
        valueClass: 'text-slate-700 dark:text-slate-200',
    },
    {
        key: 'logged',
        label: 'Hôm nay',
        value: props.task.logged_today > 0 ? `${props.task.logged_today}h` : 'Chưa ghi giờ',
        valueClass: props.task.logged_today > 0
            ? 'font-medium text-emerald-600'
            : 'text-slate-500',
    },
    {
        key: 'priority',
        label: 'Ưu tiên',
        value: props.task.priority?.label ?? null,
        empty: 'Chưa cập nhật',
        tone: props.task.priority,
    },
    {
        key: 'sprint',
        label: 'Sprint',
        value: props.task.sprint?.name ?? props.task.phase?.label ?? null,
        empty: 'Chưa cập nhật',
    },
    {
        key: 'progress',
        label: 'Tiến độ',
        value: `${progress.value}%`,
        valueClass: 'tabular-nums text-slate-700 dark:text-slate-200',
        progress: progress.value,
    },
]);

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
  <article
    class="group relative flex h-full flex-col overflow-hidden rounded-xl border transition hover:shadow-sm"
    :class="overdue
      ? 'border-rose-300 bg-rose-50/80 hover:border-rose-400 dark:border-rose-800 dark:bg-rose-950/30'
      : 'border-slate-200 bg-white hover:border-slate-300 dark:border-slate-700 dark:bg-slate-900'"
  >
    <div
      class="absolute inset-y-0 left-0 w-1"
      :style="{ backgroundColor: overdue ? '#e11d48' : projectColor }"
    />

    <button
      type="button"
      class="min-w-0 flex-1 px-3.5 pb-2 pt-3 pl-4 text-left"
      @click="emit('open', task)"
    >
      <p
        v-if="projectLabel && !hideProject"
        class="truncate text-[11px] font-semibold text-brand"
        :title="projectLabel"
      >
        {{ projectLabel }}
      </p>
      <p
        class="mt-0.5 line-clamp-2 text-sm font-semibold leading-snug group-hover:text-brand"
        :class="overdue ? 'text-rose-800 dark:text-rose-100' : 'text-slate-800 dark:text-slate-100'"
        :title="task.title"
      >
        {{ task.title }}
      </p>
    </button>

    <div class="grid grid-cols-3 gap-1.5 px-3.5 pb-3 pl-4">
      <div
        v-for="cell in metaCells"
        :key="cell.key"
        class="min-w-0 rounded-lg border px-2 py-1.5"
        :class="overdue
          ? 'border-rose-100 bg-white/70 dark:border-rose-900/40 dark:bg-slate-900/40'
          : 'border-slate-100 bg-slate-50/80 dark:border-slate-800 dark:bg-slate-800/40'"
      >
        <p class="truncate text-[9px] font-semibold uppercase tracking-wide text-slate-400">
          {{ cell.label }}
        </p>
        <div
          v-if="cell.tone"
          class="mt-0.5"
        >
          <MyWorkToneLabel
            :label="cell.tone.label"
            :color="cell.tone.color"
          />
        </div>
        <template v-else-if="cell.progress != null">
          <p
            class="mt-0.5 text-xs font-semibold"
            :class="cell.valueClass"
          >
            {{ cell.value }}
          </p>
          <div class="mt-1 h-1 overflow-hidden rounded-full bg-slate-200 dark:bg-slate-700">
            <div
              class="h-full rounded-full bg-brand/70 transition-all"
              :style="{ width: cell.progress + '%' }"
            />
          </div>
        </template>
        <p
          v-else
          class="mt-0.5 truncate text-xs font-medium"
          :class="cell.valueClass || 'text-slate-700 dark:text-slate-200'"
          :title="cell.value || cell.empty"
        >
          {{ cell.value || cell.empty }}
        </p>
      </div>
    </div>

    <div
      class="mt-auto flex items-center justify-between gap-1 border-t px-2.5 py-2 pl-3.5"
      :class="overdue ? 'border-rose-100 dark:border-rose-900/40' : 'border-slate-100 dark:border-slate-800'"
    >
      <div class="relative min-w-0">
        <button
          ref="statusTriggerRef"
          type="button"
          :disabled="!canChange"
          class="inline-flex max-w-full items-center gap-1 rounded-lg border border-transparent px-1.5 py-1 transition disabled:cursor-not-allowed disabled:opacity-60"
          :class="canChange ? 'hover:border-slate-200 hover:bg-white/80 dark:hover:bg-slate-800' : ''"
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
          <AppIcon
            v-if="canChange"
            name="chevron-down"
            :size="13"
            class="shrink-0"
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

      <div
        v-if="canLog"
        class="relative shrink-0"
      >
        <button
          ref="worklogTriggerRef"
          type="button"
          class="grid h-8 w-8 place-items-center rounded-lg text-slate-400 transition hover:bg-white/80 hover:text-brand dark:hover:bg-slate-800"
          title="Ghi giờ nhanh"
          @click="worklogOpen = !worklogOpen"
        >
          <AppIcon
            name="worklog"
            :size="15"
          />
        </button>
        <QuickWorklogPopover
          :open="worklogOpen"
          :anchor-ref="worklogTriggerRef"
          :task-title="task.title"
          @submit="onLog"
          @close="worklogOpen = false"
        />
      </div>
    </div>
  </article>
</template>
