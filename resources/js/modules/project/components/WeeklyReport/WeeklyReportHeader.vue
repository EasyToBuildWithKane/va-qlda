<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import WeeklyReportApprovalBar from './WeeklyReportApprovalBar.vue';

const props = defineProps({
    report: { type: Object, required: true },
    canGenerate: { type: Boolean, default: false },
    regenerationAvailable: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['regenerate', 'export', 'submit', 'approve', 'reject']);

function fmt(d) {
    if (!d) return '';
    const [y, m, day] = d.split('-');
    return `${day}/${m}/${y}`;
}

const exportOpen = ref(false);
function pick(format) {
    exportOpen.value = false;
    emit('export', format);
}

const period = computed(() => `${fmt(props.report.week_start)} – ${fmt(props.report.week_end)}`);
const updated = computed(() => {
    const iso = props.report.updated_at;
    if (!iso) return null;
    return new Date(iso).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
});
</script>

<template>
  <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 dark:border-slate-700 lg:flex-row lg:items-start lg:justify-between">
    <div class="min-w-0 flex-1 space-y-2">
      <div class="flex flex-col gap-2 sm:flex-row sm:flex-wrap sm:items-center sm:gap-x-4 sm:gap-y-2">
        <h2 class="shrink-0 font-display text-lg font-semibold text-slate-900 dark:text-slate-50">
          Báo cáo tuần {{ report.week_number }}
        </h2>
        <WeeklyReportApprovalBar
          inline
          :report="report"
          :processing="processing"
          class="min-w-0 flex-1"
          @submit="emit('submit')"
          @approve="emit('approve')"
          @reject="emit('reject', $event)"
        />
      </div>
      <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
        <span v-if="report.sprint_name">{{ report.sprint_name }}</span>
        <span class="inline-flex items-center gap-1">
          <AppIcon
            name="calendar"
            :size="12"
          /> {{ period }}
        </span>
        <span v-if="updated">Cập nhật {{ updated }}</span>
        <span
          v-if="report.approved_by"
          class="text-emerald-600"
        >Duyệt bởi {{ report.approved_by }}</span>
      </div>
    </div>

    <div class="flex shrink-0 items-center gap-2">
      <div class="relative">
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
          @click="exportOpen = !exportOpen"
        >
          <AppIcon
            name="export"
            :size="15"
          /> Xuất
          <AppIcon
            name="chevron-down"
            :size="13"
          />
        </button>
        <div
          v-if="exportOpen"
          class="absolute right-0 z-20 mt-1 w-36 overflow-hidden rounded-lg border border-slate-200 bg-white py-1 shadow-lg dark:border-slate-700 dark:bg-slate-900"
        >
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
            @click="pick('pdf')"
          >
            <AppIcon
              name="pdf"
              :size="14"
            /> PDF
          </button>
          <button
            type="button"
            class="flex w-full items-center gap-2 px-3 py-1.5 text-sm text-slate-600 hover:bg-slate-50 dark:text-slate-300 dark:hover:bg-slate-800"
            @click="pick('docx')"
          >
            <AppIcon
              name="documents"
              :size="14"
            /> Word (DOCX)
          </button>
        </div>
      </div>
      <button
        v-if="canGenerate && !report.is_locked"
        type="button"
        :disabled="processing"
        class="inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm font-medium transition disabled:opacity-60"
        :class="regenerationAvailable
          ? 'bg-amber-500 text-white hover:bg-amber-600'
          : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300'"
        @click="emit('regenerate')"
      >
        <AppIcon
          name="refresh"
          :size="15"
        />
        {{ regenerationAvailable ? 'Dữ liệu đã đổi — Tạo lại' : 'Tạo lại' }}
      </button>
    </div>
  </div>
</template>
