<script setup>
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

defineProps({
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    tooltip: { type: String, default: '' },
    hint: { type: String, default: '' },
    wide: { type: Boolean, default: true },
});
</script>

<template>
  <label class="label mb-1 flex flex-wrap items-center gap-1">
    <span>{{ label }}</span>
    <span
      v-if="required"
      class="text-danger"
      aria-hidden="true"
    >*</span>
    <FieldTooltip
      v-if="tooltip"
      :text="tooltip"
      :wide="wide"
    />
    <span
      v-if="hint"
      class="w-full text-xs font-normal text-slate-400 sm:w-auto"
    >{{ hint }}</span>
  </label>
</template>
