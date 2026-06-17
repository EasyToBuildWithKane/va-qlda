<script setup>
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

defineProps({
    forId: { type: String, default: null },
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    tooltip: { type: String, default: '' },
    wide: { type: Boolean, default: false },
    compact: { type: Boolean, default: false },
});
</script>

<template>
  <div
    class="min-w-0"
    :class="compact ? 'grid grid-cols-[minmax(0,1fr)_1.125rem] items-center gap-1' : 'mb-1 flex items-center gap-1.5'"
  >
    <label
      :for="forId"
      :class="compact
        ? 'text-xs font-medium leading-snug text-slate-500'
        : 'text-sm font-medium text-slate-700'"
    >
      {{ label }}
      <span
        v-if="required"
        class="ml-0.5 text-danger"
        aria-hidden="true"
        title="Bắt buộc"
      >*</span>
    </label>
    <FieldTooltip
      v-if="tooltip"
      :text="tooltip"
      :wide="wide"
      class="shrink-0 justify-self-end"
    />
    <span
      v-else-if="compact"
      class="shrink-0"
      aria-hidden="true"
    />
  </div>
</template>
