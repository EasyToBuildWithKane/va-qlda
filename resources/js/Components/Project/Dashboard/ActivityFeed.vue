<script setup>
import { computed } from 'vue';
import { ACTIVITY_DOT, relativeTime } from '@/composables/useProjectDashboard';

const props = defineProps({
    activities: { type: Array, default: () => [] },
    visibleCount: { type: Number, default: 10 },
});

const emit = defineEmits(['show-more']);

const visible = computed(() =>
    props.activities.filter((ev) => ev && ev.id != null).slice(0, props.visibleCount),
);
const hasMore = computed(() => props.activities.length > props.visibleCount);
</script>

<template>
    <div class="card min-w-0 overflow-hidden p-5 dark:border-slate-700 dark:bg-slate-900">
        <h2 class="mb-4 font-display font-semibold text-slate-800 dark:text-slate-100">Hoạt động gần đây</h2>

        <ul v-if="visible.length" class="relative space-y-0">
            <li
                v-for="(ev, i) in visible"
                :key="ev.id"
                class="relative flex gap-3 pb-5 pl-5 last:pb-0"
            >
                <span
                    v-if="i < visible.length - 1"
                    class="absolute left-[7px] top-3 h-full w-px bg-slate-200 dark:bg-slate-600"
                    aria-hidden="true"
                />
                <span
                    class="relative z-[1] mt-1 h-3.5 w-3.5 shrink-0 rounded-full ring-2 ring-white dark:ring-slate-900"
                    :class="ACTIVITY_DOT[ev.type] || 'bg-slate-400'"
                />
                <div class="min-w-0 flex-1">
                    <p class="text-sm text-slate-700 dark:text-slate-200">{{ ev.message }}</p>
                    <p class="mt-0.5 text-xs text-slate-400">{{ relativeTime(ev.at) }}</p>
                </div>
            </li>
        </ul>
        <p v-else class="text-sm text-slate-400">Chưa có hoạt động nào.</p>

        <button
            v-if="hasMore"
            type="button"
            class="btn-ghost mt-3 w-full text-sm dark:text-slate-300"
            @click="emit('show-more')"
        >
            Xem thêm
        </button>
    </div>
</template>
