<script setup>
defineProps({
    lines: { type: Number, default: 3 },
    height: { type: String, default: 'h-4' },
    widths: { type: Array, default: () => ['w-full', 'w-3/4', 'w-1/2'] },
});
</script>

<template>
  <div
    class="animate-pulse space-y-3"
    aria-busy="true"
    aria-label="Đang tải…"
  >
    <div
      v-for="i in lines"
      :key="i"
      class="rounded-md bg-slate-200 dark:bg-slate-700"
      :class="[height, widths[(i - 1) % widths.length]]"
    />
  </div>
</template>
