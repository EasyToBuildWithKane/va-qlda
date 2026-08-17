<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import viLocale from '@fullcalendar/core/locales/vi';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridSegmentedControl from '@/shared/ui/DatagridSegmentedControl.vue';
import EmptyState from '@/shared/ui/EmptyState.vue';
import Avatar from '@/shared/ui/Avatar.vue';
import { useProjectCalendar, CALENDAR_STATUS_PALETTE, MILESTONE_PALETTE } from '@/composables/useProjectCalendar';

const props = defineProps({
    project: { type: Object, required: true },
    tasks: { type: Array, default: () => [] },
    sprints: { type: Array, default: () => [] },
    canManage: { type: Boolean, default: false },
    canContribute: { type: Boolean, default: false },
    revertTaskId: { type: Number, default: null },
});

const emit = defineEmits(['create-task', 'select-task', 'date-change']);

const VIEW_STORAGE_KEY = computed(() => `va-project-calendar-view:${props.project?.id || 'x'}`);
const SIDEBAR_STORAGE_KEY = computed(() => `va-project-calendar-sidebar:${props.project?.id || 'x'}`);

const savedView = (() => {
    try {
        return localStorage.getItem(`va-project-calendar-view:${props.project?.id}`) || 'dayGridMonth';
    } catch {
        return 'dayGridMonth';
    }
})();

const filters = reactive({
    search: '',
    status: null,
    sprintId: null,
    assigneeId: null,
    kpi: null,
});

const {
    events,
    kpis,
    hasScheduledData,
    hasVisibleEvents,
    hasActiveFilters,
    unscheduledTasks,
    statusFilterOptions,
    assigneeFilterOptions,
    sprintFilterOptions,
    toYmd,
    addDays,
} = useProjectCalendar({
    tasks: () => props.tasks,
    sprints: () => props.sprints,
    editable: () => props.canContribute,
    filters: () => filters,
});

const calendarRef = ref(null);
const bodyRef = ref(null);
const filterOpen = ref(false);
const filterPanelRef = ref(null);
const sidebarOpen = ref((() => {
    try {
        const raw = localStorage.getItem(`va-project-calendar-sidebar:${props.project?.id}`);
        if (raw === '0') return false;
        if (raw === '1') return true;
    } catch { /* ignore */ }
    return true;
})());

const api = () => calendarRef.value?.getApi();

let resizeObserver = null;
onMounted(() => {
    document.addEventListener('mousedown', onDocPointer);
    if (typeof ResizeObserver === 'undefined' || !bodyRef.value) return;
    let lastW = 0;
    resizeObserver = new ResizeObserver((entries) => {
        const w = entries[0]?.contentRect.width || 0;
        if (w > 0 && w !== lastW) api()?.updateSize();
        lastW = w;
    });
    resizeObserver.observe(bodyRef.value);
});
onBeforeUnmount(() => {
    document.removeEventListener('mousedown', onDocPointer);
    resizeObserver?.disconnect();
    hideHover();
});

function onDocPointer(e) {
    if (filterOpen.value && filterPanelRef.value && !filterPanelRef.value.contains(e.target)) {
        filterOpen.value = false;
    }
}

const viewTitle = ref('');
const currentView = ref(savedView);

const VIEW_ITEMS = [
    { key: 'dayGridMonth', label: 'Tháng', icon: 'calendar', title: 'Lịch tháng' },
    { key: 'timeGridWeek', label: 'Tuần', icon: 'calendar-clock', title: 'Lịch tuần' },
    { key: 'timeGridDay', label: 'Ngày', icon: 'clock', title: 'Lịch ngày' },
    { key: 'listMonth', label: 'Danh sách', icon: 'list', title: 'Danh sách theo tháng' },
];

// Cùng một nguồn màu với event trên lịch — chip nào, màu ô đó, không lệch tông.
const TONE_SLATE = { main: '#475569', soft: '#f1f5f9' };
const TONE_AMBER = { main: '#d97706', soft: '#fef3c7' };

