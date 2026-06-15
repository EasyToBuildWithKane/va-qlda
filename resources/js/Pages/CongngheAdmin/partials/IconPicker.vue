<script setup>
import { computed } from 'vue';

const props = defineProps({
    modelValue: { type: String, default: '' },
    icons: { type: Array, default: () => [] },
    disabled: { type: Boolean, default: false },
    id: { type: String, default: null },
});

defineEmits(['update:modelValue']);

const currentPath = computed(
    () => props.icons.find((i) => i.key === props.modelValue)?.path ?? '',
);
</script>

<template>
  <div class="flex items-center gap-2">
    <span class="grid h-9 w-9 shrink-0 place-items-center rounded-lg border border-slate-200 bg-slate-50 text-brand">
      <svg
        v-if="currentPath"
        width="18"
        height="18"
        viewBox="0 0 24 24"
        fill="none"
        stroke="currentColor"
        stroke-width="1.7"
        stroke-linecap="round"
        stroke-linejoin="round"
      ><path :d="currentPath" /></svg>
    </span>
    <select
      :id="id"
      :value="modelValue"
      :disabled="disabled"
      class="input w-full"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option value="">
        — Không —
      </option>
      <option
        v-for="opt in icons"
        :key="opt.key"
        :value="opt.key"
      >
        {{ opt.label }}
      </option>
    </select>
  </div>
</template>
