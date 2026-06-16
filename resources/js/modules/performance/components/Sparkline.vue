<script setup>
import { computed } from 'vue';

/**
 * Sparkline SVG nhẹ (không tạo chart.js instance) — dùng trong KPI card.
 */
const props = defineProps({
    values: { type: Array, default: () => [] },
    color: { type: String, default: '#9A0036' },
    width: { type: Number, default: 96 },
    height: { type: Number, default: 28 },
});

const points = computed(() => {
    const vals = props.values.map((v) => Number(v) || 0);
    if (vals.length === 0) return null;
    if (vals.length === 1) vals.unshift(vals[0]);

    const max = Math.max(...vals);
    const min = Math.min(...vals);
    const span = max - min || 1;
    const stepX = props.width / (vals.length - 1);
    const pad = 3;
    const h = props.height - pad * 2;

    return vals.map((v, i) => {
        const x = i * stepX;
        const y = pad + h - ((v - min) / span) * h;
        return [x, y];
    });
});

const linePath = computed(() => points.value
    ? points.value.map(([x, y], i) => `${i === 0 ? 'M' : 'L'}${x.toFixed(1)},${y.toFixed(1)}`).join(' ')
    : '');

const areaPath = computed(() => points.value
    ? `${linePath.value} L${props.width},${props.height} L0,${props.height} Z`
    : '');

const lastPoint = computed(() => points.value ? points.value[points.value.length - 1] : null);
</script>

<template>
  <svg
    v-if="points"
    :width="width"
    :height="height"
    :viewBox="`0 0 ${width} ${height}`"
    preserveAspectRatio="none"
    aria-hidden="true"
    class="overflow-visible"
  >
    <path
      :d="areaPath"
      :fill="color"
      fill-opacity="0.1"
    />
    <path
      :d="linePath"
      fill="none"
      :stroke="color"
      stroke-width="1.75"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <circle
      v-if="lastPoint"
      :cx="lastPoint[0]"
      :cy="lastPoint[1]"
      r="2"
      :fill="color"
    />
  </svg>
</template>