const kpiChips = computed(() => {
    const k = kpis.value;
    const chips = [
        { key: null, label: 'Tổng', value: k.total, palette: TONE_SLATE },
        { key: 'inProgress', label: 'Đang làm', value: k.inProgress, palette: CALENDAR_STATUS_PALETTE.in_progress },
        { key: 'completed', label: 'Hoàn thành', value: k.completed, palette: CALENDAR_STATUS_PALETTE.done },
        { key: 'overdue', label: 'Quá hạn', value: k.overdue, palette: CALENDAR_STATUS_PALETTE.blocked },
    ];
    if (k.milestones) {
        chips.push({ key: 'milestones', label: 'Mốc', value: k.milestones, palette: MILESTONE_PALETTE, prefix: '◆' });
    }
    if (k.unscheduled) {
        chips.push({ key: 'unscheduled', label: 'Chưa lịch', value: k.unscheduled, palette: TONE_AMBER });
    }
    return chips;
});

const activeFilterCount = computed(() => {
    let n = 0;
    if (filters.status) n += 1;
    if (filters.sprintId != null && filters.sprintId !== '') n += 1;
    if (filters.assigneeId != null && filters.assigneeId !== '') n += 1;
    return n;
});

const hover = ref({
    show: false,
    x: 0,
    y: 0,
    above: false,
    props: null,
    title: '',
});

const calendarOptions = reactive({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: savedView,
    locale: viLocale,
    firstDay: 1,
    headerToolbar: false,
    height: '100%',
    expandRows: true,
    dayMaxEvents: 3,
    fixedWeekCount: false,
    weekends: true,
    nowIndicator: true,
    slotMinTime: '07:00:00',
    slotMaxTime: '20:00:00',
    allDaySlot: true,
    allDayText: 'Cả ngày',
    editable: props.canContribute,
    eventStartEditable: props.canContribute,
    eventDurationEditable: props.canContribute,
    selectable: props.canManage,
    selectMirror: true,
    displayEventTime: false,
    noEventsContent: 'Không có công việc nào trong khoảng này',
    events: (info, success) => success(events.value),
    datesSet: (arg) => {
        viewTitle.value = arg.view.title;
        currentView.value = arg.view.type;
        try {
            localStorage.setItem(VIEW_STORAGE_KEY.value, arg.view.type);
        } catch { /* ignore */ }
    },
    eventContent: renderEvent,
    eventDidMount: decorateEvent,
    eventClick: handleEventClick,
    eventDrop: handleMove,
    eventResize: handleMove,
    eventMouseEnter: handleEventEnter,
    eventMouseLeave: hideHover,
    dateClick: handleDateClick,
    select: handleSelect,
});

watch(events, () => api()?.refetchEvents());
watch(
    () => props.revertTaskId,
    (id) => {
        if (id) api()?.refetchEvents();
    },
);
watch(sidebarOpen, (open) => {
    try {
        localStorage.setItem(SIDEBAR_STORAGE_KEY.value, open ? '1' : '0');
    } catch { /* ignore */ }
    requestAnimationFrame(() => api()?.updateSize());
});

function formatRange(start, due) {
    const fmt = (d) => {
        if (!d) return null;
        return new Date(`${d}T00:00:00`).toLocaleDateString('vi-VN', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
        });
    };
    const a = fmt(start);
    const b = fmt(due);
    if (a && b && a !== b) return `${a} → ${b}`;
    return a || b || 'Chưa cập nhật';
}

function avatarInitial(name) {
    const parts = String(name || '?').trim().split(/\s+/);
    const last = parts[parts.length - 1] || '?';
    return (last[0] || '?').toUpperCase();
}

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

    const people = p.assignees || [];
    if (people.length && arg.view.type !== 'listMonth') {
        const stack = document.createElement('span');
        stack.className = 'pc-ev-avatars';
        people.slice(0, 2).forEach((a) => {
            const av = document.createElement('span');
            av.className = 'pc-ev-avatar';
            av.title = a.name || '';
            if (a.avatar) {
                const img = document.createElement('img');
                img.src = a.avatar;
                img.alt = a.name || '';
                img.className = 'pc-ev-avatar__img';
                img.onerror = () => {
                    av.textContent = avatarInitial(a.name);
                    img.remove();
                };
                av.appendChild(img);
            } else {
                av.textContent = avatarInitial(a.name);
            }
            stack.appendChild(av);
        });
        if (people.length > 2) {
            const more = document.createElement('span');
            more.className = 'pc-ev-avatar pc-ev-avatar--more';
            more.textContent = `+${people.length - 2}`;
            stack.appendChild(more);
        }
        wrap.appendChild(stack);
    }

    return { domNodes: [wrap] };
}

