<script setup>
defineProps({
    mode: { type: String, default: 'self' },
});

const emit = defineEmits(['select']);
</script>

<template>
  <div class="inline-flex items-center rounded-lg border border-slate-200 bg-slate-50 p-0.5 text-xs font-medium dark:border-slate-700 dark:bg-slate-800">
    <button
      type="button"
      class="rounded-md px-2.5 py-1 transition"
      :class="mode === 'self'
        ? 'bg-white text-brand shadow-sm dark:bg-slate-900'
        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
      @click="emit('select', 'self')"
    >
      Việc của tôi
    </button>
    <button
      type="button"
      class="rounded-md px-2.5 py-1 transition"
      :class="mode !== 'self'
        ? 'bg-white text-brand shadow-sm dark:bg-slate-900'
        : 'text-slate-500 hover:text-slate-700 dark:hover:text-slate-300'"
      @click="emit('select', 'team')"
    >
      Nhóm của tôi
    </button>
  </div>
</template>
