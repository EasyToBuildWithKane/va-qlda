<script setup>
import { ref, computed, watch } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { useVirtualScroll } from '@/composables/useVirtualScroll';
import { date } from '@/composables/useFormat';

const props = defineProps({
    flatRows: { type: Array, default: () => [] },
    dependencyLinks: { type: Array, default: () => [] },
    columns: { type: Array, default: () => [] },
    columnWidth: { type: Number, default: 72 },
    timelineWidth: { type: Number, default: 800 },
    todayPercent: { type: Number, default: 0 },
    totalRowHeight: { type: Number, default: 0 },
    editable: { type: Boolean, default: false },
    isGroupOpen: { type: Function, required: true },
    toggleGroup: { type: Function, required: true },
    sprintSpanStyle: { type: Function, default: () => () => null },
    barStyle: { type: Function, required: true },
    milestonePercent: { type: Function, required: true },
    getAssignees: { type: Function, required: true },
    isOverdue: { type: Function, required: true },
    isBlocked: { type: Function, required: true },
    statusBarClass: { type: Object, default: () => ({}) },
    revertPreviewTaskId: { type: Number, default: null },
});

const emit = defineEmits(['select', 'date-change']);

const leftScroll = ref(null);
const rightScroll = ref(null);
const syncing = ref(false);
const previews = ref({});
const dragState = ref(null);
const hoveredMilestone = ref(null);
/** Task id đang chờ server xác nhận sau kéo Gantt — giữ preview đến khi flatRows cập nhật. */
const pendingDateSaveId = ref(null);

const flatRowsRef = computed(() => props.flatRows);
const { totalHeight, visibleItems } = useVirtualScroll(rightScroll, flatRowsRef);

const statusClass = (task) => props.statusBarClass[task.status?.value] || 'bg-slate-400';

const effectiveDates = (task) => {
    const p = previews.value[task.id];
    if (!p) return { start_date: task.start_date, due_date: task.due_date };
    return { start_date: p.start, due_date: p.end };
};

const effectiveBarStyle = (task) => props.barStyle({ ...task, ...effectiveDates(task) });

const assigneeLabel = (task) => {
    const people = props.getAssignees(task);
    if (!people.length) return '—';
    if (people.length === 1) return people[0].name;
    return `${people[0].name} +${people.length - 1}`;
};

const assigneeTitle = (task) => props.getAssignees(task).map((a) => a.name).join(', ');

const durationLabel = (task) => {
    const { start_date, due_date } = effectiveDates(task);
    if (!start_date || !due_date) return '—';
    const s = new Date(`${start_date}T00:00:00`);
    const e = new Date(`${due_date}T00:00:00`);
    const days = Math.round((e - s) / 86400000) + 1;
    return days > 0 ? `${days} ngày` : '—';
};

const LIST_GRID_COLS = 'grid-cols-[minmax(0,1fr)_minmax(128px,38%)_minmax(76px,auto)]';

const dateRangeTitle = (task) => {
    const { start_date, due_date } = effectiveDates(task);
    if (!start_date && !due_date) return '';
    const parts = [];
    if (start_date) parts.push(date(start_date));
    if (due_date) parts.push(date(due_date));
    return parts.join(' → ');
};

const rowCenterY = (item) => item.y + item.h / 2;

const svgLinks = computed(() => {
    const yByTask = new Map();
    visibleItems.value.forEach((item) => {
        if (item.row.type === 'task') yByTask.set(item.row.task.id, rowCenterY(item));
    });

    return props.dependencyLinks
        .map((link) => {
            const y1 = yByTask.get(link.fromId);
            const y2 = yByTask.get(link.toId);
            if (y1 == null || y2 == null) return null;
            const x1 = ((link.fromLeft + link.fromWidth) / 100) * props.timelineWidth;
            const x2 = (link.toLeft / 100) * props.timelineWidth;
            const mid = (x1 + x2) / 2;
            return {
                ...link,
                d: `M ${x1} ${y1} C ${mid} ${y1}, ${mid} ${y2}, ${x2} ${y2}`,
            };
        })
        .filter(Boolean);
});

