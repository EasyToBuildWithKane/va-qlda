<script setup>
defineProps({
    label: { type: String, default: '' },
    htmlFor: { type: String, default: null },
});
</script>

<template>
  <div class="min-w-0 w-full">
    <label
      v-if="label"
      :for="htmlFor"
      class="mb-1.5 block text-[11px] font-medium leading-none text-slate-500 dark:text-slate-400"
    >
      {{ label }}
    </label>
    <slot />
  </div>
</template>
