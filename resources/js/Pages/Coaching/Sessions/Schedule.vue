<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import timeGridPlugin from '@fullcalendar/timegrid';
import listPlugin from '@fullcalendar/list';
import interactionPlugin from '@fullcalendar/interaction';
import viLocale from '@fullcalendar/core/locales/vi';

import AppLayout from '@/Layouts/AppLayout.vue';
import PageHeader from '@/Components/Ui/PageHeader.vue';
import AppIcon from '@/Components/AppIcon.vue';
import { useToast } from '@/shared/composables/useToast';

import CalendarSidebar from '@/modules/coaching/components/CalendarSidebar.vue';
import SessionDrawer from '@/modules/coaching/components/SessionDrawer.vue';
import QuickSessionModal from '@/modules/coaching/components/QuickSessionModal.vue';
import { useCoachingCalendar, statusMeta } from '@/modules/coaching/composables/useCoachingCalendar.js';

const props = defineProps({
    stats: { type: Object, default: () => ({ today: 0, week: 0, coaches: 0 }) },
    coaches: { type: Array, default: () => [] },
    courses: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    can: { type: Object, default: () => ({ manage: false }) },
});

const toast = useToast();
const canManage = computed(() => !!props.can.manage);
const manageableCourses = computed(() => props.courses.filter((c) => c.can?.manage));

const calendar = useCoachingCalendar({
    feedUrl: route('coaching.sessions.calendar.feed'),
    storeUrl: route('coaching.sessions.calendar.store'),
    updateUrlFor: (id) => route('coaching.sessions.calendar.update', { session: id }),
});

/* ── FullCalendar wiring ─────────────────────────────────────────── */
const calendarRef = ref(null);
const api = () => calendarRef.value?.getApi();

const viewTitle = ref('');
const currentView = ref('timeGridWeek');
const selectedDate = ref(new Date().toISOString().slice(0, 10));
const renderedCount = ref(0);
const busy = ref(false);

const VIEWS = [
    { key: 'timeGridDay', label: 'Ngày', short: 'd' },
    { key: 'timeGridWeek', label: 'Tuần', short: 'w' },
    { key: 'dayGridMonth', label: 'Tháng', short: 'm' },
    { key: 'listWeek', label: 'Lịch biểu', short: 'a' },
];

const pad = (n) => String(n).padStart(2, '0');
const toYmd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const toHm = (d) => `${pad(d.getHours())}:${pad(d.getMinutes())}`;

const calendarOptions = reactive({
    plugins: [dayGridPlugin, timeGridPlugin, listPlugin, interactionPlugin],
    initialView: 'timeGridWeek',
    locale: viLocale,
    firstDay: 1,
    headerToolbar: false,
    height: '100%',
    expandRows: true,
    nowIndicator: true,
    slotMinTime: '06:00:00',
    slotMaxTime: '22:00:00',
    slotDuration: '00:30:00',
    dayMaxEvents: 3,
    weekends: true,
    editable: canManage.value,
    eventStartEditable: canManage.value,
    eventDurationEditable: canManage.value,
    selectable: canManage.value,
    selectMirror: true,
    lazyFetching: true,
    eventTimeFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    slotLabelFormat: { hour: '2-digit', minute: '2-digit', hour12: false },
    events: calendar.source,
    datesSet: handleDatesSet,
    eventsSet: (events) => {
        renderedCount.value = events.length;
    },
    eventContent: renderEvent,
    eventDidMount: decorateEventEl,
    eventClick: handleEventClick,
    select: handleSelect,
    eventDrop: handleMove,
    eventResize: handleMove,
});

function handleDatesSet(arg) {
    viewTitle.value = arg.view.title;
    currentView.value = arg.view.type;
}

function renderEvent(arg) {
    const p = arg.event.extendedProps;
    const wrap = document.createElement('div');
    wrap.className = 'cc-ev';

    const title = document.createElement('div');
    title.className = 'cc-ev-title';
    title.textContent = arg.event.title;
    wrap.appendChild(title);

    const bits = [];
    if (arg.timeText) bits.push(arg.timeText);
    if (p.studentName) bits.push(p.studentName);
    if (bits.length) {
        const sub = document.createElement('div');
        sub.className = 'cc-ev-sub';
        sub.textContent = bits.join(' · ');
        wrap.appendChild(sub);
    }
    return { domNodes: [wrap] };
}

