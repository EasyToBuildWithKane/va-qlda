<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import viLocale from '@fullcalendar/core/locales/vi';
import AppIcon from '@/Components/AppIcon.vue';
import { useProjectCalendar } from '@/composables/useProjectCalendar';

const props = defineProps({
    project: { type: Object, required: true },
    tasks: { type: Array, default: () => [] },
    sprints: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
    revertTaskId: { type: Number, default: null },
});

const emit = defineEmits(['create-task', 'select-task', 'date-change']);

const { events, kpis, legend, hasScheduledData, toYmd, addDays } = useProjectCalendar({
    tasks: () => props.tasks,
    sprints: () => props.sprints,
    editable: () => props.canContribute,
});

const calendarRef = ref(null);
const bodyRef = ref(null);
const api = () => calendarRef.value?.getApi();

// Tab dùng v-show: FullCalendar mount khi đang display:none → đo sai chiều cao.
// Khi container hiện lại (kích thước 0 → >0) gọi updateSize() để vẽ lại.
let resizeObserver = null;
onMounted(() => {
    if (typeof ResizeObserver === 'undefined' || !bodyRef.value) return;
    let lastW = 0;
    resizeObserver = new ResizeObserver((entries) => {
        const w = entries[0]?.contentRect.width || 0;
        if (w > 0 && w !== lastW) api()?.updateSize();
        lastW = w;
    });
    resizeObserver.observe(bodyRef.value);
});
onBeforeUnmount(() => resizeObserver?.disconnect());

const viewTitle = ref('');
const currentView = ref('dayGridMonth');

const VIEWS = [
    { key: 'dayGridMonth', label: 'Tháng' },
    { key: 'dayGridWeek', label: 'Tuần' },
    { key: 'listMonth', label: 'Danh sách' },
];

const kpiChips = computed(() => {
    const k = kpis.value;
    const chips = [
        { key: 'total', label: 'Tổng', value: k.total, chip: 'bg-slate-100 text-slate-600', dot: 'bg-slate-400' },
        { key: 'inProgress', label: 'Đang làm', value: k.inProgress, chip: 'bg-sky-50 text-sky-700', dot: 'bg-sky-500' },
        { key: 'completed', label: 'Hoàn thành', value: k.completed, chip: 'bg-emerald-50 text-emerald-700', dot: 'bg-emerald-500' },
        {
            key: 'overdue',
            label: 'Quá hạn',
            value: k.overdue,
            chip: k.overdue ? 'bg-rose-50 text-rose-700' : 'bg-slate-100 text-slate-400',
            dot: k.overdue ? 'bg-rose-500' : 'bg-slate-300',
        },
    ];
    if (k.milestones) {
        chips.push({ key: 'milestones', label: 'Mốc', value: k.milestones, chip: 'bg-violet-50 text-violet-700', prefix: '◆' });
    }
    if (k.unscheduled) {
        chips.push({ key: 'unscheduled', label: 'Chưa lên lịch', value: k.unscheduled, chip: 'bg-amber-50 text-amber-700', dot: 'bg-amber-400' });
    }
    return chips;
});

const calendarOptions = reactive({
    plugins: [dayGridPlugin, listPlugin, interactionPlugin],
    initialView: 'dayGridMonth',
    locale: viLocale,
    firstDay: 1,
    headerToolbar: false,
    height: '100%',
    expandRows: true,
    dayMaxEvents: 4,
    fixedWeekCount: false,
    weekends: true,
    editable: props.canContribute,
    eventStartEditable: props.canContribute,
    eventDurationEditable: props.canContribute,
    displayEventTime: false,
    noEventsContent: 'Không có công việc nào trong khoảng này',
    events: (info, success) => success(events.value),
    datesSet: (arg) => {
        viewTitle.value = arg.view.title;
        currentView.value = arg.view.type;
    },
    eventContent: renderEvent,
    eventDidMount: decorateEvent,
    eventClick: handleEventClick,
    eventDrop: handleMove,
    eventResize: handleMove,
});

// Sync calendar khi danh sách task đổi (sau khi Inertia reload, hoặc revert lỗi).
watch(events, () => api()?.refetchEvents());
watch(
    () => props.revertTaskId,
    (id) => {
        if (id) api()?.refetchEvents();
    },
);

