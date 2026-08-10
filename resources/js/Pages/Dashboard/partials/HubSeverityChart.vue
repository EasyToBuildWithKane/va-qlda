<script setup>
/**
 * Test case đang mở theo mức độ — bar ngang, bấm để mở /blockers đã lọc.
 */
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Chart as ChartJS, CategoryScale, LinearScale, BarElement, Tooltip,
} from 'chart.js';
import { Bar } from 'vue-chartjs';
import AppIcon from '@/Components/AppIcon.vue';

ChartJS.register(CategoryScale, LinearScale, BarElement, Tooltip);

const props = defineProps({
    items: { type: Array, default: () => [] },
});

const colorMap = {
    slate: '#94a3b8', sky: '#0ea5e9', amber: '#f59e0b', rose: '#f43f5e',
};
const hex = (c) => colorMap[c] ?? '#94a3b8';

const total = computed(() => props.items.reduce((s, r) => s + (r.total ?? 0), 0));

const data = computed(() => ({
    labels: props.items.map((r) => r.label),
    datasets: [{
        data: props.items.map((r) => r.total),
        backgroundColor: props.items.map((r) => hex(r.color)),
        borderRadius: 6,
        barThickness: 18,
        maxBarThickness: 22,
    }],
}));

function navigate(index) {
    const row = props.items[index];
    if (!row) return;
    router.get('/blockers', { severity: row.severity }, { preserveScroll: true });
}

const options = computed(() => ({
    indexAxis: 'y',
    responsive: true,
    maintainAspectRatio: false,
    onHover: (e, els) => { e.native.target.style.cursor = els.length ? 'pointer' : 'default'; },
    onClick: (e, els) => { if (els.length) navigate(els[0].index); },
    plugins: {
        legend: { display: false },
        tooltip: { callbacks: { label: (ctx) => ` ${ctx.parsed.x} test case` } },
    },
    scales: {
        x: { beginAtZero: true, ticks: { precision: 0, font: { size: 10 } }, grid: { color: 'rgba(148,163,184,0.16)' } },
        y: { grid: { display: false }, ticks: { font: { size: 11 } } },
    },
}));
</script>

<template>
  <div class="card flex flex-col p-5">
    <div class="mb-4 flex items-center justify-between">
      <h3 class="flex items-center gap-2 font-display font-semibold text-slate-800">
        <AppIcon
          name="blockers"
          :size="16"
          class="text-amber-600"
        />
        Test case theo mức độ
      </h3>
      <span class="rounded-full bg-amber-50 px-2 py-0.5 text-[11px] font-semibold text-amber-700 ring-1 ring-amber-200/70">
        {{ total }} đang mở
      </span>
    </div>

    <div class="h-44 flex-1">
      <Bar
        v-if="total > 0"
        :data="data"
        :options="options"
      />
      <div
        v-else
        class="flex h-full items-center justify-center text-sm text-emerald-600"
      >
        Không có test case nào đang mở 🎉
      </div>
    </div>
  </div>
</template>