function decorateEventEl(arg) {
    const p = arg.event.extendedProps;
    const tip = [
        arg.event.title,
        p.studentName ? `Học viên: ${p.studentName}` : null,
        p.coachName ? `Coach: ${p.coachName}` : null,
        p.statusLabel,
    ]
        .filter(Boolean)
        .join('\n');
    arg.el.setAttribute('title', tip);
    arg.el.addEventListener('dblclick', () => {
        if (p.detailUrl) window.location.href = p.detailUrl;
    });
}

/* ── Drawer ──────────────────────────────────────────────────────── */
const drawerOpen = ref(false);
const activeSession = ref(null);

function eventToSession(event) {
    const p = event.extendedProps;
    return {
        id: p.sessionId,
        title: event.title,
        status: p.status,
        statusLabel: p.statusLabel,
        sessionNumber: p.sessionNumber,
        courseId: p.courseId,
        courseCode: p.courseCode,
        courseName: p.courseName,
        coachName: p.coachName,
        studentName: p.studentName,
        topic: p.topic,
        notes: p.notes,
        durationHours: p.durationHours,
        startTime: p.startTime,
        endTime: p.endTime,
        date: event.startStr ? event.startStr.slice(0, 10) : null,
        detailUrl: p.detailUrl,
        canManage: p.canManage,
    };
}

function handleEventClick(arg) {
    activeSession.value = eventToSession(arg.event);
    drawerOpen.value = true;
}

/* ── Quick create / edit modal ───────────────────────────────────── */
const modalOpen = ref(false);
const modalMode = ref('create');
const modalPrefill = ref({});
const modalSession = ref(null);

function openCreate(prefill = {}) {
    if (!canManage.value) return;
    modalMode.value = 'create';
    modalPrefill.value = prefill;
    modalSession.value = null;
    modalOpen.value = true;
}

function openEdit(session) {
    modalMode.value = 'edit';
    modalSession.value = session;
    modalOpen.value = true;
}

function handleSelect(arg) {
    if (!canManage.value) return;
    const prefill = { date: toYmd(arg.start) };
    if (!arg.allDay) {
        prefill.start_time = toHm(arg.start);
        prefill.end_time = toHm(arg.end);
    }
    openCreate(prefill);
    api()?.unselect();
}

async function handleModalSubmit(evt) {
    busy.value = true;
    try {
        if (evt.mode === 'create') {
            await calendar.createSession(evt.payload);
        } else {
            const updated = await calendar.updateSession(evt.id, evt.payload);
            if (updated && activeSession.value?.id === evt.id) {
                activeSession.value = eventToSession({
                    title: updated.title,
                    startStr: updated.start,
                    extendedProps: updated.extendedProps,
                });
            }
        }
        modalOpen.value = false;
        api()?.refetchEvents();
    } catch {
        /* toast handled in composable */
    } finally {
        busy.value = false;
    }
}

/* ── Drawer actions ──────────────────────────────────────────────── */
async function handleStatus(status) {
    if (!activeSession.value) return;
    busy.value = true;
    try {
        const updated = await calendar.updateSession(activeSession.value.id, { status });
        if (updated) {
            activeSession.value = {
                ...activeSession.value,
                status: updated.extendedProps.status,
                statusLabel: updated.extendedProps.statusLabel,
            };
        }
        api()?.refetchEvents();
    } catch {
        /* handled */
    } finally {
        busy.value = false;
    }
}

/* ── Drag / resize persistence ───────────────────────────────────── */
async function handleMove(arg) {
    const e = arg.event;
    const payload = { date: toYmd(e.start) };
    if (!e.allDay) {
        payload.start_time = toHm(e.start);
        payload.end_time = e.end ? toHm(e.end) : null;
    }
    busy.value = true;
    try {
        await calendar.reschedule(e.id, payload);
        toast.success('Đã đổi lịch buổi học.');
    } catch {
        arg.revert();
    } finally {
        busy.value = false;
    }
}

