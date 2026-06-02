<script setup>
import { computed } from 'vue';
import Badge from '@/Components/Project/Badge.vue';
import { date } from '@/composables/useFormat';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
});

const emit = defineEmits(['open-task']);

const monthStart = computed(() => {
    const d = new Date();
    d.setDate(1);
    d.setHours(0, 0, 0, 0);
    return d;
});

const daysInMonth = computed(() => {
    const start = monthStart.value;
    const end = new Date(start.getFullYear(), start.getMonth() + 1, 0);
    return end.getDate();
});

const startWeekday = computed(() => {
    const d = monthStart.value.getDay();
    return d === 0 ? 6 : d - 1;
});

const cells = computed(() => {
    const blanks = Array(startWeekday.value).fill(null);
    const days = Array.from({ length: daysInMonth.value }, (_, i) => i + 1);
    return [...blanks, ...days];
});

const tasksByDay = computed(() => {
    const map = {};
    const y = monthStart.value.getFullYear();
    const m = monthStart.value.getMonth();
    props.tasks.forEach((t) => {
        if (!t.due_date) return;
        const d = new Date(`${t.due_date}T00:00:00`);
        if (d.getFullYear() === y && d.getMonth() === m) {
            const day = d.getDate();
            if (!map[day]) map[day] = [];
            map[day].push(t);
        }
    });
    return map;
});

const monthLabel = computed(() =>
    monthStart.value.toLocaleDateString('vi-VN', { month: 'long', year: 'numeric' }),
);
</script>

<template>
    <div class="rounded-xl border border-slate-200/80 bg-white p-4 dark:border-slate-700 dark:bg-slate-900">
        <h3 class="mb-3 font-display font-semibold text-slate-800 dark:text-slate-100">{{ monthLabel }}</h3>
        <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-semibold uppercase text-slate-400">
            <span v-for="d in ['T2', 'T3', 'T4', 'T5', 'T6', 'T7', 'CN']" :key="d">{{ d }}</span>
        </div>
        <div class="mt-1 grid grid-cols-7 gap-1">
            <div
                v-for="(cell, i) in cells"
                :key="i"
                class="min-h-[5rem] rounded-lg border border-slate-100 p-1 text-left dark:border-slate-800"
                :class="cell ? 'bg-white dark:bg-slate-900' : 'bg-transparent'"
            >
                <span v-if="cell" class="text-xs font-semibold text-slate-500">{{ cell }}</span>
                <button
                    v-for="t in (tasksByDay[cell] || []).slice(0, 3)"
                    :key="t.id"
                    type="button"
                    class="mt-0.5 block w-full truncate rounded px-1 py-0.5 text-left text-[10px] hover:bg-brand/10"
                    @click="emit('open-task', t)"
                >
                    {{ t.title }}
                </button>
                <p v-if="(tasksByDay[cell] || []).length > 3" class="text-[9px] text-slate-400">+{{ tasksByDay[cell].length - 3 }}</p>
            </div>
        </div>
    </div>
</template>
