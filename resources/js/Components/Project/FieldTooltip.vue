<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    text: { type: String, required: true },
});
</script>

<template>
    <span class="group relative inline-flex align-middle">
        <button
            type="button"
            class="grid h-4 w-4 place-items-center rounded-full text-slate-400 hover:text-brand focus:outline-none focus:ring-2 focus:ring-brand/30"
            :aria-label="text"
            tabindex="0"
        >
            <AppIcon name="info" :size="14" />
        </button>
        <span
            class="pointer-events-none absolute bottom-full left-1/2 z-30 mb-1.5 hidden w-52 -translate-x-1/2 rounded-lg bg-slate-800 px-3 py-2 text-xs font-normal leading-relaxed text-white shadow-elevation-2 group-hover:block group-focus-within:block"
            role="tooltip"
        >
            {{ text }}
            <span class="absolute left-1/2 top-full h-2 w-2 -translate-x-1/2 -translate-y-1 rotate-45 bg-slate-800"></span>
        </span>
    </span>
</template>
