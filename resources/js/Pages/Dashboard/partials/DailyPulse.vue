<script setup>
import { computed } from 'vue';
import KpiSummaryStrip from '@/shared/ui/KpiSummaryStrip.vue';
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
    if (v >= 1_000_000) return `${(v / 1_000_000).toFixed(1).replace('.0', '')} triệu`;
    return Number(v).toLocaleString('vi-VN');
});

const reportSub = computed(() => {
    const parts = [];
    if (props.pulse.pendingReview) parts.push(`${props.pulse.pendingReview} chờ duyệt`);
    if (props.pulse.lateToday) parts.push(`${props.pulse.lateToday} nộp trễ`);
    return parts.length ? parts.join(' · ') : 'Phòng Công nghệ — hôm nay';
});

const cards = computed(() => [
    {
        key: 'report',
        label: 'Báo cáo ngày',
        value: reported.value,
        suffix: `/${expected.value}`,
        suffixClass: 'text-base font-medium text-slate-400',
        tone: 'brand',
        icon: 'daily',
        sub: reportSub.value,
        progress: reportRate.value,
    },
    {
        key: 'done',
        label: 'Hoàn thành hôm nay',
        value: props.pulse.completedToday ?? 0,
        tone: 'emerald',
        icon: 'check-circle',
        sub: 'Công việc đã xong trong ngày',
    },
    {
        key: 'due',
        label: 'Đến hạn hôm nay',
        value: props.pulse.dueToday ?? 0,
        tone: 'amber',
        icon: 'calendar-clock',
        sub: `${props.pulse.overdue ?? 0} công việc đang quá hạn`,
    },
    {
        key: 'hours',
        label: 'Giờ làm hôm nay',
        value: props.pulse.hoursToday ?? 0,
        suffix: 'h',
        suffixClass: 'text-lg font-medium text-slate-400',
        tone: 'sky',
        icon: 'worklog',
        sub: `${props.pulse.hoursThisWeek ?? 0}h · ${costLabel.value}đ / tuần`,
    },
]);
</script>

<template>
  <KpiSummaryStrip
    :cards="cards"
    :progress-denominator="100"
    eyebrow="Nhịp công việc"
    heading="Hôm nay"
    :hint="today"
    aria-label="Nhịp công việc hôm nay"
    grid-class="grid-cols-1 sm:grid-cols-2 xl:grid-cols-4"
    shell-class="kpi-strip relative mb-4 overflow-x-hidden rounded-card border border-slate-200/80 bg-gradient-to-b from-slate-50/90 to-white px-4 py-4 shadow-sm sm:px-5 sm:py-5"
  >
    <template #value="{ card }">
      <div
        v-if="card.key === 'report'"
        class="flex items-center gap-3"
      >
        <ProgressRing
          :value="reportRate"
          :size="52"
          :stroke="6"
        />
        <span class="font-display text-2xl font-bold tabular-nums text-brand sm:text-[1.65rem]">
          {{ reported }}<span class="text-lg font-medium text-slate-400">/{{ expected }}</span>
        </span>
      </div>
      <span
        v-else
        class="font-display text-2xl font-bold tabular-nums leading-none tracking-tight sm:text-[1.65rem]"
        :class="card.tone === 'brand' ? 'text-brand' : 'text-slate-900'"
      >
        {{ card.value }}
        <span
          v-if="card.suffix"
          :class="card.suffixClass"
        >{{ card.suffix }}</span>
      </span>
    </template>

    <template #footer="{ card }">
      <div
        v-if="card.key === 'hours'"
        class="mt-2 flex justify-end"
      >
        <Sparkline
          :values="hoursValues"
          :width="112"
          :height="36"
          color="#0284c7"
          fill="rgba(2,132,199,0.10)"
        />
      </div>
    </template>
  </KpiSummaryStrip>
</template>
