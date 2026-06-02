<script setup>
import { inject } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const notifications = inject('notifications');
</script>

<template>
    <button
        type="button"
        class="relative grid h-9 w-9 place-items-center rounded-lg text-slate-500 transition-colors hover:bg-slate-100 hover:text-slate-700 focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-brand/30 dark:text-slate-400 dark:hover:bg-slate-800 dark:hover:text-slate-200"
        aria-label="Thông báo"
        :aria-expanded="notifications.drawerOpen.value"
        @click="notifications.openDrawer()"
    >
        <AppIcon name="notifications" :size="18" />
        <span
            v-if="notifications.unreadCount.value > 0"
            class="absolute -right-0.5 -top-0.5 flex h-[18px] min-w-[18px] items-center justify-center rounded-full bg-brand px-1 text-[10px] font-bold leading-none text-white shadow-sm"
        >
            {{ notifications.badgeLabel.value }}
        </span>
    </button>
</template>
