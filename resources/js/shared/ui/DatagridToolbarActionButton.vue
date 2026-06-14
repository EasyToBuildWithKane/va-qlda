<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    active: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    title: { type: String, default: '' },
    icon: { type: String, required: true },
    iconSize: { type: Number, default: 15 },
});
</script>

<template>
  <button
    type="button"
    class="inline-flex h-10 shrink-0 items-center justify-center gap-1.5 rounded-btn border px-3 text-xs font-medium transition select-none disabled:cursor-not-allowed disabled:opacity-50"
    :class="
      active
        ? 'border-brand/40 bg-brand/5 text-brand'
        : 'border-slate-200 bg-white text-slate-600 hover:border-slate-300 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300 dark:hover:border-slate-600'
    "
    :title="title"
    :disabled="disabled"
  >
    <AppIcon
      :name="icon"
      :size="iconSize"
    />
    <span><slot /></span>
  </button>
</template>