function renderEvent(arg) {
    const p = arg.event.extendedProps;
    const wrap = document.createElement('div');
    wrap.className = 'pc-ev-body';

    const marker = document.createElement('span');
    if (p.milestone) {
        marker.className = 'pc-ev-diamond';
        marker.textContent = '◆';
    } else {
        marker.className = 'pc-ev-dot';
        marker.style.backgroundColor = p.accent;
    }
    marker.setAttribute('aria-hidden', 'true');
    wrap.appendChild(marker);

    const title = document.createElement('span');
    title.className = 'pc-ev-title';
    title.textContent = arg.event.title;
    wrap.appendChild(title);

    if (!p.milestone && p.status === 'in_progress' && p.progress > 0) {
        const prog = document.createElement('span');
        prog.className = 'pc-ev-progress';
        prog.textContent = `${p.progress}%`;
        wrap.appendChild(prog);
    }

    return { domNodes: [wrap] };
}

function decorateEvent(arg) {
    const p = arg.event.extendedProps;
    const range = [p.startDate, p.dueDate]
        .filter(Boolean)
        .map((d) => new Date(`${d}T00:00:00`).toLocaleDateString('vi-VN', { day: '2-digit', month: '2-digit', year: 'numeric' }));
    const tip = [
        arg.event.title,
        p.statusLabel ? `Trạng thái: ${p.statusLabel}` : null,
        p.milestone ? 'Mốc dự án' : null,
        p.sprintName ? `Sprint: ${p.sprintName}` : null,
        range.length ? `Thời gian: ${[...new Set(range)].join(' → ')}` : null,
        p.assignees.length ? `Phụ trách: ${p.assignees.map((a) => a.name).join(', ')}` : null,
        p.overdue ? '⚠ Quá hạn' : null,
    ]
        .filter(Boolean)
        .join('\n');
    arg.el.setAttribute('title', tip);
}

function handleEventClick(arg) {
    emit('select-task', arg.event.extendedProps.taskId);
}

function handleMove(arg) {
    const e = arg.event;
    const start = toYmd(e.start);
    // all-day end là exclusive → trừ 1 ngày để ra hạn thực.
    const end = e.end ? addDays(toYmd(e.end), -1) : start;
    emit('date-change', { id: e.extendedProps.taskId, start, end });
}

function goToday() {
    api()?.today();
}
function goPrev() {
    api()?.prev();
}
function goNext() {
    api()?.next();
}
function changeView(key) {
    api()?.changeView(key);
}
</script>

<template>
  <div class="flex h-full flex-col overflow-hidden bg-white">
    <!-- Toolbar -->
    <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 bg-white px-4 py-2.5">
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="h-8 rounded-md border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 hover:border-slate-300 hover:bg-slate-50"
          @click="goToday"
        >
          Hôm nay
        </button>
        <div class="flex items-center">
          <button
            type="button"
            class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100"
            aria-label="Trước"
            @click="goPrev"
          >
            <AppIcon
              name="chevron-left"
              :size="18"
            />
          </button>
          <button
            type="button"
            class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100"
            aria-label="Sau"
            @click="goNext"
          >
            <AppIcon
              name="chevron-right"
              :size="18"
            />
          </button>
        </div>
        <h2 class="ml-1 font-display text-base font-semibold capitalize text-slate-800">
          {{ viewTitle }}
        </h2>
      </div>

      <div class="flex-1" />

      <!-- KPI chips -->
      <div class="hidden min-w-0 flex-wrap items-center gap-1.5 md:flex">
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

      <!-- View switcher -->
      <div class="inline-flex shrink-0 rounded-md border border-slate-200 bg-slate-50 p-0.5">
        <button
          v-for="v in VIEWS"
          :key="v.key"
          type="button"
          class="rounded px-2.5 py-1 text-xs font-medium transition"
          :class="currentView === v.key ? 'bg-white text-brand shadow-sm' : 'text-slate-500 hover:text-slate-700'"
          @click="changeView(v.key)"
        >
          {{ v.label }}
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

    <!-- Legend -->
    <div
      v-if="legend.length"
      class="flex flex-wrap items-center gap-x-4 gap-y-1.5 border-b border-slate-100 bg-slate-50/60 px-4 py-1.5"
    >
      <span class="text-[10px] font-semibold uppercase tracking-wide text-slate-400">Chú thích</span>
      <span
        v-for="item in legend"
        :key="item.value"
        class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-600"
      >
        <span
          v-if="item.milestone"
          class="text-[11px] leading-none"
          :style="{ color: item.color }"
        >◆</span>
        <span
          v-else
          class="h-2.5 w-2.5 rounded-sm"
          :style="{ backgroundColor: item.color }"
        />
        {{ item.label }}
      </span>
    </div>

    <!-- Calendar body -->
    <div
      ref="bodyRef"
      class="relative min-h-0 flex-1"
    >
      <FullCalendar
        v-if="hasScheduledData"
        ref="calendarRef"
        class="pc-calendar h-full"
        :options="calendarOptions"
      />

      <!-- Empty state -->
      <div
        v-else
        class="flex h-full flex-col items-center justify-center p-8 text-center"
      >
        <div class="mb-4 grid h-16 w-16 place-items-center rounded-2xl bg-brand-50 text-brand">
          <AppIcon
            name="calendar"
            :size="28"
          />
        </div>
        <h3 class="font-display text-lg font-semibold text-slate-800">
          Chưa có công việc trên lịch
        </h3>
        <p class="mt-1 max-w-sm text-sm text-slate-500">
          Thêm công việc có ngày bắt đầu hoặc hạn hoàn thành để hiển thị trên lịch dự án.
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
          /> Tạo công việc đầu tiên
        </button>
      </div>
    </div>
  </div>
