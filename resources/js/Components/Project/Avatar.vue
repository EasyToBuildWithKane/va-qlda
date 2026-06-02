<script setup>
import { computed } from 'vue';

const props = defineProps({
    name: { type: String, default: '' },
    src: { type: String, default: null },
    size: { type: Number, default: 32 },
});

const initials = computed(() => {
    const parts = (props.name || '?').trim().split(/\s+/);
    const last = parts[parts.length - 1] || '';
    return (last[0] || '?').toUpperCase();
});

// Deterministic colour from the name so each person stays consistent.
const palette = ['bg-brand-100 text-brand-700', 'bg-sky-100 text-sky-700', 'bg-emerald-100 text-emerald-700', 'bg-amber-100 text-amber-700', 'bg-violet-100 text-violet-700', 'bg-rose-100 text-rose-700'];
const tone = computed(() => {
    let h = 0;
    for (const c of props.name || '') h = (h + c.charCodeAt(0)) % palette.length;
    return palette[h];
});
</script>

<template>
    <span
        class="inline-grid place-items-center rounded-full font-semibold shrink-0"
        :class="tone"
        :style="{ width: size + 'px', height: size + 'px', fontSize: Math.round(size * 0.42) + 'px' }"
        :title="name"
    >
        <img v-if="src" :src="src" :alt="name" class="h-full w-full rounded-full object-cover" />
        <template v-else>{{ initials }}</template>
    </span>
</template>