function decorateEvent(arg) {
    const p = arg.event.extendedProps;
    arg.el.setAttribute('aria-label', [
        arg.event.title,
        p.statusLabel,
        p.overdue ? 'Quá hạn' : null,
    ].filter(Boolean).join(' · '));
}

function handleEventEnter(arg) {
    const p = arg.event.extendedProps;
    const rect = arg.el.getBoundingClientRect();
    const pad = 10;
    const maxW = 288;
    let x = rect.left;
    if (x + maxW > window.innerWidth - pad) x = window.innerWidth - maxW - pad;
    if (x < pad) x = pad;
    const above = rect.bottom + 160 > window.innerHeight - pad;
    hover.value = {
        show: true,
        x,
        y: above ? rect.top - pad : rect.bottom + pad,
        above,
        title: arg.event.title,
        props: p,
    };
}

function hideHover() {
    hover.value = { show: false, x: 0, y: 0, above: false, props: null, title: '' };
}

function handleEventClick(arg) {
    hideHover();
    emit('select-task', arg.event.extendedProps.taskId);
}

function handleMove(arg) {
    const e = arg.event;
    const start = toYmd(e.start);
    const end = e.end ? addDays(toYmd(e.end), -1) : start;
    emit('date-change', { id: e.extendedProps.taskId, start, end });
}

function handleDateClick(arg) {
    if (!props.canManage) return;
    if (arg.view.type.startsWith('timeGrid') && !arg.allDay) return;
    emit('create-task', { start: arg.dateStr.slice(0, 10), end: arg.dateStr.slice(0, 10) });
}

function handleSelect(arg) {
    if (!props.canManage) return;
    const start = toYmd(arg.start);
    const endExclusive = arg.end ? toYmd(arg.end) : start;
    const end = endExclusive ? addDays(endExclusive, -1) : start;
    api()?.unselect();
    emit('create-task', { start, end: end || start });
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
    currentView.value = key;
    api()?.changeView(key);
}

function toggleKpi(key) {
    filters.kpi = filters.kpi === key ? null : key;
    if (key === 'unscheduled' && filters.kpi === 'unscheduled') {
        sidebarOpen.value = true;
    }
}

function clearFilters() {
    filters.search = '';
    filters.status = null;
    filters.sprintId = null;
    filters.assigneeId = null;
    filters.kpi = null;
}

function openCreate() {
    emit('create-task');
}
</script>

