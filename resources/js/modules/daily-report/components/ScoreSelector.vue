<script setup>
defineProps({
    modelValue: { type: Number, default: null },
    disabled: { type: Boolean, default: false },
    /** Compact chips for dense review scoring panel */
    compact: { type: Boolean, default: false },
});

defineEmits(['update:modelValue']);
</script>

<template>
  <div
    class="flex flex-nowrap gap-0.5 overflow-x-auto"
    role="group"
    aria-label="Bộ chọn điểm 1–10"
  >
    <button
      v-for="val in 10"
      :key="val"
      type="button"
      :disabled="disabled"
      :aria-pressed="modelValue === val"
      :aria-label="`Điểm ${val}`"
      class="shrink-0 rounded-md font-semibold tabular-nums transition-all duration-100 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/60 focus-visible:ring-offset-1 disabled:cursor-not-allowed disabled:opacity-40"
      :class="[
        compact ? 'h-7 min-w-[1.65rem] px-0.5 text-[11px]' : 'h-8 min-w-[2rem] px-1 text-xs',
        modelValue === val
          ? 'bg-brand text-white shadow-sm ring-2 ring-brand/20'
          : val < (modelValue ?? 0)
            ? 'bg-brand/10 text-brand hover:bg-brand/20'
            : 'bg-slate-100 text-slate-500 hover:bg-slate-200',
      ]"
      @click="$emit('update:modelValue', val)"
    >
      {{ val }}
    </button>
  </div>
</template>
