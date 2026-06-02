<script setup>
import { onMounted, onBeforeUnmount, ref, watch, nextTick } from 'vue';
import Gantt from 'frappe-gantt';
// The package's "exports" map only exposes the bare entry, so reach the stylesheet
// by relative path (relative imports bypass package-exports resolution).
import '../../../../node_modules/frappe-gantt/dist/frappe-gantt.css';

const props = defineProps({
    // Each: { id, title, start_date, due_date, progress, status, dependencies:[] }
    tasks: { type: Array, default: () => [] },
    editable: { type: Boolean, default: false },
});

const emit = defineEmits(['date-change', 'progress-change', 'select']);

const wrapper = ref(null);
const viewMode = ref('Week');
const modes = ['Day', 'Week', 'Month'];
let gantt = null;

const toYmd = (d) => {
    const x = d instanceof Date ? d : new Date(d);
    return `${x.getFullYear()}-${String(x.getMonth() + 1).padStart(2, '0')}-${String(x.getDate()).padStart(2, '0')}`;
};

// Only tasks with both dates can appear on a timeline.
const scheduled = () =>
    props.tasks
        .filter((t) => t.start_date && t.due_date)
        .map((t) => ({
            id: String(t.id),
            name: t.title,
            start: t.start_date,
            end: t.due_date,
            progress: Number(t.progress) || 0,
            dependencies: (t.dependencies || []).map(String).join(','),
            custom_class: `gantt-${t.status?.color || 'slate'}`,
        }));

function render() {
    const data = scheduled();
    if (!wrapper.value) return;

    // frappe-gantt needs at least one task; otherwise show our empty state.
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
        bar_height: 26,
        padding: 16,
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
onBeforeUnmount(() => {
    gantt = null;
});

// Re-render when the task set changes (add/remove/date edits from elsewhere).
watch(() => props.tasks, () => nextTick(render), { deep: true });

const unscheduledCount = () => props.tasks.filter((t) => !t.start_date || !t.due_date).length;
</script>

<template>
    <div>
        <div class="mb-3 flex items-center justify-between gap-3">
            <div class="inline-flex rounded-btn border border-slate-200 bg-white p-0.5">
                <button
                    v-for="m in modes"
                    :key="m"
                    type="button"
                    class="rounded-[4px] px-3 py-1 text-xs font-medium transition"
                    :class="viewMode === m ? 'bg-brand text-white' : 'text-slate-500 hover:bg-slate-100'"
                    @click="setView(m)"
                >
                    {{ m === 'Day' ? 'Ngày' : m === 'Week' ? 'Tuần' : 'Tháng' }}
                </button>
            </div>
            <p v-if="unscheduledCount()" class="text-xs text-slate-400">
                {{ unscheduledCount() }} công việc chưa có ngày bắt đầu/kết thúc
            </p>
        </div>

        <div v-if="tasks.length === 0" class="grid place-items-center rounded-card border border-dashed border-slate-200 py-16 text-sm text-slate-400">
            Chưa có công việc nào.
        </div>
        <div v-else class="gantt-host overflow-x-auto rounded-card border border-slate-100">
            <div ref="wrapper"></div>
        </div>
    </div>
</template>

<style>
/* Status-tinted Gantt bars (literal classes, scoped by frappe's svg structure). */
.gantt-host .gantt .bar-wrapper.gantt-emerald .bar-progress { fill: #10b981; }
.gantt-host .gantt .bar-wrapper.gantt-sky .bar-progress { fill: #0ea5e9; }
.gantt-host .gantt .bar-wrapper.gantt-violet .bar-progress { fill: #8b5cf6; }
.gantt-host .gantt .bar-wrapper.gantt-rose .bar-progress { fill: #f43f5e; }
.gantt-host .gantt .bar-wrapper.gantt-amber .bar-progress { fill: #f59e0b; }
.gantt-host .gantt .bar-wrapper .bar { fill: #e2e8f0; }
.gantt-host { --g-bar-color: #e2e8f0; }
</style>