/* ── Toolbar actions ─────────────────────────────────────────────── */
function changeView(key) {
    api()?.changeView(key);
}
function goToday() {
    api()?.today();
    selectedDate.value = new Date().toISOString().slice(0, 10);
}
function goPrev() {
    api()?.prev();
}
function goNext() {
    api()?.next();
}
function pickDate(ymd) {
    selectedDate.value = ymd;
    api()?.gotoDate(ymd);
}

let searchTimer = null;
function onSearch(value) {
    calendar.filters.query = value;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => api()?.refetchEvents(), 250);
}

function toggleCoach(name) {
    calendar.toggleSetValue(calendar.filters.coaches, name);
    api()?.refetchEvents();
}
function toggleStatus(value) {
    calendar.toggleSetValue(calendar.filters.statuses, value);
    api()?.refetchEvents();
}
function clearFilters() {
    calendar.filters.coaches.clear();
    calendar.filters.statuses.clear();
    calendar.filters.query = '';
    api()?.refetchEvents();
}

/* ── ICS export of the visible range ─────────────────────────────── */
function icsTime(d) {
    return `${d.getFullYear()}${pad(d.getMonth() + 1)}${pad(d.getDate())}T${pad(d.getHours())}${pad(d.getMinutes())}00`;
}
function icsEsc(s) {
    return String(s || '').replace(/([,;\\])/g, '\\$1').replace(/\n/g, '\\n');
}
function exportCalendar() {
    const events = api()?.getEvents() || [];
    if (!events.length) {
        toast.info('Không có buổi học nào trong khoảng đang xem để xuất.');
        return;
    }
    const lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//VAschools//Coaching//VI', 'CALSCALE:GREGORIAN'];
    for (const e of events) {
        const p = e.extendedProps;
        const start = e.start;
        const end = e.end || e.start;
        lines.push(
            'BEGIN:VEVENT',
            `UID:coaching-session-${e.id}@vaschools`,
            `DTSTART:${icsTime(start)}`,
            `DTEND:${icsTime(end)}`,
            `SUMMARY:${icsEsc(e.title)}`,
            `DESCRIPTION:${icsEsc(`Học viên: ${p.studentName || '—'} | Coach: ${p.coachName || '—'} | ${p.statusLabel || ''}`)}`,
            'END:VEVENT',
        );
    }
    lines.push('END:VCALENDAR');
    const blob = new Blob([lines.join('\r\n')], { type: 'text/calendar;charset=utf-8' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = 'lich-coaching.ics';
    a.click();
    URL.revokeObjectURL(url);
}

/* ── Keyboard shortcuts ──────────────────────────────────────────── */
function onKeydown(e) {
    const tag = e.target?.tagName;
    if (tag === 'INPUT' || tag === 'TEXTAREA' || tag === 'SELECT' || e.metaKey || e.ctrlKey) return;
    const k = e.key.toLowerCase();
    if (k === 't') goToday();
    else if (k === 'n') openCreate({ date: selectedDate.value });
    else {
        const view = VIEWS.find((v) => v.short === k);
        if (view) changeView(view.key);
    }
}

/* ── Mobile sidebar ──────────────────────────────────────────────── */
const sidebarOpen = ref(false);

onMounted(() => window.addEventListener('keydown', onKeydown));
onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown));

const showEmpty = computed(
    () => !calendar.loading.value && !calendar.error.value && renderedCount.value === 0,
);
</script>

