<script setup>
defineProps({
    label: { type: String, required: true },
    color: { type: String, default: 'slate' },
});

// Full literal class strings so Tailwind's content scanner keeps them.
const styles = {
    slate: 'bg-slate-100 text-slate-600',
    amber: 'bg-amber-100 text-amber-700',
    emerald: 'bg-emerald-100 text-emerald-700',
};
</script>

<template>
  <span
    class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium"
    :class="styles[color] || styles.slate"
  >
    {{ label }}
  </span>
</template>