<template>
  <div class="pc-shell flex h-full min-h-0 flex-col overflow-hidden bg-slate-50/40">
    <!-- Toolbar -->
    <div class="sticky top-0 z-20 shrink-0 border-b border-slate-200/80 bg-white/95 px-3 py-2.5 backdrop-blur sm:px-4">
      <div class="flex flex-wrap items-center gap-2 lg:flex-nowrap">
        <div class="flex min-w-0 shrink-0 items-center gap-2">
          <span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-gradient-to-br from-brand-50 to-brand-100/80 text-brand ring-1 ring-brand/10">
            <AppIcon
              name="calendar"
              :size="17"
            />
          </span>
          <div class="min-w-0">
            <div class="flex items-center gap-1">
              <button
                type="button"
                class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                aria-label="Kỳ trước"
                @click="goPrev"
              >
                <AppIcon
                  name="chevron-left"
                  :size="18"
                />
              </button>
              <button
                type="button"
                class="grid h-8 w-8 place-items-center rounded-lg text-slate-500 transition hover:bg-slate-100 hover:text-slate-800"
                aria-label="Kỳ sau"
                @click="goNext"
              >
                <AppIcon
                  name="chevron-right"
                  :size="18"
                />
              </button>
              <h2 class="ml-0.5 truncate font-display text-base font-semibold capitalize text-slate-800">
                {{ viewTitle || 'Lịch dự án' }}
              </h2>
            </div>
          </div>
          <button
            type="button"
            class="hidden h-8 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 transition hover:border-brand/30 hover:bg-brand-50 hover:text-brand sm:inline-flex sm:items-center"
            @click="goToday"
          >
            Hôm nay
          </button>
        </div>

        <div class="relative min-w-0 w-full basis-full sm:flex-1 lg:basis-auto">
          <AppIcon
            name="search"
            :size="14"
            class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400"
          />
          <input
            v-model="filters.search"
            type="search"
            class="input h-10 w-full pl-8 text-sm"
            placeholder="Tìm công việc, người phụ trách, sprint…"
            aria-label="Tìm trên lịch"
          >
        </div>

        <div
          ref="filterPanelRef"
          class="relative shrink-0"
        >
          <button
            type="button"
            class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
            :class="activeFilterCount ? 'border-brand/40 text-brand' : ''"
            :aria-expanded="filterOpen"
            @click="filterOpen = !filterOpen"
          >
            <AppIcon
              name="filter"
              :size="14"
            />
            Lọc
            <span
              v-if="activeFilterCount"
              class="grid h-4 min-w-[1rem] place-items-center rounded-full bg-brand px-1 text-[10px] font-bold text-white"
            >{{ activeFilterCount }}</span>
          </button>
          <div
            v-if="filterOpen"
            class="absolute right-0 z-30 mt-1.5 w-72 rounded-xl border border-slate-200 bg-white p-3 shadow-elevation-3"
          >
            <p class="mb-2 text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
              Bộ lọc lịch
            </p>
            <label class="mb-2 block text-xs text-slate-500">
              Trạng thái
              <select
                v-model="filters.status"
                class="input mt-1 h-9 w-full text-sm"
              >
                <option :value="null">
                  Tất cả trạng thái
                </option>
                <option
                  v-for="opt in statusFilterOptions"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </label>
            <label class="mb-2 block text-xs text-slate-500">
              Sprint
              <select
                v-model="filters.sprintId"
                class="input mt-1 h-9 w-full text-sm"
              >
                <option :value="null">
                  Tất cả sprint
                </option>
                <option
                  v-for="opt in sprintFilterOptions"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </label>
            <label class="mb-3 block text-xs text-slate-500">
              Người phụ trách
              <select
                v-model="filters.assigneeId"
                class="input mt-1 h-9 w-full text-sm"
              >
                <option :value="null">
                  Tất cả người phụ trách
                </option>
                <option
                  v-for="opt in assigneeFilterOptions"
                  :key="opt.value"
                  :value="opt.value"
                >
                  {{ opt.label }}
                </option>
              </select>
            </label>
            <div class="flex items-center justify-between gap-2">
              <button
                type="button"
                class="text-xs font-medium text-slate-500 hover:text-brand"
                @click="clearFilters"
              >
                Xóa lọc
              </button>
              <button
                type="button"
                class="btn-primary h-8 text-xs"
                @click="filterOpen = false"
              >
                Áp dụng
              </button>
            </div>
          </div>
        </div>

        <DatagridSegmentedControl
          :model-value="currentView"
          :items="VIEW_ITEMS"
          aria-label="Chế độ xem lịch"
          icon-only-below-sm
          @update:model-value="changeView"
        />

        <button
          type="button"
          class="inline-flex h-10 items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-2.5 text-xs font-medium text-slate-600 transition hover:bg-slate-50"
          :class="sidebarOpen ? 'border-brand/30 text-brand' : ''"
          :title="sidebarOpen ? 'Ẩn cột chưa lên lịch' : 'Hiện cột chưa lên lịch'"
          @click="sidebarOpen = !sidebarOpen"
        >
          <AppIcon
            name="columns"
            :size="14"
          />
          <span class="hidden sm:inline">Chưa lịch</span>
          <span
            v-if="kpis.unscheduled"
            class="rounded-full bg-amber-100 px-1.5 text-[10px] font-bold tabular-nums text-amber-800"
          >{{ kpis.unscheduled }}</span>
        </button>

        <button
          v-if="canManage"
          type="button"
          class="btn-primary h-10 shrink-0 gap-1.5 px-3 text-xs"
          @click="openCreate"
        >
          <AppIcon
            name="add"
            :size="14"
          />
          Tạo task
        </button>
      </div>

      <!-- KPI quick filters (đồng thời là chú thích màu — không lặp với event trên lịch) -->
      <div
        class="mt-2.5 flex flex-wrap items-center gap-1.5"
        role="group"
        aria-label="Lọc nhanh theo thống kê"
      >
        <button
          v-for="chip in kpiChips"
          :key="String(chip.key)"
          type="button"
          class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-[11px] font-medium ring-1 ring-inset transition"
          :style="filters.kpi === chip.key
            ? { backgroundColor: chip.palette.main, color: '#fff', borderColor: chip.palette.main }
            : { backgroundColor: chip.palette.soft, color: chip.palette.text || chip.palette.main }"
          :class="filters.kpi === chip.key ? 'ring-transparent' : 'ring-black/[0.03] hover:ring-black/[0.06]'"
          :aria-pressed="filters.kpi === chip.key"
          @click="toggleKpi(chip.key)"
        >
          <span
            v-if="chip.prefix"
            class="shrink-0 text-[10px] leading-none"
          >{{ chip.prefix }}</span>
          <span
            v-else
            class="h-1.5 w-1.5 shrink-0 rounded-full"
            :style="{ backgroundColor: filters.kpi === chip.key ? 'rgba(255,255,255,0.9)' : chip.palette.main }"
          />
          <span class="opacity-90">{{ chip.label }}</span>
          <span class="font-semibold tabular-nums">{{ chip.value }}</span>
        </button>
        <button
          v-if="hasActiveFilters"
          type="button"
          class="ml-1 text-[11px] font-medium text-brand hover:underline"
          @click="clearFilters"
        >
          Xóa tất cả lọc
        </button>
        <span
          v-if="canContribute"
          class="ml-auto hidden text-[11px] text-slate-400 sm:inline"
        >
          Kéo thả để đổi ngày · Bấm ô trống để tạo task
        </span>
      </div>
    </div>

    <!-- Body: calendar + unscheduled rail -->
    <div class="flex min-h-0 flex-1 flex-col lg:flex-row">
      <div
        ref="bodyRef"
        class="relative min-h-0 min-w-0 flex-1"
      >
        <FullCalendar
          v-if="hasScheduledData && hasVisibleEvents"
          ref="calendarRef"
          class="pc-calendar h-full"
          :options="calendarOptions"
        />

        <div
          v-if="!hasScheduledData"
          class="flex h-full items-center justify-center p-6"
        >
          <EmptyState
            icon="calendar"
            title="Chưa có công việc trên lịch"
            description="Thêm công việc có ngày bắt đầu hoặc hạn hoàn thành để hiển thị trên lịch dự án."
            :action="canManage ? 'Tạo công việc đầu tiên' : null"
            @action="openCreate"
          />
        </div>

        <div
          v-else-if="filters.kpi === 'unscheduled'"
          class="flex h-full items-center justify-center p-6"
        >
          <EmptyState
            icon="calendar-clock"
            title="Đang xem công việc chưa lên lịch"
            :description="unscheduledTasks.length
              ? 'Danh sách ở cột bên phải (máy tính) hoặc bên dưới. Mở task để gán ngày.'
              : 'Không còn công việc chưa lên lịch khớp bộ lọc hiện tại.'"
            :action="!sidebarOpen ? 'Mở cột chưa lịch' : null"
            @action="sidebarOpen = true"
          />
        </div>

        <div
          v-else-if="hasActiveFilters && !hasVisibleEvents"
          class="flex h-full items-center justify-center p-6"
        >
          <EmptyState
            icon="search"
            title="Không có kết quả phù hợp"
            description="Thử đổi từ khóa hoặc bỏ bớt bộ lọc để xem lại lịch."
            action="Xóa lọc"
            @action="clearFilters"
          />
        </div>
      </div>

      <!-- Unscheduled sidebar -->
      <aside
        v-if="sidebarOpen"
        class="flex max-h-[42%] w-full shrink-0 flex-col border-t border-slate-200 bg-white lg:max-h-none lg:w-80 lg:border-l lg:border-t-0"
        aria-label="Công việc chưa lên lịch"
      >
        <div class="flex items-center gap-2 border-b border-slate-100 px-3 py-2.5">
          <span class="grid h-7 w-7 place-items-center rounded-lg bg-amber-50 text-amber-700">
            <AppIcon
              name="calendar-clock"
              :size="14"
            />
          </span>
          <div class="min-w-0 flex-1">
            <p class="text-xs font-semibold text-slate-800">
              Chưa lên lịch
            </p>
            <p class="text-[10px] text-slate-400">
              {{ unscheduledTasks.length }} công việc
            </p>
          </div>
          <button
            type="button"
            class="grid h-7 w-7 place-items-center rounded-md text-slate-400 hover:bg-slate-100 hover:text-slate-700"
            aria-label="Đóng cột chưa lên lịch"
            @click="sidebarOpen = false"
          >
            <AppIcon
              name="close"
              :size="14"
            />
          </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto p-2">
          <p
            v-if="!unscheduledTasks.length"
            class="px-2 py-8 text-center text-xs text-slate-400"
          >
            {{ hasActiveFilters ? 'Không còn task chưa lịch khớp bộ lọc.' : 'Mọi công việc đã có ngày trên lịch.' }}
          </p>
          <button
            v-for="item in unscheduledTasks"
            :key="item.id"
            type="button"
            class="group mb-1.5 flex w-full items-start gap-2 rounded-xl border border-slate-100 bg-slate-50/80 px-2.5 py-2 text-left transition hover:border-brand/25 hover:bg-brand-50/40"
            @click="emit('select-task', item.id)"
          >
            <span
              class="mt-1.5 h-2 w-2 shrink-0 rounded-full"
              :style="{ backgroundColor: item.accent }"
            />
            <span class="min-w-0 flex-1">
              <span class="block truncate text-xs font-semibold text-slate-800 group-hover:text-brand">
                {{ item.title }}
              </span>
              <span class="mt-0.5 flex flex-wrap items-center gap-x-2 gap-y-0.5 text-[10px] text-slate-500">
                <span v-if="item.statusLabel">{{ item.statusLabel }}</span>
                <span v-if="item.sprintName">{{ item.sprintName }}</span>
              </span>
              <span
                v-if="item.assignees.length"
                class="mt-1.5 flex items-center gap-1"
              >
                <Avatar
                  v-for="a in item.assignees.slice(0, 3)"
                  :key="a.id"
                  :name="a.name"
                  :src="a.avatar"
                  :size="18"
                />
              </span>
            </span>
          </button>
        </div>

        <p
          v-if="canContribute"
          class="border-t border-slate-100 px-3 py-2 text-[10px] leading-relaxed text-slate-400"
        >
          Mở task để gán ngày bắt đầu / hạn — hoặc tạo mới bằng cách bấm vào ô trên lịch.
        </p>
      </aside>
    </div>

    <!-- Hover card -->
    <Teleport to="body">
      <div
        v-if="hover.show && hover.props"
        class="pointer-events-none fixed z-[220] w-72 max-w-[calc(100vw-1rem)] overflow-hidden rounded-xl border border-slate-200/90 bg-white shadow-elevation-3"
        :class="hover.above ? '-translate-y-full' : ''"
        :style="{ left: `${hover.x}px`, top: `${hover.y}px` }"
        role="tooltip"
      >
        <div
          class="h-1 w-full"
          :style="{ backgroundColor: hover.props.accent }"
        />
        <div class="px-3 py-2.5">
          <div class="flex items-start gap-2">
            <span
              v-if="hover.props.milestone"
              class="mt-0.5 text-violet-600"
            >◆</span>
            <p class="min-w-0 flex-1 font-display text-sm font-semibold leading-snug text-slate-800">
              {{ hover.title }}
            </p>
          </div>
          <div class="mt-2 flex flex-wrap gap-1.5">
            <span
              v-if="hover.props.statusLabel"
              class="rounded-md px-1.5 py-0.5 text-[10px] font-semibold"
              :style="{
                backgroundColor: `${hover.props.accent}18`,
                color: hover.props.accent,
              }"
            >
              {{ hover.props.statusLabel }}
            </span>
            <span
              v-if="hover.props.priorityLabel"
              class="rounded-md bg-slate-100 px-1.5 py-0.5 text-[10px] font-medium text-slate-600"
            >
              {{ hover.props.priorityLabel }}
            </span>
            <span
              v-if="hover.props.overdue"
              class="rounded-md bg-rose-50 px-1.5 py-0.5 text-[10px] font-semibold text-rose-700"
            >
              Quá hạn
            </span>
            <span
              v-if="hover.props.milestone"
              class="rounded-md bg-violet-50 px-1.5 py-0.5 text-[10px] font-semibold text-violet-700"
            >
              Mốc dự án
            </span>
          </div>
          <dl class="mt-2.5 space-y-1 text-[11px] text-slate-500">
            <div class="flex gap-2">
              <dt class="w-16 shrink-0 text-slate-400">
                Thời gian
              </dt>
              <dd class="font-medium text-slate-700">
                {{ formatRange(hover.props.startDate, hover.props.dueDate) }}
              </dd>
            </div>
            <div
              v-if="hover.props.sprintName"
              class="flex gap-2"
            >
              <dt class="w-16 shrink-0 text-slate-400">
                Sprint
              </dt>
              <dd class="font-medium text-slate-700">
                {{ hover.props.sprintName }}
              </dd>
            </div>
            <div
              v-if="!hover.props.milestone && hover.props.progress > 0"
              class="flex gap-2"
            >
              <dt class="w-16 shrink-0 text-slate-400">
                Tiến độ
              </dt>
              <dd class="font-semibold text-brand">
                {{ hover.props.progress }}%
              </dd>
            </div>
          </dl>
          <div
            v-if="hover.props.assignees?.length"
            class="mt-2.5 flex items-center gap-1.5 border-t border-slate-100 pt-2"
          >
            <Avatar
              v-for="a in hover.props.assignees.slice(0, 4)"
              :key="a.id"
              :name="a.name"
              :src="a.avatar"
              :size="22"
            />
            <span class="ml-1 truncate text-[11px] text-slate-500">
              {{ hover.props.assignees.map((a) => a.name).join(', ') }}
            </span>
          </div>
          <p
            v-else
            class="mt-2.5 border-t border-slate-100 pt-2 text-[11px] text-slate-400"
          >
            Chưa gán phụ trách
          </p>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<style>