<template>
  <Head title="Lịch Coaching" />
  <AppLayout>
    <template #header>
      <PageHeader
        title="Lịch Coaching"
        subtitle="Quản lý lịch các buổi coaching theo ngày / tuần / tháng"
        icon="calendar"
        back-href="/coaching"
      >
        <button
          type="button"
          class="btn-ghost h-9 gap-1.5 px-3 text-sm"
          @click="exportCalendar"
        >
          <AppIcon
            name="download"
            :size="15"
          />
          <span class="hidden sm:inline">Xuất lịch</span>
        </button>
        <button
          v-if="canManage"
          type="button"
          class="btn-primary h-9 gap-1.5 px-3 text-sm"
          @click="openCreate({ date: selectedDate })"
        >
          <AppIcon
            name="plus"
            :size="16"
          />
          Thêm buổi
        </button>
      </PageHeader>
    </template>

    <!-- Stat chips -->
    <div class="mb-4 grid grid-cols-3 gap-3">
      <div class="card flex items-center gap-3 px-4 py-3">
        <span class="grid h-9 w-9 place-items-center rounded-lg bg-brand/10 text-brand">
          <AppIcon
            name="report-today"
            :size="18"
          />
        </span>
        <div>
          <p class="text-lg font-semibold leading-none text-slate-800">
            {{ stats.today }}
          </p>
          <p class="mt-0.5 text-xs text-slate-500">
            Buổi hôm nay
          </p>
        </div>
      </div>
      <div class="card flex items-center gap-3 px-4 py-3">
        <span class="grid h-9 w-9 place-items-center rounded-lg bg-sky-50 text-sky-600">
          <AppIcon
            name="calendar"
            :size="18"
          />
        </span>
        <div>
          <p class="text-lg font-semibold leading-none text-slate-800">
            {{ stats.week }}
          </p>
          <p class="mt-0.5 text-xs text-slate-500">
            Buổi trong tuần
          </p>
        </div>
      </div>
      <div class="card flex items-center gap-3 px-4 py-3">
        <span class="grid h-9 w-9 place-items-center rounded-lg bg-violet-50 text-violet-600">
          <AppIcon
            name="people"
            :size="18"
          />
        </span>
        <div>
          <p class="text-lg font-semibold leading-none text-slate-800">
            {{ stats.coaches }}
          </p>
          <p class="mt-0.5 text-xs text-slate-500">
            Coach đang hoạt động
          </p>
        </div>
      </div>
    </div>

    <div class="flex gap-4">
      <!-- Sidebar (desktop) -->
      <aside class="hidden w-72 shrink-0 lg:block">
        <div class="card sticky top-4 h-[calc(100vh-9rem)]">
          <CalendarSidebar
            :coaches="coaches"
            :statuses="statuses"
            :filters="calendar.filters"
            :selected-date="selectedDate"
            @select-date="pickDate"
            @toggle-coach="toggleCoach"
            @toggle-status="toggleStatus"
            @clear="clearFilters"
          />
        </div>
      </aside>

      <!-- Calendar -->
      <div class="min-w-0 flex-1">
        <div class="card overflow-hidden">
          <!-- Toolbar -->
          <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 bg-slate-50/70 px-4 py-3">
            <div class="flex items-center gap-2">
              <button
                type="button"
                class="grid h-8 w-8 place-items-center rounded-btn border border-slate-200 bg-white text-slate-500 hover:border-slate-300 lg:hidden"
                aria-label="Bộ lọc"
                @click="sidebarOpen = true"
              >
                <AppIcon
                  name="filter"
                  :size="16"
                />
              </button>
              <button
                type="button"
                class="h-8 rounded-btn border border-slate-200 bg-white px-3 text-sm font-medium text-slate-600 hover:border-slate-300"
                @click="goToday"
              >
                Hôm nay
              </button>
              <div class="flex items-center">
                <button
                  type="button"
                  class="grid h-8 w-8 place-items-center rounded-btn text-slate-500 hover:bg-slate-100"
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
                  class="grid h-8 w-8 place-items-center rounded-btn text-slate-500 hover:bg-slate-100"
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

            <div class="flex items-center gap-2">
              <!-- Search -->
              <div class="relative hidden sm:block">
                <span class="pointer-events-none absolute left-2.5 top-1/2 -translate-y-1/2 text-slate-400">
                  <AppIcon
                    name="search"
                    :size="15"
                  />
                </span>
                <input
                  :value="calendar.filters.query"
                  type="search"
                  placeholder="Tìm học viên, coach, khóa…"
                  class="input h-8 w-56 pl-8 text-sm"
                  @input="onSearch($event.target.value)"
                >
              </div>

              <!-- View switcher -->
              <div class="flex items-center rounded-btn border border-slate-200 bg-white p-0.5">
                <button
                  v-for="v in VIEWS"
                  :key="v.key"
                  type="button"
                  class="rounded-md px-2.5 py-1 text-xs font-medium transition"
                  :class="
                    currentView === v.key
                      ? 'bg-brand text-white shadow-sm'
                      : 'text-slate-500 hover:text-slate-700'
                  "
                  @click="changeView(v.key)"
                >
                  {{ v.label }}
                </button>
              </div>
            </div>
          </div>

          <!-- Calendar body -->
          <div class="cc-calendar relative h-[calc(100vh-15rem)]">
            <FullCalendar
              ref="calendarRef"
              :options="calendarOptions"
            />

            <!-- Loading overlay -->
            <div
              v-if="calendar.loading.value"
              class="absolute inset-0 z-10 grid place-items-center bg-white/60 backdrop-blur-[1px]"
            >
              <div class="flex items-center gap-2 rounded-full bg-white px-4 py-2 text-sm text-slate-500 shadow-elevation-2">
                <span class="h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-brand" />
                Đang tải lịch…
              </div>
            </div>

            <!-- Empty overlay -->
            <div
              v-else-if="showEmpty"
              class="pointer-events-none absolute inset-0 z-[5] grid place-items-center"
            >
              <div class="pointer-events-auto max-w-xs text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-slate-100 text-slate-400">
                  <AppIcon
                    name="calendar"
                    :size="22"
                  />
                </span>
                <p class="mt-3 text-sm font-medium text-slate-600">
                  Không có buổi học nào
                </p>
                <p class="mt-1 text-xs text-slate-400">
                  {{
                    calendar.filters.coaches.size || calendar.filters.statuses.size || calendar.filters.query
                      ? 'Thử bỏ bớt bộ lọc.'
                      : 'Chọn một ô trống để tạo buổi học mới.'
                  }}
                </p>
                <button
                  v-if="canManage && !(calendar.filters.coaches.size || calendar.filters.statuses.size || calendar.filters.query)"
                  type="button"
                  class="btn-primary mt-3 h-8 gap-1.5 px-3 text-xs"
                  @click="openCreate({ date: selectedDate })"
                >
                  <AppIcon
                    name="plus"
                    :size="14"
                  />
                  Thêm buổi
                </button>
              </div>
            </div>

            <!-- Error overlay -->
            <div
              v-if="calendar.error.value"
              class="absolute inset-0 z-20 grid place-items-center bg-white/80"
            >
              <div class="max-w-xs text-center">
                <span class="mx-auto grid h-12 w-12 place-items-center rounded-full bg-rose-50 text-rose-500">
                  <AppIcon
                    name="alert"
                    :size="22"
                  />
                </span>
                <p class="mt-3 text-sm font-medium text-slate-700">
                  Không tải được lịch
                </p>
                <button
                  type="button"
                  class="btn-ghost mt-3 h-8 gap-1.5 px-3 text-xs"
                  @click="api()?.refetchEvents()"
                >
                  <AppIcon
                    name="refresh"
                    :size="14"
                  />
                  Thử lại
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Legend -->
        <div class="mt-3 flex flex-wrap items-center gap-3 px-1 text-xs text-slate-500">
          <span
            v-for="s in statuses"
            :key="s.value"
            class="inline-flex items-center gap-1.5"
          >
            <span
              class="h-2.5 w-2.5 rounded-full"
              :style="{ backgroundColor: statusMeta(s.value).color }"
            />
            {{ s.label }}
          </span>
          <span class="ml-auto hidden text-slate-400 md:inline">
            Phím tắt: T hôm nay · D/W/M/A đổi chế độ · N thêm buổi
          </span>
        </div>
      </div>
    </div>

    <!-- Mobile sidebar drawer -->
    <Teleport to="body">
      <Transition
        enter-active-class="transition duration-200 ease-out"
        enter-from-class="opacity-0"
        leave-active-class="transition duration-150 ease-in"
        leave-to-class="opacity-0"
      >
        <div
          v-if="sidebarOpen"
          class="fixed inset-0 z-50 bg-slate-900/40 lg:hidden"
          @click.self="sidebarOpen = false"
        >
          <div class="absolute left-0 top-0 h-full w-80 max-w-[85%] bg-white shadow-elevation-3">
            <div class="flex items-center justify-between border-b border-slate-100 px-4 py-3">
              <span class="font-display text-base font-semibold text-slate-800">Bộ lọc</span>
              <button
                type="button"
                class="grid h-8 w-8 place-items-center rounded-btn text-slate-400 hover:bg-slate-100"
                aria-label="Đóng"
                @click="sidebarOpen = false"
              >
                <AppIcon
                  name="close"
                  :size="18"
                />
              </button>
            </div>
            <CalendarSidebar
              class="h-[calc(100%-3.5rem)]"
              :coaches="coaches"
              :statuses="statuses"
              :filters="calendar.filters"
              :selected-date="selectedDate"
              @select-date="(d) => { pickDate(d); sidebarOpen = false; }"
              @toggle-coach="toggleCoach"
              @toggle-status="toggleStatus"
              @clear="clearFilters"
            />
          </div>
        </div>
      </Transition>
    </Teleport>

    <!-- Detail drawer -->
    <SessionDrawer
      :show="drawerOpen"
      :session="activeSession"
      :busy="busy"
      @close="drawerOpen = false"
      @edit="(s) => openEdit(s)"
      @reschedule="(s) => openEdit(s)"
      @status="handleStatus"
    />

    <!-- Quick create / edit -->
    <QuickSessionModal
      :show="modalOpen"
      :mode="modalMode"
      :courses="manageableCourses"
      :prefill="modalPrefill"
      :session="modalSession"
      :busy="busy"
      @close="modalOpen = false"
      @submit="handleModalSubmit"
    />
  </AppLayout>
