<script setup>
import { computed, toRef } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import ProjectTimelineView from '@/modules/project/components/Timeline/ProjectTimelineView.vue';
import { useProjectTimeline, TIMELINE_STATUS_BAR } from '@/composables/useProjectTimeline';

const props = defineProps({
    project: { type: Object, required: true },
    tasks: { type: Array, default: () => [] },
    sprints: { type: Array, default: () => [] },
    blockers: { type: Array, default: () => [] },
    enums: { type: Object, default: () => ({}) },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
    revertPreviewTaskId: { type: Number, default: null },
});

const emit = defineEmits(['create-task', 'select-task', 'date-change']);

const timeline = useProjectTimeline({
    project: toRef(props, 'project'),
    tasks: toRef(props, 'tasks'),
    sprints: toRef(props, 'sprints'),
});

const {
    viewMode,
    zoom,
    groupBy,
    setGroupBy,
    kpis,
    columns,
    columnWidth,
    timelineWidth,
    todayPercent,
    hasTimelineData,
    flatRows,
    dependencyLinks,
    setViewMode,
    zoomIn,
    zoomOut,
    toggleGroup,
    isGroupOpen,
    sprintSpanStyle,
    barStyle,
    milestonePercent,
    getAssignees,
    isOverdue,
    isBlocked,
} = timeline;

const viewModes = [
    { key: 'day', label: 'Ngày' },
    { key: 'week', label: 'Tuần' },
];

const groupModes = [
    { key: 'nested', label: 'Sprint + giai đoạn' },
    { key: 'sprint', label: 'Theo sprint' },
    { key: 'phase', label: 'Theo giai đoạn' },
];

const kpiChips = computed(() => {
    const k = kpis.value ?? {};
    const chips = [
        { key: 'total', label: 'Tổng', value: k.total, dot: 'bg-slate-400', chip: 'bg-slate-100 text-slate-600' },
        { key: 'completed', label: 'Hoàn thành', value: k.completed, dot: 'bg-emerald-500', chip: 'bg-emerald-50 text-emerald-700' },
        { key: 'inProgress', label: 'Đang làm', value: k.inProgress, dot: 'bg-sky-500', chip: 'bg-sky-50 text-sky-700' },
        {
            key: 'overdue',
            label: 'Quá hạn',
            value: k.overdue,
            dot: k.overdue ? 'bg-rose-500' : 'bg-slate-300',
            chip: k.overdue ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-400',
        },
        ...(k.milestones
            ? [{ key: 'milestones', label: 'Milestone', value: k.milestones, dot: '', chip: 'bg-violet-50 text-violet-700', prefix: '◆' }]
            : []),
    ];
    return chips.filter((c) => c?.key != null);
});
</script>

<template>
  <div class="flex h-full flex-col overflow-hidden bg-white">
    <div class="flex shrink-0 items-center gap-3 border-b border-slate-200 bg-white px-4 py-2">
      <div class="flex min-w-0 flex-wrap items-center gap-1.5">
        <span
          v-for="chip in kpiChips"
          :key="chip.key"
          class="inline-flex items-center gap-1.5 rounded-md px-2 py-0.5 text-[11px] font-medium"
          :class="chip.chip"
        >
          <span
            v-if="chip.dot"
            class="h-1.5 w-1.5 shrink-0 rounded-full"
            :class="chip.dot"
          />
          <span
            v-else-if="chip.prefix"
            class="shrink-0"
          >{{ chip.prefix }}</span>
          <span class="opacity-80">{{ chip.label }}</span>
          <span class="font-semibold tabular-nums">{{ chip.value }}</span>
        </span>
      </div>

      <div class="flex-1" />

      <div class="inline-flex max-w-full shrink-0 flex-wrap rounded-md border border-slate-200 bg-slate-50 p-0.5">
        <button
          v-for="g in groupModes"
          :key="g.key"
          type="button"
          class="rounded px-2 py-1 text-[11px] font-medium transition"
          :class="groupBy === g.key ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          @click="setGroupBy(g.key)"
        >
          {{ g.label }}
        </button>
      </div>

      <div class="inline-flex shrink-0 rounded-md border border-slate-200 bg-slate-50 p-0.5">
        <button
          v-for="m in viewModes"
          :key="m.key"
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition"
          :class="viewMode === m.key ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          @click="setViewMode(m.key)"
        >
          {{ m.label }}
        </button>
      </div>

      <div class="hidden items-center gap-1 sm:flex">
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded border border-slate-200 text-slate-500 hover:bg-slate-100"
          title="Thu nhỏ"
          @click="zoomOut"
        >
          <AppIcon
            name="minus"
            :size="13"
          />
        </button>
        <span class="min-w-[2.5rem] text-center text-[11px] font-medium text-slate-500">{{ Math.round(zoom * 100) }}%</span>
        <button
          type="button"
          class="grid h-7 w-7 place-items-center rounded border border-slate-200 text-slate-500 hover:bg-slate-100"
          title="Phóng to"
          @click="zoomIn"
        >
          <AppIcon
            name="plus"
            :size="13"
          />
        </button>
      </div>

      <button
        v-if="canManage"
        type="button"
        class="btn-primary shrink-0 text-xs"
        @click="emit('create-task')"
      >
        <AppIcon
          name="add"
          :size="13"
        /> Tạo task
      </button>
    </div>

    <div class="flex min-h-0 flex-1 flex-col overflow-hidden">
      <div
        v-if="!hasTimelineData"
        class="flex flex-1 flex-col items-center justify-center p-8 text-center"
      >
        <div class="mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand">
          <AppIcon
            name="timeline"
            :size="28"
          />
        </div>
        <h3 class="font-display text-lg font-semibold text-slate-800">
          Chưa có dữ liệu biểu đồ
        </h3>
        <p class="mt-1 max-w-sm text-sm text-slate-500">
          Thêm task có ngày bắt đầu và hạn để xây dựng Gantt chart.
        </p>
        <button
          v-if="canManage"
          type="button"
          class="btn-primary mt-4 text-sm"
          @click="emit('create-task')"
        >
          <AppIcon
            name="add"
            :size="14"
          /> Tạo task đầu tiên
        </button>
      </div>

      <ProjectTimelineView
        v-else
        class="flex-1"
        :flat-rows="flatRows"
        :dependency-links="dependencyLinks"
        :columns="columns"
        :column-width="columnWidth"
        :timeline-width="timelineWidth"
        :today-percent="todayPercent"
        :editable="canContribute"
        :is-group-open="isGroupOpen"
        :toggle-group="toggleGroup"
        :sprint-span-style="sprintSpanStyle"
        :bar-style="barStyle"
        :milestone-percent="milestonePercent"
        :get-assignees="getAssignees"
        :is-overdue="isOverdue"
        :is-blocked="isBlocked"
        :status-bar-class="TIMELINE_STATUS_BAR"
        :revert-preview-task-id="revertPreviewTaskId"
        @select="emit('select-task', $event)"
        @date-change="emit('date-change', $event)"
      />
    </div>
  </div>
</template>
