<script setup>
import { computed } from 'vue';

const props = defineProps({
    series: { type: Array, default: () => [] },
    height: { type: Number, default: 120 },
});

const width = 280;
const pad = { t: 8, r: 8, b: 20, l: 28 };

const chart = computed(() => {
    const data = props.series || [];
    if (!data.length) return null;

    const maxY = Math.max(...data.map((d) => Math.max(d.remaining, d.ideal)), 1);
    const innerW = width - pad.l - pad.r;
    const innerH = props.height - pad.t - pad.b;
    const step = innerW / Math.max(data.length - 1, 1);

    const px = (i, val) => ({
        x: pad.l + i * step,
        y: pad.t + innerH - (val / maxY) * innerH,
    });

    const actual = data.map((d, i) => px(i, d.remaining));
    const ideal = data.map((d, i) => px(i, d.ideal));

    const line = (pts) => pts.map((p, i) => `${i === 0 ? 'M' : 'L'}${p.x.toFixed(1)},${p.y.toFixed(1)}`).join(' ');

    return {
        actualPath: line(actual),
        idealPath: line(ideal),
        labels: data.map((d, i) => ({ ...d, ...px(i, 0), show: i % 2 === 0 || i === data.length - 1 })),
        maxY,
    };
});
</script>

<template>
  <div
    v-if="chart"
    class="w-full"
  >
    <svg
      :viewBox="`0 0 ${width} ${height}`"
      class="w-full"
      role="img"
      aria-label="Biểu đồ burndown"
    >
      <line
        v-for="tick in 4"
        :key="tick"
        :x1="pad.l"
        :x2="width - pad.r"
        :y1="pad.t + ((height - pad.t - pad.b) / 4) * (tick - 1)"
        :y2="pad.t + ((height - pad.t - pad.b) / 4) * (tick - 1)"
        class="stroke-slate-100 dark:stroke-slate-700"
        stroke-width="1"
      />
      <path
        :d="chart.idealPath"
        fill="none"
        class="stroke-slate-300 dark:stroke-slate-600"
        stroke-width="1.5"
        stroke-dasharray="4 3"
      />
      <path
        :d="chart.actualPath"
        fill="none"
        class="stroke-brand"
        stroke-width="2"
      />
      <text
        v-for="(l, i) in chart.labels.filter((x) => x.show)"
        :key="i"
        :x="l.x"
        :y="height - 4"
        text-anchor="middle"
        class="fill-slate-400 text-[9px]"
      >
        {{ l.label }}
      </text>
    </svg>
    <div class="mt-1 flex gap-3 text-[10px] text-slate-500">
      <span class="inline-flex items-center gap-1"><span class="h-0.5 w-3 bg-brand" /> Thực tế</span>
      <span class="inline-flex items-center gap-1"><span class="h-0.5 w-3 border-t border-dashed border-slate-400" /> Lý tưởng</span>
    </div>
  </div>
  <p
    v-else
    class="text-xs text-slate-400"
  >
    Chưa đủ dữ liệu burndown.
  </p>
</template>
