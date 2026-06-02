<script setup>
import { computed, ref } from 'vue';
import { date } from '@/composables/useFormat';
import { GANTT_BAR } from '@/composables/useProjectDashboard';

const props = defineProps({
    project: { type: Object, required: true },
    tasks: { type: Array, default: () => [] },
});

const hovered = ref(null);
const tooltip = ref({ show: false, bar: null, x: 0, y: 0, above: false });

const showTooltip = (bar, event) => {
    hovered.value = bar.id;
    const rect = event.currentTarget.getBoundingClientRect();
    const pad = 8;
    const maxW = 256;
    let x = rect.left;
    if (x + maxW > window.innerWidth - pad) x = window.innerWidth - maxW - pad;
    if (x < pad) x = pad;
    const above = rect.bottom + 120 > window.innerHeight - pad;
    const y = above ? rect.top - pad : rect.bottom + pad;
    tooltip.value = { show: true, bar, x, y, above };
};

const hideTooltip = () => {
    hovered.value = null;
    tooltip.value = { show: false, bar: null, x: 0, y: 0 };
};

const range = computed(() => {
    const start = props.project.start_date;
    const end = props.project.due_date || props.project.end_date;
    if (!start || !end) return null;
    const s = new Date(`${start}T00:00:00`).getTime();
    const e = new Date(`${end}T00:00:00`).getTime();
    const dur = e - s;
    if (dur <= 0) return null;
    return { start: s, end: e, duration: dur };
});

const weekLabels = computed(() => {
    if (!range.value) return [];
    const labels = [];
    const msWeek = 7 * 86400000;
    let t = range.value.start;
    let i = 0;
    while (t <= range.value.end && i < 52) {
        labels.push(new Date(t).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit' }));
        t += msWeek;
        i++;
    }
    return labels;
});

const bars = computed(() => {
    if (!range.value) return [];
    return props.tasks
        .filter((t) => t.start_date || t.due_date)
        .map((t) => {
            const ts = t.start_date ? new Date(`${t.start_date}T00:00:00`).getTime() : range.value.start;
            const te = t.due_date ? new Date(`${t.due_date}T00:00:00`).getTime() : ts + 86400000;
            const taskStart = Math.max(ts, range.value.start);
            const taskEnd = Math.min(te, range.value.end);
            const taskDur = Math.max(taskEnd - taskStart, 86400000);
            const offset = ((taskStart - range.value.start) / range.value.duration) * 100;
            const width = (taskDur / range.value.duration) * 100;
            const status = t.status?.value ?? 'todo';
            const assignee = t.assignees?.length
                ? t.assignees.map((a) => a.name).join(', ')
                : (t.assignee?.name ?? '—');
            return {
                id: t.id,
                title: t.title,
                offset: Math.max(0, Math.min(offset, 100)),
                width: Math.max(0.5, Math.min(width, 100 - offset)),
                color: GANTT_BAR[status] || GANTT_BAR.todo,
                assignee,
                start: t.start_date,
                end: t.due_date,
                progress: t.progress ?? 0,
            };
        });
});
</script>

<template>
    <div class="card min-w-0 overflow-x-hidden p-5 dark:border-slate-700 dark:bg-slate-900">
        <h2 class="mb-4 font-display font-semibold text-slate-800 dark:text-slate-100">Timeline mini (Gantt)</h2>

        <p v-if="!range" class="text-sm text-slate-400 dark:text-slate-500">
            Cần ngày bắt đầu và kết thúc dự án để hiển thị timeline.
        </p>

        <template v-else>
            <div
                class="mb-2 grid text-center text-[10px] text-slate-400 dark:text-slate-500"
                :style="{ gridTemplateColumns: `repeat(${Math.max(weekLabels.length, 1)}, minmax(0, 1fr))` }"
            >
                <span v-for="(w, i) in weekLabels" :key="i" class="truncate px-0.5">{{ w }}</span>
            </div>

            <div class="relative min-w-0 space-y-1.5">
                <div
                    v-for="bar in bars"
                    :key="bar.id"
                    class="group relative flex min-w-0 h-7 items-center gap-2"
                    @mouseenter="showTooltip(bar, $event)"
                    @mouseleave="hideTooltip"
                >
                    <span class="w-20 shrink-0 truncate text-xs text-slate-600 sm:w-24 dark:text-slate-300">{{ bar.title }}</span>
                    <div class="relative min-w-0 flex-1 h-5 rounded bg-slate-100 dark:bg-slate-800">
                        <div
                            class="absolute top-0.5 h-4 rounded transition-opacity"
                            :class="[bar.color, hovered === bar.id ? 'opacity-100 ring-2 ring-brand/30' : 'opacity-90']"
                            :style="{ left: bar.offset + '%', width: bar.width + '%' }"
                        />
                    </div>
                </div>
                <p v-if="!bars.length" class="py-4 text-center text-sm text-slate-400">Chưa có task có ngày bắt đầu/kết thúc.</p>
            </div>
        </template>

        <Teleport to="body">
            <div
                v-if="tooltip.show && tooltip.bar"
                class="pointer-events-none fixed z-[200] w-64 max-w-[calc(100vw-1rem)] rounded-card border border-slate-200 bg-white px-3 py-2 text-xs shadow-elevation-3 dark:border-slate-600 dark:bg-slate-800"
                :class="tooltip.above ? '-translate-y-full' : ''"
                :style="{ left: `${tooltip.x}px`, top: `${tooltip.y}px` }"
                role="tooltip"
            >
                <p class="font-semibold text-slate-800 dark:text-slate-100">{{ tooltip.bar.title }}</p>
                <p class="mt-0.5 text-slate-500 dark:text-slate-400">{{ tooltip.bar.assignee }}</p>
                <p class="text-slate-500 dark:text-slate-400">{{ date(tooltip.bar.start) }} → {{ date(tooltip.bar.end) }}</p>
                <p class="font-medium text-brand">{{ tooltip.bar.progress }}%</p>
            </div>
        </Teleport>
    </div>
</template>
