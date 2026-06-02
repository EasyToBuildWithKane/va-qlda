<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    banner: { type: Object, required: true },
});

const emit = defineEmits(['dismiss']);
</script>

<template>
    <div
        class="flex items-center justify-between gap-3 rounded-card border px-4 py-3 text-sm font-medium"
        :class="banner.tone === 'danger'
            ? 'border-rose-200 bg-rose-50 text-rose-800 dark:border-rose-800 dark:bg-rose-950/40 dark:text-rose-200'
            : 'border-amber-200 bg-amber-50 text-amber-800 dark:border-amber-800 dark:bg-amber-950/40 dark:text-amber-200'"
        role="alert"
    >
        <span class="flex items-center gap-2">
            <AppIcon name="flag" :size="16" />
            {{ banner.text }}
        </span>
        <button
            type="button"
            class="grid h-7 w-7 shrink-0 place-items-center rounded text-current/60 hover:bg-black/5 hover:text-current dark:hover:bg-white/10"
            aria-label="Đóng"
            @click="emit('dismiss')"
        >
            <AppIcon name="close" :size="14" />
        </button>
    </div>
</template>