</template>

<style>
.cc-calendar .fc {
    --fc-border-color: #e5e7eb;
    --fc-today-bg-color: #eff6ff;
    --fc-now-indicator-color: #ef4444;
    --fc-event-selected-overlay-color: rgba(37, 99, 235, 0.12);
    font-size: 13px;
}
.cc-calendar .fc .fc-col-header-cell-cushion {
    padding: 8px 4px;
    font-size: 12px;
    font-weight: 600;
    color: #64748b;
}
.cc-calendar .fc .fc-timegrid-slot-label-cushion,
.cc-calendar .fc .fc-timegrid-axis-cushion {
    font-size: 11px;
    color: #94a3b8;
}
.cc-calendar .fc .fc-daygrid-day.fc-day-today,
.cc-calendar .fc .fc-timegrid-col.fc-day-today {
    background-color: #eff6ff;
}
.cc-calendar .fc .fc-daygrid-day-number {
    font-size: 12px;
    color: #475569;
    padding: 4px 6px;
}
.cc-calendar .fc-event {
    border-radius: 8px;
    border-width: 1px;
    border-left-width: 3px;
    padding: 1px 4px;
    cursor: pointer;
    box-shadow: 0 1px 2px rgba(15, 23, 42, 0.06);
    transition: box-shadow 0.15s ease, transform 0.15s ease;
    overflow: hidden;
}
.cc-calendar .fc-event:hover {
    box-shadow: 0 4px 12px rgba(15, 23, 42, 0.14);
    transform: translateY(-1px);
}
.cc-calendar .fc-daygrid-event {
    white-space: nowrap;
}
.cc-ev {
    line-height: 1.25;
    overflow: hidden;
}
.cc-ev-title {
    font-weight: 600;
    font-size: 12px;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cc-ev-sub {
    font-size: 11px;
    opacity: 0.85;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.cc-calendar .fc .fc-more-link {
    font-size: 11px;
    font-weight: 600;
    color: #2563eb;
}
.cc-calendar .fc .fc-list-event:hover td {
    background-color: #f8fafc;
}
.cc-calendar .fc .fc-list-event-dot {
    border-color: currentColor;
}
</style>
