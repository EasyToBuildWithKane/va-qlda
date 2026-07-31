<script setup>
import { computed } from 'vue';
import { displayOrEmpty, EMPTY_LABELS } from '@/shared/utils/emptyDisplay';

const props = defineProps({
    level: { type: Object, default: null },
});

function formatScoreWeight(weight) {
    const n = Number(weight);
    if (!Number.isFinite(n)) return '';
    return n > 0 ? `+${n}` : String(n);
}

const cell = computed(() => {
    if (!props.level) return null;
    return {
        label: props.level.label || '',
        description: String(props.level.description || '').trim(),
        weightText: formatScoreWeight(props.level.weight) || '0',
    };
});
</script>

<template>
  <div
    v-if="cell"
    class="flex flex-col gap-0.5 text-center"
  >
    <span
      class="line-clamp-2 text-[12px] font-medium leading-snug text-slate-800"
      :title="cell.label || undefined"
    >
      {{ displayOrEmpty(cell.label, EMPTY_LABELS.notUpdated) }}
    </span>
    <span
      v-if="cell.description"
      class="line-clamp-2 text-[11px] leading-snug text-slate-500"
      :title="cell.description"
    >
      {{ cell.description }}
    </span>
    <span class="mt-0.5 text-[12px] font-semibold tabular-nums text-brand">
      {{ cell.weightText }}
    </span>
  </div>
  <span
    v-else
    class="block text-center text-xs text-slate-400"
  >{{ EMPTY_LABELS.notUpdated }}</span>
</template>
