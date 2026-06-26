<script setup>
import { ref, computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Badge from '@/shared/ui/Badge.vue';
import QuickWorklogPopover from './QuickWorklogPopover.vue';

const props = defineProps({
    task: { type: Object, required: true },
    mode: { type: String, default: 'self' },
    statusOptions: { type: Array, default: () => [] },
});

const emit = defineEmits(['change-status', 'log-work', 'open']);

const statusOpen = ref(false);
const worklogOpen = ref(false);

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

const dueLabel = computed(() => {
    if (!props.task.due_date) return null;
    const d = new Date(props.task.due_date + 'T00:00:00');
    return d.toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' });
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
        <!-- Title -->
        <button
          type="button"
          class="block w-full truncate text-left text-sm font-medium text-slate-800 hover:text-brand dark:text-slate-100"
          @click="emit('open', task)"
        >
          {{ task.title }}
        </button>

        <!-- Meta row -->
        <div class="mt-1 flex flex-wrap items-center gap-x-2 gap-y-1 text-[11px] text-slate-500">
          <span
            v-if="task.project"
            class="inline-flex items-center gap-1 truncate font-medium text-slate-500"
          >
            {{ task.project.code || task.project.name }}
          </span>
          <span
            v-if="task.priority"
            class="inline-flex"
          >
            <Badge
              :label="task.priority.label"
              :color="task.priority.color"
            />
          </span>
          <span
            v-if="dueLabel"
            class="inline-flex items-center gap-1"
            :class="task.is_late ? 'font-semibold text-rose-600' : ''"
          >
            <AppIcon
              name="clock"
              :size="12"
            />
            {{ dueLabel }}
          </span>
          <span
            v-if="task.logged_today > 0"
            class="inline-flex items-center gap-1 text-emerald-600"
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
            type="button"
            :disabled="!canChange"
            class="inline-flex items-center gap-1 rounded-lg px-1.5 py-1 transition disabled:cursor-not-allowed disabled:opacity-60"
            :class="canChange ? 'hover:bg-slate-100 dark:hover:bg-slate-800' : ''"
            :title="canChange ? 'Đổi trạng thái' : 'Bạn không có quyền đổi trạng thái việc này'"
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

          <div
            v-if="statusOpen"
            class="absolute right-0 top-full z-30 mt-1 w-40"
          >
            <button
              type="button"
              class="fixed inset-0 z-0 cursor-default"
              aria-label="Đóng"
              @click="statusOpen = false"
            />
            <ul class="relative z-10 overflow-hidden rounded-xl border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900">
              <li
                v-for="opt in statusOptions"
                :key="opt.value"
              >
                <button
                  type="button"
                  class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-xs hover:bg-slate-50 dark:hover:bg-slate-800"
                  :class="opt.value === task.status?.value ? 'font-semibold' : ''"
                  @click="pickStatus(opt.value)"
                >
                  <span
                    class="h-2 w-2 rounded-full"
                    :class="dotClass[opt.color] || dotClass.slate"
                  />
                  {{ opt.label }}
                </button>
              </li>
            </ul>
          </div>
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
