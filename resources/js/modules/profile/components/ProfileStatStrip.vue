<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    stats: { type: Object, required: true },
});

const cards = computed(() => [
    {
        key: 'completion',
        icon: 'done',
        tone: 'emerald',
        label: 'Tỷ lệ hoàn thành',
        value: props.stats.completion_rate + '%',
        hint: `${props.stats.tasks_done}/${props.stats.tasks_total} công việc`,
    },
    {
        key: 'in_progress',
        icon: 'task',
        tone: 'sky',
        label: 'Đang thực hiện',
        value: props.stats.tasks_in_progress,
        hint: 'công việc đang làm',
    },
    {
        key: 'overdue',
        icon: 'alert',
        tone: props.stats.tasks_overdue > 0 ? 'rose' : 'slate',
        label: 'Trễ hạn',
        value: props.stats.tasks_overdue,
        hint: 'công việc quá hạn',
    },
    {
        key: 'hours',
        icon: 'worklog',
        tone: 'violet',
        label: 'Giờ ghi nhận',
        value: Number(props.stats.worklog_hours).toLocaleString('vi-VN') + 'h',
        hint: `${props.stats.projects_count} dự án tham gia`,
    },
]);

const toneMap = {
    emerald: 'bg-emerald-50 text-emerald-600',
    sky: 'bg-sky-50 text-sky-600',
    rose: 'bg-rose-50 text-rose-600',
    violet: 'bg-violet-50 text-violet-600',
    slate: 'bg-slate-100 text-slate-500',
};
</script>

<template>
  <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
    <div
      v-for="c in cards"
      :key="c.key"
      class="rounded-2xl border border-slate-200/70 bg-white p-4 shadow-sm"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ c.label }}</span>
        <div
          class="grid h-7 w-7 place-items-center rounded-lg"
          :class="toneMap[c.tone]"
        >
          <AppIcon
            :name="c.icon"
            :size="14"
          />
        </div>
      </div>
      <p class="mt-2 text-2xl font-semibold tabular-nums text-slate-900">
        {{ c.value }}
      </p>
      <p class="mt-0.5 text-[12px] text-slate-400">
        {{ c.hint }}
      </p>
    </div>
  </div>
</template>
