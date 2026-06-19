<script setup>
defineProps({
    grade: { type: String, required: true },
    color: { type: String, default: 'slate' },
});

const styles = {
    emerald: 'bg-emerald-500',
    green: 'bg-green-500',
    sky: 'bg-sky-500',
    amber: 'bg-amber-500',
    rose: 'bg-rose-500',
    slate: 'bg-slate-400',
};
</script>

<template>
  <span
    class="inline-flex h-7 w-7 items-center justify-center rounded-full text-xs font-bold text-white"
    :class="styles[color] || styles.slate"
  >
    {{ grade }}
  </span>
</template>
