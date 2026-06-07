<script setup>
defineProps({
    label: { type: String, required: true },
    color: { type: String, default: 'slate' },
});

// Full literal class strings so Tailwind's content scanner keeps them.
const styles = {
    slate: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    sky: 'bg-sky-100 text-sky-700 dark:bg-sky-950/50 dark:text-sky-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950/50 dark:text-amber-300',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/50 dark:text-emerald-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-950/50 dark:text-rose-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950/50 dark:text-violet-300',
    indigo: 'bg-indigo-100 text-indigo-700 dark:bg-indigo-950/50 dark:text-indigo-300',
    teal: 'bg-teal-100 text-teal-700 dark:bg-teal-950/50 dark:text-teal-300',
    fuchsia: 'bg-fuchsia-100 text-fuchsia-700 dark:bg-fuchsia-950/50 dark:text-fuchsia-300',
    orange: 'bg-orange-100 text-orange-700 dark:bg-orange-950/50 dark:text-orange-300',
    green: 'bg-green-100 text-green-700 dark:bg-green-950/50 dark:text-green-300',
    cyan: 'bg-cyan-100 text-cyan-700 dark:bg-cyan-950/50 dark:text-cyan-300',
    brand: 'bg-brand-100 text-brand-700 dark:bg-brand/20 dark:text-brand-100',
    blue: 'bg-sky-100 text-sky-800 dark:bg-sky-950/50 dark:text-sky-300',
};
</script>

<template>
  <span
    class="inline-flex items-center gap-1 rounded-full px-2.5 py-0.5 text-xs font-medium whitespace-nowrap"
    :class="styles[color] || styles.slate"
  >
    {{ label }}
  </span>
</template>
