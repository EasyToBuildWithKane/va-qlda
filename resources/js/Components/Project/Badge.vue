<script setup>
defineProps({
    label: { type: String, required: true },
    color: { type: String, default: 'slate' },
});

// Full literal class strings so Tailwind's content scanner keeps them.
const styles = {
    slate: 'bg-slate-100 text-slate-600',
    sky: 'bg-sky-100 text-sky-700',
    amber: 'bg-amber-100 text-amber-700',
    emerald: 'bg-emerald-100 text-emerald-700',
    rose: 'bg-rose-100 text-rose-700',
    violet: 'bg-violet-100 text-violet-700',
    cyan: 'bg-cyan-100 text-cyan-700',
    brand: 'bg-brand-100 text-brand-700',
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
