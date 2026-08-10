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
        badge: props.task.priority,
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
    class="group relative flex h-full flex-col overflow-hidden rounded-xl border border-slate-200 bg-white transition hover:border-slate-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-900"
  >
    <div
      class="absolute inset-y-0 left-0 w-1"
      :style="{ backgroundColor: projectColor }"
    />

    <!-- Header -->
    <button
      type="button"
      class="min-w-0 flex-1 px-3.5 pb-2 pt-3 pl-4 text-left"
      @click="emit('open', task)"
    >
      <div class="mb-1.5 flex flex-wrap items-center gap-1.5">
        <span
          v-if="overdueDays > 0"
          class="inline-flex items-center gap-1 rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700 dark:bg-rose-950/50 dark:text-rose-300"
        >
          Quá hạn {{ overdueDays }}d
        </span>
        <span
          v-else-if="isDueToday"
          class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700 dark:bg-amber-950/50 dark:text-amber-300"
        >
          Hôm nay
        </span>
        <Badge
          v-if="task.is_milestone"
          label="Mốc"
          color="amber"
        />
        <Badge
          v-if="task.phase && !task.sprint"
          :label="task.phase.label"
          :color="task.phase.color"
        />
      </div>
      <p
        v-if="projectLabel"
        class="truncate text-[11px] font-semibold text-brand"
        :title="projectLabel"
      >
        {{ projectLabel }}
      </p>
      <p
        class="mt-0.5 line-clamp-2 text-sm font-semibold leading-snug text-slate-800 group-hover:text-brand dark:text-slate-100"
        :title="task.title"
      >
        {{ task.title }}
      </p>
    </button>

    <!-- Meta: 2 hàng × 3 cột -->
    <div class="grid grid-cols-3 gap-1.5 px-3.5 pb-3 pl-4">
      <div
        v-for="cell in metaCells"
        :key="cell.key"
        class="min-w-0 rounded-lg border border-slate-100 bg-slate-50/80 px-2 py-1.5 dark:border-slate-800 dark:bg-slate-800/40"
      >
        <p class="truncate text-[9px] font-semibold uppercase tracking-wide text-slate-400">
          {{ cell.label }}
        </p>
        <div
          v-if="cell.badge"
          class="mt-0.5"
        >
          <Badge
            :label="cell.badge.label"
            :color="cell.badge.color"
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

    <!-- Footer actions -->
    <div class="mt-auto flex items-center justify-between gap-1 border-t border-slate-100 px-2.5 py-2 pl-3.5 dark:border-slate-800">
      <div class="relative min-w-0">
        <button
          ref="statusTriggerRef"
          type="button"
          :disabled="!canChange"
          class="inline-flex max-w-full items-center gap-1 rounded-lg border border-transparent px-1.5 py-1 transition disabled:cursor-not-allowed disabled:opacity-60"
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
                    :class="dotClass[opt.color] || dotClass.slate"
                  />
                  {{ opt.label }}
                </button>
              </li>
            </ul>
          </div>
        </Teleport>
      </div>

      <div class="flex shrink-0 items-center gap-0.5">
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
          <span class="hidden sm:inline">Chi tiết</span>
        </button>
      </div>
    </div>
  </article>
</template>