.pc-shell {
    --pc-brand: #9a0036;
    --pc-brand-soft: #fdf2f6;
    --pc-line: #e8eef5;
}

.pc-calendar .fc {
    --fc-border-color: var(--pc-line);
    --fc-today-bg-color: var(--pc-brand-soft);
    --pc-page-bg-color: #ffffff;
    --fc-page-bg-color: #ffffff;
    --fc-neutral-bg-color: #f8fafc;
    --fc-now-indicator-color: var(--pc-brand);
    --fc-event-border-color: transparent;
    font-size: 13px;
}

.pc-calendar .fc .fc-scrollgrid {
    border-radius: 0;
    border-color: var(--pc-line);
}

.pc-calendar .fc .fc-col-header-cell {
    background: linear-gradient(180deg, #fafbfc 0%, #ffffff 100%);
}

.pc-calendar .fc .fc-col-header-cell-cushion {
    padding: 10px 6px;
    font-size: 11px;
    font-weight: 650;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: #94a3b8;
}

.pc-calendar .fc .fc-daygrid-day.fc-day-other {
    background-color: #fbfcfe;
}

.pc-calendar .fc .fc-daygrid-day.fc-day-sat,
.pc-calendar .fc .fc-daygrid-day.fc-day-sun {
    background-color: #fafafa;
}

.pc-calendar .fc .fc-daygrid-day.fc-day-today {
    background-color: var(--pc-brand-soft);
}

.pc-calendar .fc .fc-daygrid-day-number {
    font-size: 12px;
    font-weight: 560;
    color: #475569;
    padding: 6px 8px;
    border-radius: 9999px;
    margin: 4px;
    transition: background 0.15s ease, color 0.15s ease;
}

.pc-calendar .fc .fc-daygrid-day:hover .fc-daygrid-day-number {
    background: #f1f5f9;
}

.pc-calendar .fc .fc-day-today .fc-daygrid-day-number {
    background: var(--pc-brand);
    color: #fff;
    font-weight: 700;
    box-shadow: 0 2px 6px rgba(154, 0, 54, 0.28);
}

.pc-calendar .fc .fc-day-sat .fc-daygrid-day-number,
.pc-calendar .fc .fc-day-sun .fc-daygrid-day-number {
    color: #a1a9b8;
}

.pc-calendar .fc .fc-day-today.fc-day-sat .fc-daygrid-day-number,
.pc-calendar .fc .fc-day-today.fc-day-sun .fc-daygrid-day-number {
    color: #fff;
}

/* Event chips */
.pc-calendar .fc-daygrid-event,
.pc-calendar .fc-timegrid-event {
    border-radius: 7px;
    border-width: 1px;
    border-left-width: 3px;
    border-style: solid;
    padding: 0;
    margin: 1.5px 3px;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.05);
    transition: box-shadow 0.15s ease, transform 0.15s ease, border-color 0.15s ease;
    white-space: normal;
    cursor: pointer;
}

