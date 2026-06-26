<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import QuickWorklogPopover from './QuickWorklogPopover.vue';
import { useFixedDropdownAnchor, resolveAnchorElement } from '@/shared/composables/useFixedDropdownAnchor';

const props = defineProps({
    task: { type: Object, required: true },
    mode: { type: String, default: 'self' },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['change-status', 'log-work', 'open']);

const statusOpen = ref(false);
const worklogOpen = ref(false);
const statusTriggerRef = ref(null);

const { panelStyle: statusPanelStyle } = useFixedDropdownAnchor(
    () => resolveAnchorElement(statusTriggerRef),
    statusOpen,
    { width: 168, zIndex: 120, preferDown: true, maxHeight: 280 },
);

// Literal classes so Tailwind's content scanner keeps them (no dynamic strings).
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
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' });
}

const startLabel = computed(() => fmtDate(props.task.start_date));
const dueLabel = computed(() => fmtDate(props.task.due_date));

// "PRJ-026 · Tên dự án" — mã + tên để biết rõ đang ở dự án nào.
const projectLabel = computed(() => {
    const p = props.task.project;
    if (!p) return null;
    if (p.code && p.name) return `${p.code} · ${p.name}`;
    return p.name || p.code || null;
});

const estimateLabel = computed(() => {
    const h = props.task.estimate_hours;
    if (h == null || h <= 0) return null;
    return `${Number.isInteger(h) ? h : h.toFixed(1)}h`;
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
  <div class="group rounded-xl border border-slate-200 bg-white p-3 transition hover:border-slate-300 hover:shadow-sm dark:border-slate-700 dark:bg-slate-900">
    <div class="flex items-start gap-3">
      <!-- Project colour dot -->
      <span
        class="mt-1 h-2.5 w-2.5 shrink-0 rounded-full"
        :style="{ backgroundColor: task.project?.color || '#94a3b8' }"
        :title="task.project?.name"
      />

      <div class="min-w-0 flex-1">
        <!-- Project — mã + tên, rõ đang ở dự án nào -->
        <button
          v-if="projectLabel"
          type="button"
          class="block max-w-full truncate text-left text-[11px] font-semibold text-brand hover:underline"
          :title="projectLabel"
          @click="emit('open', task)"
        >
          {{ projectLabel }}
        </button>

        <!-- Title -->
        <button
          type="button"
          class="mt-0.5 block w-full truncate text-left text-sm font-medium text-slate-800 hover:text-brand dark:text-slate-100"
          @click="emit('open', task)"
        >
          {{ task.title }}
        </button>

        <!-- Labels (badge row) -->
        <div class="mt-1.5 flex flex-wrap items-center gap-1.5">
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
            v-if="task.is_milestone"
            label="Mốc"
            color="amber"
          />
          <Badge
            v-if="task.sprint"
            :label="task.sprint.name"
            color="slate"
          />
        </div>

        <!-- Meta row — cột thời gian + ước tính giờ -->
        <div class="mt-1.5 flex flex-wrap items-center gap-x-3 gap-y-1 text-[11px] text-slate-500">
          <span
            v-if="startLabel"
            class="inline-flex items-center gap-1"
            title="Ngày bắt đầu"
          >
            <AppIcon
              name="calendar"
              :size="12"
            />
            <span class="text-slate-400">Bắt đầu:</span>
            {{ startLabel }}
          </span>
          <span
            v-if="dueLabel"
            class="inline-flex items-center gap-1"
            :class="task.is_late ? 'font-semibold text-rose-600' : ''"
            title="Hạn hoàn thành"
          >
            <AppIcon
              name="clock"
              :size="12"
            />
            <span :class="task.is_late ? 'text-rose-400' : 'text-slate-400'">Hạn:</span>
            {{ dueLabel }}
          </span>
          <span
            v-if="estimateLabel"
            class="inline-flex items-center gap-1"
            title="Giờ làm dự kiến (ước tính)"
          >
            <AppIcon
              name="gauge"
              :size="12"
            />
            <span class="text-slate-400">Dự kiến:</span>
            {{ estimateLabel }}
          </span>
          <span
            v-if="task.logged_today > 0"
            class="inline-flex items-center gap-1 text-emerald-600"
            title="Giờ đã ghi hôm nay"
          >
            <AppIcon
              name="worklog"
              :size="12"
            />
            {{ task.logged_today }}h hôm nay
          </span>
        </div>
      </div>

      <!-- Actions -->
      <div class="flex shrink-0 items-center gap-1">
        <!-- Status menu -->
        <div class="relative">
          <button
            ref="statusTriggerRef"
            type="button"
            :disabled="!canChange"
            class="inline-flex items-center gap-1 rounded-lg px-1.5 py-1 transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="canChange ? 'hover:bg-slate-100 dark:hover:bg-slate-800' : ''"
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

        <!-- Quick worklog (self only) -->
        <div
          v-if="canLog"
          class="relative"
        >
          <button
            type="button"
            class="grid h-7 w-7 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-brand dark:hover:bg-slate-800"
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
            :task-title="task.title"
            @submit="onLog"
            @close="worklogOpen = false"
          />
        </div>

        <!-- Open detail -->
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded-lg text-slate-400 transition hover:bg-slate-100 hover:text-brand dark:hover:bg-slate-800"
          title="Mở chi tiết"
          @click="emit('open', task)"
        >
          <AppIcon
            name="external-link"
            :size="15"
          />
        </button>
      </div>
    </div>
  </div>
</template>
