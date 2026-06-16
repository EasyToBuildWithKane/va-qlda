<script setup>
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

defineProps({
    forId: { type: String, default: null },
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    tooltip: { type: String, default: '' },
    wide: { type: Boolean, default: false },
});
</script>

<template>
  <div class="mb-1 flex items-center gap-1.5">
    <label
      :for="forId"
      class="text-sm font-medium text-slate-700"
    >
      {{ label }}
      <span
        v-if="required"
        class="ml-0.5 text-danger"
        aria-hidden="true"
      >*</span>
    </label>
    <FieldTooltip
      v-if="tooltip"
      :text="tooltip"
      :wide="wide"
    />
  </div>
</template>
