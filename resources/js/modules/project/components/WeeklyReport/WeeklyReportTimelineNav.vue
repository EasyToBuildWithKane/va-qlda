<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    sprint: { type: Object, default: null },
    weeks: { type: Array, default: () => [] },
    currentWeek: { type: Number, default: 1 },
    activeReportId: { type: [Number, null], default: null },
    pendingWeek: { type: [Number, null], default: null },
    /** Tab Tổng quan — ẩn ô Phạm vi Sprint (chỉ đọc, thường «Ngoài Sprint»). */
    hideSprintScope: { type: Boolean, default: false },
});

const emit = defineEmits(['select']);

const FILTER_CONTROL_CLASS = 'input h-10 w-full text-sm';

const sprintLabel = computed(() => props.sprint?.name || 'Ngoài Sprint');
const isOutsideSprint = computed(() => !props.sprint);

const selectedWeekNumber = computed(() => {
    if (props.pendingWeek != null) return props.pendingWeek;
    if (props.activeReportId) {
        const match = props.weeks.find((w) => w.report_id === props.activeReportId);
        if (match) return match.week_number;
    }
    return props.currentWeek;
});

const activeWeek = computed(() => props.weeks.find((w) => w.week_number === selectedWeekNumber.value) ?? null);

function fmt(d) {
    if (!d) return '';
    const [, m, day] = d.split('-');
    return `${day}/${m}`;
}

function isDone(status) {
    return status === 'submitted' || status === 'approved';
}

function weekOptionLabel(w) {
    const range = `${fmt(w.week_start)} – ${fmt(w.week_end)}`;
    if (isDone(w.status)) return `Tuần ${w.week_number} · ${range} · Đã gửi/duyệt`;
    if (w.report_id) return `Tuần ${w.week_number} · ${range} · Đã tạo`;
    return `Tuần ${w.week_number} · ${range} · Chưa tạo`;
}

function onWeekChange(event) {
    const num = Number(event.target.value);
    const week = props.weeks.find((w) => w.week_number === num);
    if (week) emit('select', week);
}
</script>

<template>
  <div class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
    <div
      class="grid min-w-0 flex-1 grid-cols-1 gap-3 lg:max-w-2xl"
      :class="hideSprintScope ? '' : 'sm:grid-cols-2'"
    >
      <label
        v-if="!hideSprintScope"
        class="min-w-0"
      >
        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
          Phạm vi Sprint
        </span>
        <div class="relative">
          <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">
            <AppIcon
              name="sprint"
              :size="15"
            />
          </span>
          <select
            :class="[FILTER_CONTROL_CLASS, 'pl-9 pr-3', isOutsideSprint ? 'text-amber-800 dark:text-amber-200' : '']"
            :aria-label="`Phạm vi: ${sprintLabel}`"
            disabled
          >
            <option>{{ sprintLabel }}</option>
          </select>
        </div>
        <p
          v-if="isOutsideSprint"
          class="mt-1 text-[11px] leading-snug text-slate-500"
        >
          Dự án chưa gán Sprint — báo cáo theo tuần lịch hiện tại.
        </p>
      </label>

      <label class="min-w-0">
        <span class="mb-1 block text-[10px] font-semibold uppercase tracking-[0.12em] text-slate-400">
          Tuần báo cáo
        </span>
        <div class="relative">
          <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-brand">
            <AppIcon
              name="weekly"
              :size="15"
            />
          </span>
          <select
            :class="[FILTER_CONTROL_CLASS, 'pl-9 pr-3']"
            :value="selectedWeekNumber"
            aria-label="Chọn tuần báo cáo"
            @change="onWeekChange"
          >
            <option
              v-for="w in weeks"
              :key="w.week_number"
              :value="w.week_number"
            >
              {{ weekOptionLabel(w) }}
            </option>
          </select>
        </div>
      </label>
    </div>

    <div
      v-if="activeWeek"
      class="flex flex-wrap items-center gap-2 text-[11px] text-slate-500 sm:pb-0.5"
    >
      <span
        v-if="activeWeek.week_number === currentWeek"
        class="inline-flex items-center gap-1 rounded-full bg-amber-100 px-2 py-0.5 font-semibold uppercase text-amber-800 dark:bg-amber-950 dark:text-amber-200"
      >
        Tuần hiện tại
      </span>
      <span
        v-if="isDone(activeWeek.status)"
        class="inline-flex items-center gap-1 text-emerald-600 dark:text-emerald-400"
      >
        <AppIcon
          name="check"
          :size="12"
        />
        Đã gửi hoặc duyệt
      </span>
      <span
        v-else-if="activeWeek.report_id"
        class="inline-flex items-center gap-1 text-sky-600 dark:text-sky-400"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-sky-500" />
        Đã có báo cáo
      </span>
      <span
        v-else
        class="inline-flex items-center gap-1 text-slate-400"
      >
        <span class="h-1.5 w-1.5 rounded-full bg-slate-300 dark:bg-slate-600" />
        Chưa tạo báo cáo
      </span>
    </div>
  </div>
</template>