</template>

<style>
.pc-calendar .fc {
    --fc-border-color: #eef2f7;
    --fc-today-bg-color: #fdf2f6;
    --fc-page-bg-color: #ffffff;
    --fc-neutral-bg-color: #f8fafc;
    font-size: 13px;
}
.pc-calendar .fc .fc-col-header-cell-cushion {
    padding: 8px 6px;
    font-size: 11px;
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.03em;
    color: #94a3b8;
}
.pc-calendar .fc .fc-daygrid-day.fc-day-today {
    background-color: #fdf2f6;
}
.pc-calendar .fc .fc-daygrid-day-number {
    font-size: 12px;
    font-weight: 500;
    color: #475569;
    padding: 5px 7px;
}
.pc-calendar .fc .fc-day-today .fc-daygrid-day-number {
    color: #9a0036;
    font-weight: 700;
}
.pc-calendar .fc .fc-day-sat .fc-daygrid-day-number,
.pc-calendar .fc .fc-day-sun .fc-daygrid-day-number {
    color: #94a3b8;
}

/* ── Event chip ── */
.pc-calendar .fc-daygrid-event {
    border-radius: 7px;
    border-width: 1px;
    border-style: solid;
    padding: 0;
    margin: 1px 2px;
    box-shadow: 0 1px 1.5px rgba(15, 23, 42, 0.05);
    transition: box-shadow 0.15s ease, transform 0.15s ease;
    white-space: normal;
}
.pc-calendar .fc-daygrid-event:hover {
    box-shadow: 0 3px 10px rgba(15, 23, 42, 0.13);
    transform: translateY(-1px);
    z-index: 3;
}
.pc-calendar .fc-daygrid-event .fc-event-main {
    padding: 2px 6px;
    color: inherit;
}
.pc-ev-body {
    display: flex;
    align-items: center;
    gap: 5px;
    min-width: 0;
    line-height: 1.35;
}
.pc-ev-dot {
    flex-shrink: 0;
    width: 7px;
    height: 7px;
    border-radius: 9999px;
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.7);
}
.pc-ev-diamond {
    flex-shrink: 0;
    font-size: 10px;
    line-height: 1;
    color: #7c3aed;
}
.pc-ev-title {
    flex: 1 1 auto;
    min-width: 0;
    font-weight: 600;
    font-size: 12px;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.pc-ev-progress {
    flex-shrink: 0;
    font-size: 10px;
    font-weight: 700;
    font-variant-numeric: tabular-nums;
    opacity: 0.85;
}

/* Milestone: nền nhạt, chữ tím, gạch nhẹ */
.pc-ev--milestone {
    background-image: linear-gradient(0deg, rgba(124, 58, 237, 0.04), rgba(124, 58, 237, 0.04));
}
.pc-ev--milestone .pc-ev-title {
    color: #5b21b6;
}
/* Quá hạn: viền đỏ rõ */
.pc-ev--overdue {
    box-shadow: 0 0 0 1px #fecaca, 0 1px 1.5px rgba(239, 68, 68, 0.18);
}
/* Hoàn thành: tiêu đề mờ + gạch ngang */
.pc-ev--done .pc-ev-title {
    text-decoration: line-through;
    opacity: 0.7;
}

/* ── List view ── */
.pc-calendar .fc .fc-list-event:hover td {
    background-color: #fdf2f6;
}
.pc-calendar .fc .fc-list-event-dot {
    border-color: currentColor;
}
.pc-calendar .fc .fc-list-day-cushion {
    background-color: #f8fafc;
}
.pc-calendar .fc .fc-list-event .pc-ev-body {
    white-space: nowrap;
}
.pc-calendar .fc-theme-standard .fc-list,
.pc-calendar .fc-theme-standard td,
.pc-calendar .fc-theme-standard th {
    border-color: #eef2f7;
}
.pc-calendar .fc .fc-more-link {
    font-size: 11px;
    font-weight: 600;
    color: #9a0036;
    padding: 1px 4px;
}
</style>
