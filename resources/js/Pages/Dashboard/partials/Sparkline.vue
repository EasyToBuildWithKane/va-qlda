<script setup>
import { computed } from 'vue';

const props = defineProps({
    /** Array of numeric values, oldest → newest. */
    values: { type: Array, default: () => [] },
    width: { type: Number, default: 120 },
    height: { type: Number, default: 36 },
    color: { type: String, default: '#9A0036' },
    fill: { type: String, default: 'rgba(154,0,54,0.10)' },
});

const nums = computed(() => props.values.map((v) => Number(v) || 0));
const max = computed(() => Math.max(1, ...nums.value));

const points = computed(() => {
    const n = nums.value.length;
    if (n === 0) return [];
    const pad = 2;
    const w = props.width - pad * 2;
    const h = props.height - pad * 2;
    const step = n > 1 ? w / (n - 1) : 0;
    return nums.value.map((v, i) => {
        const x = pad + i * step;
        const y = pad + h - (v / max.value) * h;
        return [Number(x.toFixed(2)), Number(y.toFixed(2))];
    });
});

const linePath = computed(() => points.value.map((p) => p.join(',')).join(' '));

const areaPath = computed(() => {
    if (!points.value.length) return '';
    const pad = 2;
    const baseline = props.height - pad;
    const first = points.value[0];
    const last = points.value[points.value.length - 1];
    return `${first[0]},${baseline} ${linePath.value} ${last[0]},${baseline}`;
});

const lastPoint = computed(() => points.value[points.value.length - 1] ?? null);
</script>

<template>
  <svg
    :width="width"
    :height="height"
    :viewBox="`0 0 ${width} ${height}`"
    class="overflow-visible"
    aria-hidden="true"
  >
    <polygon
      v-if="areaPath"
      :points="areaPath"
      :fill="fill"
      stroke="none"
    />
    <polyline
      v-if="linePath"
      :points="linePath"
      fill="none"
      :stroke="color"
      stroke-width="1.8"
      stroke-linecap="round"
      stroke-linejoin="round"
    />
    <circle
      v-if="lastPoint"
      :cx="lastPoint[0]"
      :cy="lastPoint[1]"
      r="2.4"
      :fill="color"
    />
  </svg>
</template>
