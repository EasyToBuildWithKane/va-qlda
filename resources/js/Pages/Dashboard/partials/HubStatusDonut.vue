<script setup>
/**
 * Donut phân bố trạng thái — tương tác:
 *  • Hover hiện tooltip kèm %.
 *  • Bấm vào lát / dòng chú thích → điều hướng tới module đã lọc.
 */
import { computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Chart as ChartJS, ArcElement, Tooltip } from 'chart.js';
import { Doughnut } from 'vue-chartjs';
import AppIcon from '@/Components/AppIcon.vue';

ChartJS.register(ArcElement, Tooltip);

const props = defineProps({
    title: { type: String, required: true },
    icon: { type: String, default: 'projects' },
    items: { type: Array, default: () => [] },
    keyField: { type: String, default: 'status' },
    /** Đường dẫn module để điều hướng khi bấm (vd. /projects). */
    baseHref: { type: String, default: '' },
    /** Tên tham số lọc (vd. status). */
    paramKey: { type: String, default: 'status' },
    centerLabel: { type: String, default: '' },
    emptyText: { type: String, default: 'Chưa có dữ liệu' },
});

const colorMap = {
    slate: '#94a3b8', sky: '#0ea5e9', violet: '#8b5cf6',
    emerald: '#10b981', rose: '#f43f5e', amber: '#f59e0b', brand: '#9A0036',
};
const hex = (c) => colorMap[c] ?? '#94a3b8';

const rows = computed(() => props.items.filter((r) => (r.total ?? 0) > 0));
const total = computed(() => rows.value.reduce((s, r) => s + (r.total ?? 0), 0));

const data = computed(() => ({
    labels: rows.value.map((r) => r.label),
    datasets: [{
        data: rows.value.map((r) => r.total),
        backgroundColor: rows.value.map((r) => hex(r.color)),
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 7,
    }],
}));

function navigate(row) {
    if (!props.baseHref) return;
    router.get(props.baseHref, { [props.paramKey]: row[props.keyField] }, {
        preserveScroll: true,
    });
}

const options = computed(() => ({
    responsive: true,
    maintainAspectRatio: false,
    cutout: '66%',
    onHover: (e, els) => {
        e.native.target.style.cursor = els.length && props.baseHref ? 'pointer' : 'default';
    },
    onClick: (e, els) => {
        if (els.length) navigate(rows.value[els[0].index]);
    },
    plugins: {
        legend: { display: false },
        tooltip: {
            callbacks: {
                label: (ctx) => {
                    const pct = total.value ? Math.round((ctx.parsed / total.value) * 100) : 0;
                    return ` ${ctx.label}: ${ctx.parsed} (${pct}%)`;
                },
            },
        },
    },
}));

function pct(row) {
    return total.value ? Math.round((row.total / total.value) * 100) : 0;
}
</script>

<template>
  <div class="card flex flex-col p-5">
    <h3 class="mb-4 flex items-center gap-2 font-display font-semibold text-slate-800">
      <AppIcon
        :name="icon"
        :size="16"
        class="text-brand"
      />
      {{ title }}
    </h3>

    <div
      v-if="total > 0"
      class="flex flex-1 flex-col items-center gap-4 sm:flex-row"
    >
      <div class="relative h-40 w-40 shrink-0">
        <Doughnut
          :data="data"
          :options="options"
        />
        <div class="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
          <p class="font-display text-2xl font-bold tabular-nums text-slate-900">
            {{ total }}
          </p>
          <p class="text-[10px] uppercase tracking-wide text-slate-400">
            {{ centerLabel }}
          </p>
        </div>
      </div>

      <ul class="w-full flex-1 space-y-1.5">
        <li
          v-for="row in rows"
          :key="row[keyField]"
        >
          <component
            :is="baseHref ? 'button' : 'div'"
            type="button"
            class="group flex w-full items-center gap-2.5 rounded-lg px-2 py-1.5 text-left transition"
            :class="baseHref ? 'hover:bg-slate-50' : ''"
            @click="navigate(row)"
          >
            <span
              class="h-2.5 w-2.5 shrink-0 rounded-full"
              :style="{ backgroundColor: hex(row.color) }"
            />
            <span class="min-w-0 flex-1 truncate text-[13px] text-slate-600 group-hover:text-slate-900">
              {{ row.label }}
            </span>
            <span class="shrink-0 font-display text-sm font-semibold tabular-nums text-slate-800">
              {{ row.total }}
            </span>
            <span class="w-9 shrink-0 text-right text-[11px] tabular-nums text-slate-400">
              {{ pct(row) }}%
            </span>
          </component>
        </li>
      </ul>
    </div>

    <div
      v-else
      class="flex flex-1 items-center justify-center py-8 text-sm text-slate-400"
    >
      {{ emptyText }}
    </div>
  </div>
</template>
