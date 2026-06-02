<script setup>
import { computed } from 'vue';

const props = defineProps({
    value: { type: Number, default: 0 },
    showLabel: { type: Boolean, default: true },
    height: { type: String, default: 'h-2' },
    color: { type: String, default: null },
});

const pct = computed(() => {
    const n = Number(props.value);
    const safe = Number.isFinite(n) ? n : 0;
    return Math.max(0, Math.min(100, Math.round(safe)));
});

const fill = computed(() => {
    if (props.color) return props.color;
    if (pct.value >= 100) return 'bg-emerald-500';
    if (pct.value >= 60) return 'bg-sky-500';
    if (pct.value >= 30) return 'bg-amber-500';
    return 'bg-rose-400';
});
</script>

<template>
  <div class="flex items-center gap-2">
    <div
      class="flex-1 rounded-full bg-slate-100 overflow-hidden"
      :class="height"
    >
      <div
        class="h-full rounded-full transition-all duration-300"
        :class="fill"
        :style="{ width: pct + '%' }"
      />
    </div>
    <span
      v-if="showLabel"
      class="w-9 text-right text-xs font-medium text-slate-500"
    >{{ pct }}%</span>
  </div>
</template>
