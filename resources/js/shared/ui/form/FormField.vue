<script setup>
defineProps({
    label: { type: String, default: null },
    error: { type: String, default: null },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
    id: { type: String, default: null },
});
</script>

<template>
  <div class="space-y-1">
    <label
      v-if="label"
      :for="id"
      class="block text-sm font-medium text-slate-700 dark:text-slate-300"
    >
      {{ label }}
      <span
        v-if="required"
        class="ml-0.5 text-danger"
        aria-hidden="true"
      >*</span>
    </label>

    <slot />

    <p
      v-if="error"
      class="text-xs text-danger"
      role="alert"
    >
      {{ error }}
    </p>
    <p
      v-else-if="hint"
      class="text-xs text-slate-400"
    >
      {{ hint }}
    </p>
  </div>
</template>
