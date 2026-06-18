<script setup>
/**
 * Biểu đồ xu hướng hoạt động — tương tác:
 *  • Chuyển khoảng thời gian 7 / 14 / 30 ngày (cắt client-side).
 *  • Bật/tắt từng chuỗi (Hoàn thành · Tạo mới) bằng chip.
 * Nhận đủ 30 ngày từ controller, không round-trip server.
 */
import { computed, ref } from 'vue';
import {
    Chart as ChartJS, CategoryScale, LinearScale,
    LineElement, PointElement, Tooltip, Filler,
} from 'chart.js';
import { Line } from 'vue-chartjs';
import AppIcon from '@/Components/AppIcon.vue';

ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Filler);

const props = defineProps({
    trend: { type: Array, default: () => [] },
});

const BRAND = '#9A0036';
const EMERALD = '#10b981';

const ranges = [
    { value: 7, label: '7 ngày' },
    { value: 14, label: '14 ngày' },
    { value: 30, label: '30 ngày' },
];
const days = ref(14);

const series = [
    { key: 'completed', label: 'Hoàn thành', color: BRAND },
    { key: 'created', label: 'Tạo mới', color: EMERALD },
];
const active = ref({ completed: true, created: true });

function toggle(key) {
    // Không cho tắt cả hai chuỗi.
    if (active.value[key] && Object.values(active.value).filter(Boolean).length === 1) return;
    active.value = { ...active.value, [key]: !active.value[key] };
}

const windowed = computed(() => props.trend.slice(-days.value));

const totals = computed(() => ({
    completed: windowed.value.reduce((s, r) => s + (r.completed ?? 0), 0),
    created: windowed.value.reduce((s, r) => s + (r.created ?? 0), 0),
}));

const data = computed(() => ({
    labels: windowed.value.map((r) => r.label),
    datasets: series
        .filter((s) => active.value[s.key])
        .map((s) => ({
            label: s.label,
            data: windowed.value.map((r) => r[s.key] ?? 0),
            borderColor: s.color,
            backgroundColor: s.key === 'completed' ? 'rgba(154,0,54,0.08)' : 'rgba(16,185,129,0.07)',
            tension: 0.4,
            fill: true,
            pointBackgroundColor: s.color,
            pointRadius: days.value > 14 ? 0 : 2,
            pointHoverRadius: 5,
            borderWidth: 2,
        })),
}));

const options = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        legend: { display: false },
        tooltip: {
            padding: 10,
            boxPadding: 4,
            callbacks: { label: (ctx) => ` ${ctx.dataset.label}: ${ctx.parsed.y} công việc` },
        },
    },
    scales: {
        x: { grid: { display: false }, ticks: { maxTicksLimit: 10, font: { size: 10 } } },
        y: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(148,163,184,0.18)' } },
    },
};

const hasData = computed(() => props.trend.length > 0);
</script>

<template>
  <div class="card flex flex-col p-5">
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
      <h3 class="flex items-center gap-2 font-display font-semibold text-slate-800">
        <AppIcon
          name="performance"
          :size="16"
          class="text-brand"
        />
        Xu hướng công việc
      </h3>

      <!-- Period segmented control -->
      <div
        class="inline-flex rounded-lg border border-slate-200 bg-slate-50 p-0.5"
        role="group"
        aria-label="Chọn khoảng thời gian"
      >
        <button
          v-for="r in ranges"
          :key="r.value"
          type="button"
          class="rounded-md px-2.5 py-1 text-[11px] font-semibold transition"
          :class="days === r.value
            ? 'bg-white text-brand shadow-sm ring-1 ring-slate-200'
            : 'text-slate-500 hover:text-slate-700'"
          :aria-pressed="days === r.value"
          @click="days = r.value"
        >
          {{ r.label }}
        </button>
      </div>
    </div>

    <!-- Series toggles -->
    <div class="mb-3 flex flex-wrap items-center gap-2">
      <button
        v-for="s in series"
        :key="s.key"
        type="button"
        class="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-1 text-[11px] font-medium transition"
        :class="active[s.key]
          ? 'border-slate-200 bg-white text-slate-700 shadow-sm'
          : 'border-transparent bg-slate-100 text-slate-400'"
        :aria-pressed="active[s.key]"
        @click="toggle(s.key)"
      >
        <span
          class="h-2 w-2 rounded-full transition"
          :style="{ backgroundColor: active[s.key] ? s.color : '#cbd5e1' }"
        />
        {{ s.label }}
        <span class="tabular-nums font-semibold">{{ totals[s.key] }}</span>
      </button>
    </div>

    <div class="h-56 flex-1">
      <Line
        v-if="hasData"
        :data="data"
        :options="options"
      />
      <div
        v-else
        class="flex h-full items-center justify-center text-sm text-slate-400"
      >
        Chưa có dữ liệu hoạt động
      </div>
    </div>
  </div>
</template>
