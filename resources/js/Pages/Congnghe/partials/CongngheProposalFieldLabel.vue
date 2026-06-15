<script setup>
import FieldTooltip from '@/shared/ui/FieldTooltip.vue';

defineProps({
    forId: { type: String, default: '' },
    label: { type: String, required: true },
    required: { type: Boolean, default: false },
    tooltip: { type: String, default: '' },
    hint: { type: String, default: '' },
});
</script>

<template>
  <label
    class="mb-1.5 flex flex-wrap items-center gap-1.5 text-xs font-semibold uppercase tracking-wide text-white/55"
    :for="forId || undefined"
  >
    <span>{{ label }}</span>
    <span
      v-if="required"
      class="text-[#ff6b9d]"
      aria-hidden="true"
    >*</span>
    <FieldTooltip
      v-if="tooltip"
      :text="tooltip"
      wide
    />
    <span
      v-if="hint"
      class="w-full text-[11px] font-normal normal-case tracking-normal text-white/40"
    >{{ hint }}</span>
  </label>
</template>
