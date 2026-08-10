<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    kpi: { type: Object, default: () => ({}) },
});

const toneClasses = {
    brand: 'text-brand bg-brand/10',
    sky: 'text-sky-600 bg-sky-100 dark:bg-sky-950/60 dark:text-sky-300',
    emerald: 'text-emerald-600 bg-emerald-100 dark:bg-emerald-950/60 dark:text-emerald-300',
    amber: 'text-amber-600 bg-amber-100 dark:bg-amber-950/60 dark:text-amber-300',
    rose: 'text-rose-600 bg-rose-100 dark:bg-rose-950/60 dark:text-rose-300',
    violet: 'text-violet-600 bg-violet-100 dark:bg-violet-950/60 dark:text-violet-300',
    slate: 'text-slate-600 bg-slate-100 dark:bg-slate-800 dark:text-slate-300',
};

function relTime(iso) {
    if (!iso) return '—';
    const diff = Date.now() - new Date(iso).getTime();
    const mins = Math.round(diff / 60000);
    if (mins < 1) return 'vừa xong';
    if (mins < 60) return `${mins} phút trước`;
    const hrs = Math.round(mins / 60);
    if (hrs < 24) return `${hrs} giờ trước`;
    return `${Math.round(hrs / 24)} ngày trước`;
}

const cards = computed(() => {
    const k = props.kpi || {};
    const n = (v) => Number(v ?? 0);
    return [
        { key: 'progress', label: 'Tiến độ Sprint', value: `${n(k.sprint_progress)}%`, icon: 'overview', tone: 'brand', progress: n(k.sprint_progress) },
        { key: 'completed', label: 'Hoàn thành', value: `${n(k.completed_tasks)} / ${n(k.total_tasks)}`, icon: 'check-circle', tone: 'emerald' },
        { key: 'remaining', label: 'Còn lại', value: n(k.remaining_tasks), icon: 'task', tone: 'sky' },
        { key: 'overdue', label: 'Quá hạn', value: n(k.overdue), icon: 'clock', tone: n(k.overdue) ? 'rose' : 'slate' },
        { key: 'blocked', label: 'Bị chặn', value: n(k.blocked), icon: 'blockers', tone: n(k.blocked) ? 'rose' : 'slate' },
        { key: 'issues', label: 'Test case mở', value: n(k.open_issues), icon: 'alert', tone: n(k.open_issues) ? 'amber' : 'slate' },
        { key: 'feedback', label: 'Phản hồi', value: n(k.feedback), icon: 'feedback', tone: 'violet' },
        { key: 'bugs', label: 'Lỗi nghiêm trọng', value: n(k.critical_bugs), icon: 'bug', tone: n(k.critical_bugs) ? 'rose' : 'slate' },
        { key: 'hours', label: 'Giờ công', value: `${n(k.worklog_hours)}h`, icon: 'worklog', tone: 'sky' },
        { key: 'velocity', label: 'Team Velocity', value: `${n(k.team_velocity)}%`, icon: 'rocket', tone: 'emerald' },
        { key: 'updated', label: 'Cập nhật', value: relTime(k.last_updated), icon: 'refresh', tone: 'slate', small: true },
    ];
});
</script>

<template>
  <div class="grid grid-cols-2 gap-2.5 sm:grid-cols-3 lg:grid-cols-4 xl:grid-cols-6">
    <div
      v-for="c in cards"
      :key="c.key"
      class="rounded-xl border border-slate-200 bg-white p-3 transition hover:shadow-sm dark:border-slate-700 dark:bg-slate-900"
    >
      <div class="flex items-center justify-between">
        <span class="text-[11px] font-medium uppercase tracking-wide text-slate-400">{{ c.label }}</span>
        <span
          class="inline-flex h-6 w-6 items-center justify-center rounded-lg"
          :class="toneClasses[c.tone]"
        >
          <AppIcon
            :name="c.icon"
            :size="13"
          />
        </span>
      </div>
      <div
        class="mt-1.5 font-display font-semibold tabular-nums text-slate-800 dark:text-slate-100"
        :class="c.small ? 'text-sm' : 'text-xl'"
      >
        {{ c.value }}
      </div>
      <div
        v-if="c.progress != null"
        class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-100 dark:bg-slate-800"
      >
        <div
          class="h-full rounded-full bg-brand transition-all duration-200"
          :style="{ width: `${Math.min(c.progress, 100)}%` }"
        />
      </div>
    </div>
  </div>
</template>
