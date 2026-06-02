<script setup>
import { onMounted, onBeforeUnmount, ref, watch, nextTick, computed } from 'vue';
import Gantt from 'frappe-gantt';
import '../../../../node_modules/frappe-gantt/dist/frappe-gantt.css';
import Avatar from '@/Components/Project/Avatar.vue';
import Badge from '@/Components/Project/Badge.vue';

const props = defineProps({
    tasks: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
});

const emit = defineEmits(['date-change', 'progress-change', 'select']);

const wrapper = ref(null);
const viewMode = ref('Week');
const modes = [
    { key: 'Day', label: 'Ngày' },
    { key: 'Week', label: 'Tuần' },
    { key: 'Month', label: 'Tháng' },
];
let gantt = null;

const toYmd = (d) => {
    const x = d instanceof Date ? d : new Date(d);
    return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`;
};

const scheduled = () =>
    props.tasks
        .filter((t) => t.start_date && t.due_date)
        .map((t) => {
            const assigneeName = t.assignees?.length
                ? t.assignees.map((a) => a.name.split(' ').pop()).join(', ')
                : t.assignee?.name?.split(' ').pop() ?? '';
            return {
                id: String(t.id),
                name: assigneeName ? `${t.title} · ${assigneeName}` : t.title,
                start: t.start_date,
                end: t.due_date,
                progress: Number(t.progress) || 0,
                dependencies: (t.dependencies || []).map((d) => String(typeof d === 'object' && d !== null ? d.id : d)).join(','),
                custom_class: `gantt-${t.status?.color || 'slate'}`,
            };
        });

function render() {
    const data = scheduled();
    if (!wrapper.value) return;
    if (data.length === 0) {
        gantt = null;
        wrapper.value.innerHTML = '';
        return;
    }
    wrapper.value.innerHTML = '';
    gantt = new Gantt(wrapper.value, data, {
        view_mode: viewMode.value,
        readonly: !props.editable,
        readonly_progress: !props.editable,
        readonly_dates: !props.editable,
        infinite_padding: false,
        language: 'vi',
        bar_height: 32,
        padding: 20,
        today_button: true,
        view_mode_select: false,
        on_click: (task) => emit('select', Number(task.id)),
        on_date_change: (task, start, end) =>
            emit('date-change', { id: Number(task.id), start: toYmd(start), end: toYmd(end) }),
        on_progress_change: (task, progress) =>
            emit('progress-change', { id: Number(task.id), progress: Math.round(progress) }),
    });
}

function setView(mode) {
    viewMode.value = mode;
    if (gantt) gantt.change_view_mode(mode);
    else render();
}

onMounted(() => nextTick(render));
onBeforeUnmount(() => { gantt = null; });
watch(() => props.tasks, () => nextTick(render), { deep: true });

const unscheduledTasks = computed(() => props.tasks.filter((t) => !t.start_date || !t.due_date));
const isMonthView = computed(() => viewMode.value === 'Month');

// Member legend
const memberLegend = computed(() => {
    const seen = new Map();
    props.tasks.forEach((t) => {
        const people = t.assignees?.length ? t.assignees : (t.assignee ? [t.assignee] : []);
        people.forEach((p) => {
            if (!seen.has(p.id)) seen.set(p.id, p);
        });
    });
    return [...seen.values()];
});
</script>

<template>
    <div>
        <!-- Controls row -->
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div class="inline-flex rounded-btn border border-slate-200 bg-white p-0.5 shadow-sm">
                <button
                    v-for="m in modes"
                    :key="m.key"
                    type="button"
                    class="rounded-[4px] px-4 py-1.5 text-sm font-medium transition"
                    :class="[
                        viewMode === m.key ? 'bg-brand text-white shadow-sm' : 'text-slate-500 hover:bg-slate-100',
                        m.key === 'Month' ? 'relative pl-5' : '',
                    ]"
                    @click="setView(m.key)"
                >
                    <span
                        v-if="m.key === 'Month'"
                        class="absolute left-2 top-1/2 h-1.5 w-1.5 -translate-y-1/2 rounded-full"
                        :class="viewMode === m.key ? 'bg-white/90' : 'bg-brand'"
                    ></span>
                    {{ m.label }}
                </button>
            </div>

            <!-- Member legend -->
            <div v-if="memberLegend.length" class="flex flex-wrap items-center gap-3">
                <span class="text-xs font-medium text-slate-500">Người phụ trách:</span>
                <span v-for="m in memberLegend" :key="m.id" class="flex items-center gap-1.5">
                    <Avatar :name="m.name" :src="m.avatar_path" :size="22" />
                    <span class="text-xs text-slate-600">{{ m.name }}</span>
                </span>
            </div>

            <p v-if="unscheduledTasks.length" class="ml-auto text-xs text-amber-600">
                ⚠ {{ unscheduledTasks.length }} công việc chưa có ngày
            </p>
        </div>

        <div
            v-if="isMonthView && scheduled().length"
            class="mb-3 flex flex-wrap items-center justify-between gap-2 rounded-card border border-brand-100 bg-gradient-to-r from-brand-50 via-white to-brand-50/50 px-4 py-2.5"
        >
            <p class="text-sm font-medium text-brand">
                Chế độ Tháng: tối ưu theo mốc tổng quan và nhịp tiến độ dài hạn.
            </p>
            <Badge tone="sky" size="sm">Monthly Timeline</Badge>
        </div>

        <!-- Empty state -->
        <div v-if="tasks.length === 0" class="grid place-items-center rounded-card border border-dashed border-slate-200 py-20 text-sm text-slate-400">
            Chưa có công việc nào.
        </div>
        <div v-else-if="scheduled().length === 0" class="grid place-items-center rounded-card border border-dashed border-slate-200 py-20 text-sm text-slate-400">
            <p>Chưa có công việc nào được lên lịch.</p>
            <p class="mt-1 text-xs text-slate-400">Hãy đặt ngày bắt đầu &amp; kết thúc cho công việc để hiển thị trên biểu đồ.</p>
        </div>

        <!-- Gantt chart container -->
        <div
            v-else
            class="gantt-host overflow-x-auto rounded-card border border-slate-100"
            :class="{ 'gantt-host-month': isMonthView }"
        >
            <div ref="wrapper" class="gantt-inner"></div>
        </div>

        <!-- Unscheduled tasks list -->
        <div v-if="unscheduledTasks.length" class="mt-4">
            <p class="mb-2 text-xs font-semibold uppercase tracking-wide text-slate-400">Chưa lên lịch ({{ unscheduledTasks.length }})</p>
            <div class="flex flex-wrap gap-2">
                <div
                    v-for="t in unscheduledTasks"
                    :key="t.id"
                    class="flex cursor-pointer items-center gap-2 rounded-btn border border-dashed border-slate-200 bg-slate-50 px-3 py-1.5 text-sm text-slate-600 hover:border-brand hover:bg-brand-50 hover:text-brand"
                    @click="emit('select', t.id)"
                >
                    <span class="h-2 w-2 shrink-0 rounded-full" :class="{
                        'bg-slate-400': t.status?.color === 'slate',
                        'bg-sky-500': t.status?.color === 'sky',
                        'bg-violet-500': t.status?.color === 'violet',
                        'bg-emerald-500': t.status?.color === 'emerald',
                        'bg-rose-500': t.status?.color === 'rose',
                        'bg-amber-500': t.status?.color === 'amber',
                    }"></span>
                    {{ t.title }}
                    <Avatar v-if="t.assignee" :name="t.assignee.name" :src="t.assignee.avatar_path" :size="18" />
                </div>
            </div>
        </div>
    </div>
</template>

<style>
.gantt-host .gantt .bar-wrapper.gantt-emerald .bar-progress { fill: #10b981; }
.gantt-host .gantt .bar-wrapper.gantt-sky .bar-progress { fill: #0ea5e9; }
.gantt-host .gantt .bar-wrapper.gantt-violet .bar-progress { fill: #8b5cf6; }
.gantt-host .gantt .bar-wrapper.gantt-rose .bar-progress { fill: #f43f5e; }
.gantt-host .gantt .bar-wrapper.gantt-amber .bar-progress { fill: #f59e0b; }
.gantt-host .gantt .bar-wrapper .bar { fill: #e2e8f0; }
.gantt-host { --g-bar-color: #e2e8f0; }
/* Make the chart wider and taller by default */
.gantt-host svg { min-width: 900px; }
.gantt-inner { min-height: 200px; }

/* Month mode redesign */
.gantt-host-month {
    border-color: #cbd5e1;
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 30%);
}

.gantt-host-month svg {
    min-width: 1080px;
}

.gantt-host-month .gantt .grid-header {
    fill: #e0f2fe;
}

.gantt-host-month .gantt .grid-row {
    fill: #ffffff;
}

.gantt-host-month .gantt .row-line,
.gantt-host-month .gantt .tick {
    stroke: #dbeafe;
}

.gantt-host-month .gantt .today-highlight {
    fill: rgba(14, 165, 233, 0.12);
}

.gantt-host-month .gantt .upper-text {
    fill: #0f172a;
    font-weight: 700;
    letter-spacing: 0.02em;
}

.gantt-host-month .gantt .lower-text {
    fill: #334155;
    font-weight: 600;
}

.gantt-host-month .gantt .bar-label {
    fill: #0f172a;
    font-weight: 600;
}
</style>
