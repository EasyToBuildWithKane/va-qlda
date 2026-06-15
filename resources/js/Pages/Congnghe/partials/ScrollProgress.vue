<script setup>
import { computed } from 'vue';
import { useScrollProgress } from './motion.js';

const progress = useScrollProgress();
const width = computed(() => `${(progress.value * 100).toFixed(2)}%`);
</script>

<template>
  <div class="pointer-events-none fixed inset-x-0 top-0 z-[60] h-[3px]">
    <div
      class="h-full bg-gradient-to-r from-brand via-[#ff4d8d] to-[#7c3aed] shadow-[0_0_12px_rgba(255,77,141,0.6)] transition-[width] duration-150 ease-out"
      :style="{ width }"
    />
  </div>
</template>