const syncScroll = (source, target) => {
    if (syncing.value || !source || !target) return;
    syncing.value = true;
    target.scrollTop = source.scrollTop;
    requestAnimationFrame(() => { syncing.value = false; });
};

const onLeftScroll = () => syncScroll(leftScroll.value, rightScroll.value);
const onRightScroll = () => syncScroll(rightScroll.value, leftScroll.value);

const fmt = (d) =>
    `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;

const DRAG_THRESHOLD_PX = 4;

const onBarPointerDown = (event, task, mode = 'move') => {
    if (!props.editable || event.button !== 0) return;
    event.stopPropagation();
    const dates = effectiveDates(task);
    dragState.value = {
        id: task.id,
        mode,
        startX: event.clientX,
        startY: event.clientY,
        moved: false,
        originStart: dates.start_date,
        originEnd: dates.due_date,
    };
    window.addEventListener('pointermove', onBarPointerMove);
    window.addEventListener('pointerup', onBarPointerUp);
};

const onBarClick = (task) => {
    if (props.editable) return;
    emit('select', task.id);
};

const onBarPointerMove = (event) => {
    if (!dragState.value) return;
    const dx = event.clientX - dragState.value.startX;
    const dy = event.clientY - dragState.value.startY;
    if (Math.abs(dx) > DRAG_THRESHOLD_PX || Math.abs(dy) > DRAG_THRESHOLD_PX) {
        dragState.value.moved = true;
    }
    const days = Math.round(dx / Math.max(props.columnWidth * 0.6, 24));
    if (!days && dragState.value.mode === 'move') return;

    const start = new Date(`${dragState.value.originStart}T00:00:00`);
    const end = new Date(`${dragState.value.originEnd}T00:00:00`);

    if (dragState.value.mode === 'move') {
        start.setDate(start.getDate() + days);
        end.setDate(end.getDate() + days);
    } else if (dragState.value.mode === 'resize-left') {
        start.setDate(start.getDate() + days);
        if (start > end) start.setTime(end.getTime());
    } else if (dragState.value.mode === 'resize-right') {
        end.setDate(end.getDate() + days);
        if (end < start) end.setTime(start.getTime());
    }

    previews.value = {
        ...previews.value,
        [dragState.value.id]: { start: fmt(start), end: fmt(end) },
    };
};

const clearPreview = (taskId) => {
    if (!taskId || !previews.value[taskId]) return;
    const next = { ...previews.value };
    delete next[taskId];
    previews.value = next;
};

const syncPreviewAfterSave = () => {
    const id = pendingDateSaveId.value;
    if (!id) return;
    const preview = previews.value[id];
    if (!preview) {
        pendingDateSaveId.value = null;
        return;
    }
    const row = props.flatRows.find((r) => r.type === 'task' && r.task?.id === id);
    if (row && row.task.start_date === preview.start && row.task.due_date === preview.end) {
        clearPreview(id);
        pendingDateSaveId.value = null;
    }
};

const onBarPointerUp = () => {
    const state = dragState.value;
    const id = state?.id;
    const preview = id ? previews.value[id] : null;

    if (id && preview && state?.moved) {
        pendingDateSaveId.value = id;
        emit('date-change', { id, start: preview.start, end: preview.end });
    } else if (id && state && !state.moved) {
        emit('select', id);
        clearPreview(id);
    } else if (!pendingDateSaveId.value) {
        previews.value = {};
    }

    dragState.value = null;
    window.removeEventListener('pointermove', onBarPointerMove);
    window.removeEventListener('pointerup', onBarPointerUp);
};

watch(
    () => props.flatRows,
    () => syncPreviewAfterSave(),
    { deep: true },
);

watch(
    () => props.revertPreviewTaskId,
    (id) => {
        if (id) {
            clearPreview(id);
            if (pendingDateSaveId.value === id) pendingDateSaveId.value = null;
        }
    },
);
</script>

<template>
  <!-- h-full fills whatever the parent gives us (ProjectTimelineCenter flex-1) -->
  <div class="flex h-full flex-col overflow-hidden border-t border-slate-200 bg-white">
    <div class="grid min-h-0 flex-1 grid-cols-[3fr_7fr]">
      <!-- ── Left panel: task grid ── -->
      <div class="z-20 flex min-w-0 flex-col border-r border-slate-200 bg-slate-50/90">
        <!-- Header row -->
        <div
          class="grid h-9 w-full shrink-0 items-center gap-2 border-b border-slate-200 bg-slate-50 px-2.5 text-[10px] font-semibold uppercase tracking-wide text-slate-400"
          :class="LIST_GRID_COLS"
        >
          <span>Công việc</span>
          <span>Người TH</span>
          <span class="whitespace-nowrap text-right">Số ngày</span>
        </div>
        <!-- Scrollable list (synced with right panel) -->
        <div
          ref="leftScroll"
          class="min-h-0 flex-1 overflow-auto"
          @scroll="onLeftScroll"
        >
          <div
            class="relative w-full min-w-0"
            :style="{ height: `${totalHeight}px` }"
          >
            <div
              v-for="item in visibleItems"
              :key="`L-${item.row.type}-${item.row.task?.id || item.row.key || item.row.milestone?.id}`"
              class="absolute left-0 right-0 border-b border-slate-100"
              :style="{ top: `${item.y}px`, height: `${item.h}px` }"
            >
              <!-- Sprint group -->
              <button
                v-if="item.row.type === 'sprint'"
                type="button"
                class="flex h-full w-full items-center gap-1.5 bg-white px-2.5 text-left hover:bg-slate-50"
                @click="toggleGroup(item.row.key)"
              >
                <AppIcon
                  :name="isGroupOpen(item.row.key) ? 'chevron-down' : 'chevron-right'"
                  :size="14"
                  class="shrink-0 text-slate-400"
                />
                <div class="min-w-0 flex-1">
                  <div class="flex flex-wrap items-center gap-1.5">
                    <span
                      class="text-xs font-semibold text-slate-800"
                      :title="item.row.label"
                    >{{ item.row.label }}</span>
                    <span
                      v-if="item.row.status?.label"
                      class="rounded-full px-1.5 py-0.5 text-[9px] font-semibold"
                      :class="{
                        'bg-slate-100 text-slate-600': item.row.status?.color === 'slate',
                        'bg-sky-100 text-sky-700': item.row.status?.color === 'sky',
                        'bg-emerald-100 text-emerald-700': item.row.status?.color === 'emerald',
                      }"
                    >
                      {{ item.row.status.label }}
                    </span>
                  </div>
                  <p
                    v-if="item.row.meta"
                    class="truncate text-[10px] text-slate-400"
                  >
                    {{ item.row.meta }}
                  </p>
                </div>
                <span class="ml-1 shrink-0 rounded-full bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-500">{{ item.row.count }}</span>
              </button>

              <!-- Phase group (nested or top-level) -->
              <button
                v-else-if="item.row.type === 'phase'"
                type="button"
                class="flex h-full w-full items-center gap-1.5 text-left hover:bg-violet-50/60"
                :class="item.row.indent ? 'bg-violet-50/30 pl-8 pr-2.5' : 'bg-violet-50/50 px-2.5'"
                @click="toggleGroup(item.row.key)"
              >
                <AppIcon
                  :name="isGroupOpen(item.row.key) ? 'chevron-down' : 'chevron-right'"
                  :size="14"
                  class="shrink-0 text-violet-400"
                />
                <div class="min-w-0 flex-1">
                  <span
                    class="text-xs font-semibold text-violet-900"
                    :title="item.row.label"
                  >{{ item.row.label }}</span>
                  <p
                    v-if="item.row.meta"
                    class="truncate text-[10px] text-violet-600/80"
                  >
                    {{ item.row.meta }}
                  </p>
                </div>
                <span class="ml-1 shrink-0 rounded-full bg-violet-100 px-1.5 py-0.5 text-[10px] font-medium text-violet-700">{{ item.row.count }}</span>
              </button>

              <!-- Task row -->
              <div
                v-else-if="item.row.type === 'task'"
                class="grid h-full cursor-pointer items-start gap-2 py-1 text-xs hover:bg-white"
                :class="[
                  LIST_GRID_COLS,
                  item.row.groupKey?.includes(':phase-') ? 'bg-white pr-2.5' : 'px-2.5',
                  item.row.depth ? 'bg-slate-50/60' : '',
                ]"
                :style="{
                  paddingLeft: `${10 + (item.row.groupKey?.includes(':phase-') ? 40 : 10) + (item.row.depth || 0) * 18}px`,
                }"
                @click="emit('select', item.row.task.id)"
              >
                <div class="flex min-w-0 items-start gap-1.5">
                  <span
                    v-if="item.row.depth"
                    class="mt-1 shrink-0 text-[10px] text-slate-400"
                    aria-hidden="true"
                  >↳</span>
                  <span
                    class="mt-1 h-2 w-2 shrink-0 rounded-full"
                    :class="statusClass(item.row.task)"
                    :title="item.row.task.status?.label"
                  />
                  <span
                    class="min-w-0 break-words leading-snug"
                    :class="item.row.depth ? 'text-sm text-slate-600' : 'font-medium text-slate-800'"
                    :title="item.row.task.title"
                  >{{ item.row.task.title }}</span>
                  <AppIcon
                    v-if="isBlocked(item.row.task) || (item.row.task.dependencies || []).length"
                    name="dependency"
                    :size="11"
                    class="mt-0.5 shrink-0 text-amber-500"
                  />
                </div>
                <div
                  class="flex min-w-0 items-center gap-1.5 self-center"
                  :title="assigneeTitle(item.row.task) || undefined"
                >
                  <Avatar
                    v-if="getAssignees(item.row.task)[0]"
                    :name="getAssignees(item.row.task)[0].name"
                    :src="getAssignees(item.row.task)[0].avatar_path"
                    :size="18"
                    class="shrink-0"
                  />
                  <span class="min-w-0 truncate text-slate-700">{{ assigneeLabel(item.row.task) }}</span>
                </div>
                <span
                  class="shrink-0 self-center whitespace-nowrap text-right text-xs font-medium tabular-nums"
                  :class="isOverdue(item.row.task) ? 'text-rose-600' : 'text-slate-500'"
                  :title="dateRangeTitle(item.row.task) || undefined"
                >{{ durationLabel(item.row.task) }}</span>
              </div>

              <!-- Milestone header -->
              <div
                v-else-if="item.row.type === 'milestone-header'"
                class="flex h-full items-center bg-violet-50/60 px-2.5 text-xs font-semibold text-violet-700"
              >
                Milestones
              </div>

              <!-- Milestone row -->
              <div
                v-else-if="item.row.type === 'milestone'"
                class="grid h-full cursor-pointer items-start gap-2 px-2.5 py-1 text-xs hover:bg-violet-50/40"
                :class="LIST_GRID_COLS"
                @click="emit('select', item.row.milestone.id)"
              >
                <span
                  class="flex min-w-0 items-start gap-1 break-words font-medium leading-snug text-violet-700"
                  :title="item.row.milestone.title"
                >
                  <span class="shrink-0 text-violet-600">◆</span>
                  {{ item.row.milestone.title }}
                </span>
                <span
                  class="flex min-w-0 items-center gap-1.5 self-center truncate text-slate-600"
                  :title="assigneeTitle(item.row.milestone) || undefined"
                >{{ assigneeLabel(item.row.milestone) }}</span>
                <span
                  class="shrink-0 self-center whitespace-nowrap text-right text-xs tabular-nums text-slate-500"
                  :title="date(item.row.milestone.due_date || item.row.milestone.start_date) || undefined"
                >{{ date(item.row.milestone.due_date || item.row.milestone.start_date) || '—' }}</span>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ── Right panel: timeline bars ── -->
      <div
        ref="rightScroll"
        class="relative min-w-0 overflow-auto"
        @scroll="onRightScroll"
      >
        <div :style="{ width: `${Math.max(timelineWidth, 640)}px`, minWidth: '100%' }">
          <!-- Column headers (sticky) -->
          <div class="sticky top-0 z-10 flex h-10 border-b border-slate-200 bg-white">
            <div
              v-for="col in columns"
              :key="col.key"
              class="shrink-0 border-r border-slate-100 text-center text-[11px] font-medium text-slate-500"
              :style="{ width: `${columnWidth}px` }"
            >
              <span class="inline-flex h-full items-center justify-center px-1">{{ col.label }}</span>
            </div>
          </div>

          <!-- Timeline rows -->
          <div
            class="relative"
            :style="{ height: `${totalHeight}px` }"
          >
            <!-- Column grid lines -->
            <div class="pointer-events-none absolute inset-0 flex">
              <div
                v-for="col in columns"
                :key="`g-${col.key}`"
                class="shrink-0 border-r border-slate-50"
                :style="{ width: `${columnWidth}px` }"
              />
            </div>

            <!-- Today line -->
            <div
              class="pointer-events-none absolute bottom-0 top-0 z-10 w-px bg-rose-500/80"
              :style="{ left: `${todayPercent}%` }"
            >
              <span class="absolute -left-5 top-0 whitespace-nowrap rounded bg-rose-500 px-1 py-0.5 text-[9px] font-bold uppercase text-white">Today</span>
            </div>

            <!-- Dependency SVG lines -->
            <svg
              class="pointer-events-none absolute left-0 top-0 z-[5] overflow-visible"
              :width="timelineWidth"
              :height="totalHeight"
            >
              <path
                v-for="link in svgLinks"
                :key="link.id"
                :d="link.d"
                fill="none"
                class="dependency-line"
                :class="link.blocked ? 'stroke-rose-400' : 'stroke-slate-300'"
                stroke-width="1.5"
                stroke-dasharray="4 3"
              />
            </svg>

            <!-- Row bars -->
            <div
              v-for="item in visibleItems"
              :key="`R-${item.row.type}-${item.row.task?.id || item.row.key || item.row.milestone?.id}`"
              class="absolute left-0 right-0 border-b border-slate-100"
              :style="{ top: `${item.y}px`, height: `${item.h}px`, width: `${timelineWidth}px` }"
            >
              <!-- Sprint span background -->
              <div
                v-if="item.row.type === 'sprint'"
                class="relative h-full bg-slate-50/70"
              >
                <div
                  v-if="item.row.sprint && sprintSpanStyle(item.row.sprint)"
                  class="absolute top-[6px] h-[22px] rounded-md border border-brand/20 bg-brand/10"
                  :style="sprintSpanStyle(item.row.sprint)"
                  :title="`${item.row.label} · ${item.row.meta}`"
                />
              </div>

              <!-- Phase group background -->
              <div
                v-else-if="item.row.type === 'phase'"
                class="h-full"
                :class="item.row.indent ? 'bg-violet-50/25' : 'bg-violet-50/40'"
              />

              <!-- Task bar -->
              <div
                v-else-if="item.row.type === 'task'"
                class="relative h-full cursor-pointer"
                @click.self="emit('select', item.row.task.id)"
              >
                <div
                  v-if="effectiveBarStyle(item.row.task)"
                  class="absolute top-[7px] flex h-[26px] cursor-pointer items-center rounded-md text-[10px] font-semibold text-white shadow-sm transition-shadow hover:shadow-md"
                  :class="[
                    statusClass(item.row.task),
                    isOverdue(item.row.task) ? 'ring-2 ring-rose-300' : '',
                    dragState?.id === item.row.task.id ? 'opacity-90 ring-2 ring-brand/40' : '',
                  ]"
                  :style="effectiveBarStyle(item.row.task)"
                  :title="`${item.row.task.title} · ${effectiveDates(item.row.task).start_date} → ${effectiveDates(item.row.task).due_date}`"
                  @click.stop="onBarClick(item.row.task)"
                >
                  <!-- Progress fill inside bar -->
                  <span
                    class="pointer-events-none absolute inset-0 rounded-md bg-black/10"
                    :style="{ width: `${item.row.task.progress || 0}%` }"
                  />
                  <!-- Resize left handle -->
                  <span
                    v-if="editable"
                    class="relative z-10 w-2 shrink-0 cursor-ew-resize self-stretch rounded-l-md bg-black/20 hover:bg-black/30"
                    @pointerdown.stop="onBarPointerDown($event, item.row.task, 'resize-left')"
                  />
                  <!-- Label -->
                  <span
                    class="relative z-10 flex flex-1 cursor-grab items-center truncate px-1.5 active:cursor-grabbing"
                    @pointerdown.stop="onBarPointerDown($event, item.row.task, 'move')"
                  >
                    {{ item.row.task.progress || 0 }}%
                  </span>
                  <!-- Resize right handle -->
                  <span
                    v-if="editable"
                    class="relative z-10 w-2 shrink-0 cursor-ew-resize self-stretch rounded-r-md bg-black/20 hover:bg-black/30"
                    @pointerdown.stop="onBarPointerDown($event, item.row.task, 'resize-right')"
                  />
                </div>
                <!-- No-date indicator -->
                <button
                  v-else
                  type="button"
                  class="absolute left-2 top-[10px] text-[10px] italic text-slate-400 hover:text-brand"
                  @click.stop="emit('select', item.row.task.id)"
                >
                  chưa lên lịch
                </button>
              </div>

              <!-- Milestone header bg -->
              <div
                v-else-if="item.row.type === 'milestone-header'"
                class="h-full bg-violet-50/40"
              />

              <!-- Milestone diamond -->
              <div
                v-else-if="item.row.type === 'milestone'"
                class="relative h-full"
              >
                <button
                  v-if="milestonePercent(item.row.milestone) !== null"
                  type="button"
                  class="absolute top-1/2 z-20 -translate-x-1/2 -translate-y-1/2 text-xl text-violet-600 transition-transform hover:scale-125"
                  :style="{ left: `${milestonePercent(item.row.milestone)}%` }"
                  @mouseenter="hoveredMilestone = item.row.milestone"
                  @mouseleave="hoveredMilestone = null"
                  @click="emit('select', item.row.milestone.id)"
                >
                  ◆
                </button>
                <!-- Milestone tooltip -->
                <div
                  v-if="hoveredMilestone?.id === item.row.milestone.id"
                  class="absolute z-30 mt-7 w-52 rounded-lg border border-slate-200 bg-white p-2.5 text-xs shadow-lg"
                  :style="{ left: `${milestonePercent(item.row.milestone)}%` }"
                >
                  <p class="font-semibold text-slate-800">
                    {{ item.row.milestone.title }}
                  </p>
                  <p class="mt-1 text-slate-500">
                    Hạn: {{ item.row.milestone.due_date || '—' }}
                  </p>
                  <p class="text-slate-500">
                    Trạng thái: {{ item.row.milestone.status?.label }}
                  </p>
                  <p
                    v-if="getAssignees(item.row.milestone)[0]"
                    class="text-slate-500"
                  >
                    Owner: {{ getAssignees(item.row.milestone)[0].name }}
                  </p>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<style scoped>
.dependency-line {
    animation: dash 1.2s linear infinite;
}
@keyframes dash {
    to { stroke-dashoffset: -14; }
}
</style>