.pc-calendar .fc-daygrid-event:hover,
.pc-calendar .fc-timegrid-event:hover {
    box-shadow: 0 6px 16px -4px rgba(15, 23, 42, 0.18);
    transform: translateY(-1px);
    z-index: 3;
}

.pc-calendar .fc-daygrid-event .fc-event-main,
.pc-calendar .fc-timegrid-event .fc-event-main {
    padding: 3px 6px;
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
    box-shadow: 0 0 0 1px rgba(255, 255, 255, 0.75);
}

.pc-ev-diamond {
    flex-shrink: 0;
    font-size: 10px;
    line-height: 1;
    color: var(--pc-brand);
}

.pc-ev-title {
    flex: 1 1 auto;
    min-width: 0;
    font-weight: 650;
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

.pc-ev-avatars {
    display: flex;
    align-items: center;
    flex-shrink: 0;
    margin-left: 2px;
}

.pc-ev-avatar {
    display: inline-grid;
    place-items: center;
    width: 16px;
    height: 16px;
    margin-left: -4px;
    border-radius: 9999px;
    border: 1.5px solid #fff;
    background: #e2e8f0;
    color: #475569;
    font-size: 8px;
    font-weight: 700;
    overflow: hidden;
}

.pc-ev-avatar:first-child {
    margin-left: 0;
}

.pc-ev-avatar__img {
    width: 100%;
    height: 100%;
    object-fit: cover;
}

.pc-ev-avatar--more {
    background: #f1f5f9;
    color: #64748b;
    font-size: 7px;
}

.pc-ev--milestone {
    background-image: linear-gradient(0deg, rgba(154, 0, 54, 0.04), rgba(154, 0, 54, 0.04));
}

.pc-ev--milestone .pc-ev-title {
    color: var(--pc-brand);
}

.pc-ev--overdue {
    box-shadow: 0 0 0 1px #fda4af, 0 1px 2px rgba(225, 29, 72, 0.16);
}

.pc-ev--done .pc-ev-title {
    text-decoration: line-through;
    opacity: 0.68;
}

/* Time grid */
.pc-calendar .fc .fc-timegrid-slot {
    height: 2.4em;
}

.pc-calendar .fc .fc-timegrid-axis-cushion,
.pc-calendar .fc .fc-timegrid-slot-label-cushion {
    font-size: 11px;
    color: #94a3b8;
}

.pc-calendar .fc .fc-timegrid-now-indicator-line {
    border-color: var(--pc-brand);
    border-width: 2px;
}

.pc-calendar .fc .fc-timegrid-now-indicator-arrow {
    border-top-color: var(--pc-brand);
    border-bottom-color: var(--pc-brand);
}

/* List view */
.pc-calendar .fc .fc-list-event:hover td {
    background-color: var(--pc-brand-soft);
}

.pc-calendar .fc .fc-list-event-dot {
    border-color: currentColor;
}

.pc-calendar .fc .fc-list-day-cushion {
    background: linear-gradient(180deg, #f8fafc 0%, #ffffff 100%);
    font-weight: 650;
}

.pc-calendar .fc .fc-list-event .pc-ev-body {
    white-space: nowrap;
}

.pc-calendar .fc-theme-standard .fc-list,
.pc-calendar .fc-theme-standard td,
.pc-calendar .fc-theme-standard th {
    border-color: var(--pc-line);
}

.pc-calendar .fc .fc-more-link {
    font-size: 11px;
    font-weight: 650;
    color: var(--pc-brand);
    padding: 2px 6px;
    border-radius: 6px;
}

.pc-calendar .fc .fc-more-link:hover {
    background: var(--pc-brand-soft);
}

.pc-calendar .fc .fc-highlight {
    background: rgba(154, 0, 54, 0.08);
}

@media (prefers-reduced-motion: reduce) {
    .pc-calendar .fc-daygrid-event,
    .pc-calendar .fc-timegrid-event {
        transition: none;
    }
}
</style>
