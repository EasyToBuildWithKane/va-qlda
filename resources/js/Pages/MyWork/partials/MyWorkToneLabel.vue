<script setup>
import { toneDotClass, toneTextClass } from '../utils/taskDisplay';

defineProps({
    label: { type: String, required: true },
    color: { type: String, default: 'slate' },
});
</script>

<template>
  <span class="inline-flex min-w-0 items-center gap-1.5">
    <span
      class="h-1.5 w-1.5 shrink-0 rounded-full"
      :class="toneDotClass(color)"
      aria-hidden="true"
    />
    <span
      class="truncate text-xs font-medium"
      :class="toneTextClass(color)"
    >{{ label }}</span>
  </span>
</template>
