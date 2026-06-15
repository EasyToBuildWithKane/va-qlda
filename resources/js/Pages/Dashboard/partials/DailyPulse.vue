<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProgressRing from './ProgressRing.vue';
import Sparkline from './Sparkline.vue';

const props = defineProps({
    pulse: { type: Object, default: () => ({}) },
    today: { type: String, default: '' },
});

const reported = computed(() => props.pulse.reportedToday ?? 0);
const expected = computed(() => props.pulse.expectedToday ?? 0);
const reportRate = computed(() =>
    expected.value > 0 ? Math.round((reported.value / expected.value) * 100) : 0,
);

const hoursValues = computed(() => (props.pulse.hoursTrend ?? []).map((d) => d.value ?? 0));

const costLabel = computed(() => {
    const v = props.pulse.costThisWeek ?? 0;
    if (v >= 1_000_000) return (v / 1_000_000).toFixed(1).replace('.0', '') + ' triệu';
    return Number(v).toLocaleString('vi-VN');
});

const tiles = computed(() => [
    {
        key: 'done',
        label: 'Hoàn thành hôm nay',
        value: props.pulse.completedToday ?? 0,
        sub: 'công việc đã xong',
        icon: 'check-circle',
        tone: 'text-emerald-600',
        bg: 'bg-emerald-50',
    },
    {
        key: 'due',
        label: 'Đến hạn hôm nay',
        value: props.pulse.dueToday ?? 0,
        sub: `${props.pulse.overdue ?? 0} đang quá hạn`,
        icon: 'calendar-clock',
        tone: 'text-amber-600',
        bg: 'bg-amber-50',
    },
]);
</script>

<template>
  <section class="card p-5">
    <header class="mb-4 flex flex-wrap items-end justify-between gap-2">
      <div>
        <p class="text-[10px] font-semibold uppercase tracking-[0.14em] text-brand/80">
          Nhịp công việc
        </p>
        <h2 class="font-display text-base font-semibold text-slate-800">
          Hôm nay
        </h2>
      </div>
      <span class="text-xs text-slate-400">{{ today }}</span>
    </header>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-4">
      <!-- Báo cáo ngày — ring -->
      <div class="flex items-center gap-4 rounded-xl border border-slate-100 bg-slate-50/60 p-4">
        <ProgressRing
          :value="reportRate"
          :size="68"
          :stroke="7"
        />
        <div class="min-w-0">
          <p class="text-xs font-medium text-slate-500">
            Báo cáo ngày
          </p>
          <p class="font-display text-lg font-bold text-slate-800">
            {{ reported }}<span class="text-sm font-medium text-slate-400">/{{ expected }}</span>
          </p>
          <div class="mt-1 flex flex-wrap gap-1">
            <span
              v-if="pulse.pendingReview"
              class="inline-flex items-center rounded-full bg-amber-100 px-2 py-0.5 text-[10px] font-semibold text-amber-700"
            >{{ pulse.pendingReview }} chờ duyệt</span>
            <span
              v-if="pulse.lateToday"
              class="inline-flex items-center rounded-full bg-rose-100 px-2 py-0.5 text-[10px] font-semibold text-rose-700"
            >{{ pulse.lateToday }} nộp trễ</span>
          </div>
        </div>
      </div>

      <!-- Task tiles -->
      <div
        v-for="tile in tiles"
        :key="tile.key"
        class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/60 p-4"
      >
        <div class="flex items-start justify-between">
          <p class="text-xs font-medium text-slate-500">
            {{ tile.label }}
          </p>
          <span :class="[tile.bg, 'rounded-lg p-1.5']">
            <AppIcon
              :name="tile.icon"
              :size="15"
              :class="tile.tone"
            />
          </span>
        </div>
        <div>
          <p :class="['font-display text-2xl font-bold', tile.tone]">
            {{ tile.value }}
          </p>
          <p class="text-[11px] text-slate-400">
            {{ tile.sub }}
          </p>
        </div>
      </div>

      <!-- Giờ log — sparkline -->
      <div class="flex flex-col justify-between rounded-xl border border-slate-100 bg-slate-50/60 p-4">
        <div class="flex items-start justify-between">
          <p class="text-xs font-medium text-slate-500">
            Giờ làm hôm nay
          </p>
          <span class="rounded-lg bg-sky-50 p-1.5">
            <AppIcon
              name="worklog"
              :size="15"
              class="text-sky-600"
            />
          </span>
        </div>
        <div class="mt-1 flex items-end justify-between gap-2">
          <div>
            <p class="font-display text-2xl font-bold text-sky-700">
              {{ pulse.hoursToday ?? 0 }}<span class="text-sm font-medium text-slate-400">h</span>
            </p>
            <p class="text-[11px] text-slate-400">
              {{ pulse.hoursThisWeek ?? 0 }}h · {{ costLabel }}đ / tuần
            </p>
          </div>
          <Sparkline
            :values="hoursValues"
            :width="96"
            :height="34"
            color="#0284c7"
            fill="rgba(2,132,199,0.10)"
          />
        </div>
      </div>
    </div>
  </section>
</template>
