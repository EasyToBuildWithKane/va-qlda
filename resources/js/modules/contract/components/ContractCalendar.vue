<script setup>
import { reactive, ref, watch, onMounted, onBeforeUnmount } from 'vue';
import { router } from '@inertiajs/vue3';
import FullCalendar from '@fullcalendar/vue3';
import dayGridPlugin from '@fullcalendar/daygrid';
import listPlugin from '@fullcalendar/list';
import viLocale from '@fullcalendar/core/locales/vi';
import AppIcon from '@/Components/AppIcon.vue';
import { useContractCalendar } from '../composables/useContractCalendar.js';

const props = defineProps({
    contracts: { type: Array, default: () => [] },
});

const { events, legend } = useContractCalendar(() => props.contracts);

const calendarRef = ref(null);
const bodyRef = ref(null);
const api = () => calendarRef.value?.getApi();
const viewTitle = ref('');
const currentView = ref('dayGridMonth');

const VIEWS = [
    { key: 'dayGridMonth', label: 'Tháng' },
    { key: 'listMonth', label: 'Danh sách' },
];

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

const calendarOptions = reactive({
    plugins: [dayGridPlugin, listPlugin],
    initialView: 'dayGridMonth',
    locale: viLocale,
    firstDay: 1,
    headerToolbar: false,
    height: '100%',
    expandRows: true,
    dayMaxEvents: 4,
    fixedWeekCount: false,
    displayEventTime: false,
    noEventsContent: 'Không có hợp đồng hết hạn trong khoảng này',
    events: (info, success) => success(events.value),
    datesSet: (arg) => {
        viewTitle.value = arg.view.title;
        currentView.value = arg.view.type;
    },
    eventClick: (arg) => router.visit(`/contracts/${arg.event.extendedProps.contractId}`),
});

watch(events, () => api()?.refetchEvents());

const goToday = () => api()?.today();
const goPrev = () => api()?.prev();
const goNext = () => api()?.next();
const changeView = (key) => api()?.changeView(key);
</script>

<template>
  <div class="flex h-[34rem] flex-col overflow-hidden rounded-card border border-slate-200 bg-white">
    <div class="flex flex-wrap items-center gap-3 border-b border-slate-200 px-4 py-2.5">
      <button
        type="button"
        class="h-8 rounded-md border border-slate-200 px-3 text-sm font-medium text-slate-600 hover:bg-slate-50"
        @click="goToday"
      >
        Hôm nay
      </button>
      <div class="flex items-center">
        <button
          type="button"
          class="grid h-8 w-8 place-items-center rounded-md text-slate-500 hover:bg-slate-100"
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

      <div class="ml-auto flex flex-wrap items-center gap-3">
        <span
          v-for="l in legend"
          :key="l.level"
          class="inline-flex items-center gap-1.5 text-[11px] font-medium text-slate-600"
        >
          <span
            class="h-2.5 w-2.5 rounded-sm"
            :style="{ backgroundColor: l.color }"
          />
          {{ l.label }}
        </span>
        <div class="inline-flex rounded-md border border-slate-200 bg-slate-50 p-0.5">
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
      </div>
    </div>

    <div
      ref="bodyRef"
      class="relative min-h-0 flex-1"
    >
      <FullCalendar
        ref="calendarRef"
        class="cc-calendar h-full"
        :options="calendarOptions"
      />
    </div>
  </div>
</template>

<style>
.cc-calendar .fc {
    --fc-border-color: #eef2f7;
    --fc-today-bg-color: #fdf2f6;
    font-size: 13px;
}
.cc-calendar .fc .fc-daygrid-event {
    border-radius: 6px;
    border-width: 1px;
    border-style: solid;
    padding: 1px 4px;
    font-weight: 600;
    cursor: pointer;
}
.cc-calendar .fc .fc-day-today .fc-daygrid-day-number {
    color: #9a0036;
    font-weight: 700;
}
</style>
