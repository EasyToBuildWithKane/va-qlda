<script setup>
import { computed, ref, watch } from 'vue';
import {
    Chart as ChartJS,
    CategoryScale,
    LinearScale,
    LineElement,
    PointElement,
    Tooltip,
    Filler,
} from 'chart.js';
import { Line } from 'vue-chartjs';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import AiDashboardChartPanel from '@/modules/aiAccount/components/dashboard/AiDashboardChartPanel.vue';
import {
    SERIES_STYLES,
    baseCartesianScales,
    baseChartPlugins,
    moneyTooltipLabel,
} from '@/modules/aiAccount/components/dashboard/aiDashboardChartTheme';

ChartJS.register(CategoryScale, LinearScale, LineElement, PointElement, Tooltip, Filler);

const props = defineProps({
    series: { type: Object, default: null },
    loading: { type: Boolean, default: false },
    granularity: { type: String, default: 'month' },
    comparePreviousYear: { type: Boolean, default: true },
});

const emit = defineEmits(['update:granularity', 'update:comparePreviousYear']);

const GRANULARITY_OPTS = [
    { key: 'day', label: 'Ngày' },
    { key: 'month', label: 'Tháng' },
    { key: 'quarter', label: 'Quý' },
    { key: 'year', label: 'Năm' },
];

const datasetsMeta = computed(() => props.series?.datasets ?? []);

const toggles = ref({});

watch(
    datasetsMeta,
    (rows) => {
        const next = { ...toggles.value };
        for (const ds of rows) {
            const key = ds.key ?? ds.label;
            if (next[key] === undefined) {
                next[key] = true;
            }
        }
        toggles.value = next;
    },
    { immediate: true },
);

function toggleSeries(key) {
    const activeCount = Object.entries(toggles.value).filter(([k, on]) => on && datasetsMeta.value.some((d) => (d.key ?? d.label) === k)).length;
    if (toggles.value[key] && activeCount <= 1) return;
    toggles.value = { ...toggles.value, [key]: !toggles.value[key] };
}

const hasData = computed(() => {
    const rows = props.series?.datasets ?? [];
    return rows.some((ds) => (ds.data ?? []).some((v) => Number(v) > 0));
});

const chartData = computed(() => {
    const labels = props.series?.labels ?? [];
    const datasets = (props.series?.datasets ?? [])
        .filter((ds) => toggles.value[ds.key ?? ds.label] !== false)
        .map((ds) => {
            const key = ds.key ?? 'actual';
            const style = SERIES_STYLES[key] ?? SERIES_STYLES.actual;
            return {
                label: ds.label ?? style.label,
                data: ds.data ?? [],
                borderColor: style.color,
                backgroundColor: style.fillArea ? style.fill : style.fill,
                fill: style.fillArea,
                tension: 0.35,
                pointRadius: labels.length > 14 ? 0 : 3,
                pointHoverRadius: 6,
                pointBackgroundColor: style.color,
                pointBorderColor: '#fff',
                pointBorderWidth: 2,
                borderWidth: style.borderWidth,
                borderDash: style.borderDash,
            };
        });

    return { labels, datasets };
});

const options = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: { mode: 'index', intersect: false },
    plugins: {
        ...baseChartPlugins,
        tooltip: {
            ...baseChartPlugins.tooltip,
            callbacks: { label: moneyTooltipLabel },
        },
    },
    scales: baseCartesianScales,
};

const seriesChips = computed(() =>
    (props.series?.datasets ?? []).map((ds) => {
        const key = ds.key ?? ds.label;
        const style = SERIES_STYLES[key] ?? SERIES_STYLES.actual;
        const total = (ds.data ?? []).reduce((s, v) => s + Number(v || 0), 0);
        return { key, label: style.label, color: style.color, total };
    }),
);
</script>

<template>
  <AiDashboardChartPanel
    title="Chi phí theo thời gian"
    subtitle="ĐNTT đã thanh toán so với ngân sách vận hành từ PĐX"
    icon="budget"
    :loading="loading"
    :empty="!loading && !hasData"
    empty-title="Chưa có chi phí theo thời gian"
    empty-description="Biểu đồ hiển thị khi có đề nghị thanh toán đã ghi nhận trong kỳ bạn chọn."
    empty-icon="budget"
  >
    <template #toolbar>
      <DatagridSegmentedControl
        :model-value="granularity"
        :items="GRANULARITY_OPTS"
        aria-label="Chọn độ chi tiết thời gian"
        icon-only-below-sm
        @update:model-value="emit('update:granularity', $event)"
      />
      <label class="inline-flex h-10 cursor-pointer items-center gap-2 rounded-lg border border-slate-200 bg-slate-50 px-3 text-xs text-slate-600">
        <input
          type="checkbox"
          class="rounded border-slate-300 text-brand focus:ring-brand/30"
          :checked="comparePreviousYear"
          @change="emit('update:comparePreviousYear', $event.target.checked)"
        >
        So sánh năm trước
      </label>
    </template>

    <div class="flex h-full min-h-0 flex-col">
      <div
        v-if="seriesChips.length"
        class="mb-3 flex flex-wrap gap-2"
        role="group"
        aria-label="Bật hoặc tắt từng chuỗi dữ liệu"
      >
        <button
          v-for="chip in seriesChips"
          :key="chip.key"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-full border px-3 py-1 text-[11px] font-medium transition"
          :class="toggles[chip.key]
            ? 'border-slate-200 bg-white text-slate-700 shadow-sm'
            : 'border-transparent bg-slate-100 text-slate-400'"
          :aria-pressed="!!toggles[chip.key]"
          @click="toggleSeries(chip.key)"
        >
          <span
            class="h-2 w-2 rounded-full"
            :style="{ backgroundColor: toggles[chip.key] ? chip.color : '#cbd5e1' }"
          />
          {{ chip.label }}
        </button>
      </div>
      <div class="min-h-0 flex-1">
        <Line
          :data="chartData"
          :options="options"
        />
      </div>
    </div>
  </AiDashboardChartPanel>
</template>
