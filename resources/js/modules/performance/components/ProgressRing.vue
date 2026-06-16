<script setup>
import { computed } from 'vue';

/**
 * Vòng tiến độ SVG (progress ring). Có slot trung tâm để hiển thị số/điểm.
 */
const props = defineProps({
    value: { type: Number, default: 0 },
    size: { type: Number, default: 64 },
    stroke: { type: Number, default: 6 },
    color: { type: String, default: '#9A0036' },
    track: { type: String, default: '#e2e8f0' },
    showLabel: { type: Boolean, default: true },
});

const clamped = computed(() => Math.max(0, Math.min(100, Math.round(props.value))));
const radius = computed(() => (props.size - props.stroke) / 2);
const circumference = computed(() => 2 * Math.PI * radius.value);
const offset = computed(() => circumference.value * (1 - clamped.value / 100));
const center = computed(() => props.size / 2);
</script>

<template>
  <span
    class="relative inline-grid place-items-center"
    :style="{ width: size + 'px', height: size + 'px' }"
  >
    <svg
      :width="size"
      :height="size"
      :viewBox="`0 0 ${size} ${size}`"
      class="-rotate-90"
      aria-hidden="true"
    >
      <circle
        :cx="center"
        :cy="center"
        :r="radius"
        :stroke="track"
        :stroke-width="stroke"
        fill="none"
      />
      <circle
        :cx="center"
        :cy="center"
        :r="radius"
        :stroke="color"
        :stroke-width="stroke"
        fill="none"
        stroke-linecap="round"
        :stroke-dasharray="circumference"
        :stroke-dashoffset="offset"
        class="transition-[stroke-dashoffset] duration-700 ease-out"
      />
    </svg>
    <span class="absolute inset-0 grid place-items-center">
      <slot>
        <span
          v-if="showLabel"
          class="font-display font-bold tabular-nums"
          :style="{ fontSize: Math.round(size * 0.26) + 'px', color }"
        >{{ clamped }}<span class="text-[0.7em]">%</span></span>
      </slot>
    </span>
  </span>
</template>
