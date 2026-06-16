<script setup>
import { computed, toRef } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useCountUp } from '@/shared/composables/useCountUp.js';
import { toneSoft, tailwindToHex } from '../composables/useChartTheme.js';
import ProgressRing from './ProgressRing.vue';
import Sparkline from './Sparkline.vue';

const props = defineProps({
    card: { type: Object, required: true },
    spark: { type: Array, default: null },
});

const decimals = computed(() => Number.isInteger(props.card.value) ? 0 : 1);
const { display } = useCountUp(toRef(() => props.card.value ?? 0), { decimals: decimals.value });

const chip = computed(() => toneSoft[props.card.tone] ?? toneSoft.brand);
const ringColor = computed(() => tailwindToHex(props.card.tone));

const trendTone = computed(() => {
    if (props.card.trend === 'up') return 'text-emerald-600';
    if (props.card.trend === 'down') return 'text-rose-600';
    return 'text-slate-400';
});
const trendArrow = computed(() => ({ up: '↑', down: '↓' }[props.card.trend] ?? '→'));
const showDelta = computed(() => props.card.delta !== undefined && props.card.delta !== null);
</script>

<template>
  <div class="card relative flex flex-col gap-3 overflow-hidden p-4">
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <p class="truncate text-[11px] font-semibold uppercase tracking-wide text-slate-400">
          {{ card.label }}
        </p>
        <div class="mt-1 flex items-baseline gap-1">
          <span class="font-display text-2xl font-bold tabular-nums text-slate-900">
            {{ display }}
          </span>
          <span
            v-if="card.suffix"
            class="text-sm font-semibold text-slate-400"
          >{{ card.suffix }}</span>
        </div>
      </div>

      <span
        class="grid h-9 w-9 shrink-0 place-items-center rounded-xl"
        :class="chip"
      >
        <AppIcon
          :name="card.icon"
          :size="17"
        />
      </span>
    </div>

    <div class="flex items-end justify-between gap-2">
      <p
        v-if="showDelta"
        class="text-[11px] font-medium"
        :class="trendTone"
      >
        {{ trendArrow }} {{ card.delta > 0 ? '+' : '' }}{{ card.delta }} so với kỳ trước
      </p>
      <p
        v-else
        class="text-[11px] text-slate-300"
      >
        &nbsp;
      </p>

      <ProgressRing
        v-if="card.ring !== undefined && card.ring !== null"
        :value="card.ring"
        :size="40"
        :stroke="4"
        :color="ringColor"
      />
      <Sparkline
        v-else-if="spark && spark.length"
        :values="spark"
        :color="ringColor"
        :width="84"
        :height="26"
      />
    </div>
  </div>
</template>
