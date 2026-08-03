<script setup>
defineProps({
    levels: { type: Array, default: () => [] },
    selectedCode: { type: String, default: null },
    selectedLabel: { type: String, default: null },
    disabled: { type: Boolean, default: false },
});

defineEmits(['select']);
</script>

<template>
  <div
    class="flex flex-wrap gap-1.5"
    role="group"
    aria-label="Chọn mức điểm"
  >
    <button
      v-for="level in levels"
      :key="level.code || level.label"
      type="button"
      :disabled="disabled"
      :aria-pressed="(selectedCode && level.code === selectedCode) || (selectedLabel && level.label === selectedLabel)"
      class="rounded-lg border px-2.5 py-1.5 text-left text-xs transition focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/40 disabled:cursor-not-allowed disabled:opacity-40"
      :class="((selectedCode && level.code === selectedCode) || (selectedLabel && level.label === selectedLabel))
        ? 'border-brand bg-brand/10 text-brand ring-1 ring-brand/20'
        : 'border-slate-200 bg-white text-slate-600 hover:border-brand/40 hover:bg-brand/5'"
      @click="$emit('select', { code: level.code || null, label: level.label || null, weight: Number(level.weight) || 0 })"
    >
      <span class="font-medium">{{ level.label }}</span>
      <span
        v-if="level.weight != null"
        class="ml-1 tabular-nums text-slate-400"
      >
        +{{ level.weight }}
      </span>
    </button>
    <p
      v-if="!levels.length"
      class="text-xs text-slate-400"
    >
      Chưa có thang điểm cho tiêu chí này.
    </p>
  </div>
</template>
