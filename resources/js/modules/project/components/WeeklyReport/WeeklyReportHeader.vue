<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    report: { type: Object, required: true },
    canGenerate: { type: Boolean, default: false },
    regenerationAvailable: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['regenerate', 'export']);

const statusTone = {
    slate: 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300',
    sky: 'bg-sky-100 text-sky-700 dark:bg-sky-950 dark:text-sky-300',
    violet: 'bg-violet-100 text-violet-700 dark:bg-violet-950 dark:text-violet-300',
    amber: 'bg-amber-100 text-amber-700 dark:bg-amber-950 dark:text-amber-300',
    emerald: 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950 dark:text-emerald-300',
    rose: 'bg-rose-100 text-rose-700 dark:bg-rose-950 dark:text-rose-300',
};

function fmt(d) {
    if (!d) return '';
    const [y, m, day] = d.split('-');
    return `${day}/${m}/${y}`;
}

const period = computed(() => `${fmt(props.report.week_start)} – ${fmt(props.report.week_end)}`);
const updated = computed(() => {
    const iso = props.report.updated_at;
    if (!iso) return null;
    return new Date(iso).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
});
</script>

<template>
  <div class="flex flex-col gap-3 border-b border-slate-200 pb-4 dark:border-slate-700 sm:flex-row sm:items-start sm:justify-between">
    <div class="min-w-0">
      <div class="flex flex-wrap items-center gap-2">
        <h2 class="font-display text-lg font-semibold text-slate-900 dark:text-slate-50">
          Báo cáo tuần {{ report.week_number }}
        </h2>
        <span
          class="rounded-full px-2 py-0.5 text-[11px] font-semibold"
          :class="statusTone[report.status?.color] || statusTone.slate"
        >{{ report.status?.label }}</span>
        <span class="inline-flex items-center gap-1 rounded-full bg-slate-100 px-2 py-0.5 text-[11px] text-slate-500 dark:bg-slate-800">
          <AppIcon
            name="sparkles"
            :size="11"
          /> Tổng hợp tự động
        </span>
      </div>
      <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-500">
        <span v-if="report.sprint_name">{{ report.sprint_name }}</span>
        <span class="inline-flex items-center gap-1"><AppIcon
          name="calendar"
          :size="12"
        /> {{ period }}</span>
        <span v-if="updated">Cập nhật {{ updated }}</span>
        <span
          v-if="report.approved_by"
          class="text-emerald-600"
        >Duyệt bởi {{ report.approved_by }}</span>
      </div>
    </div>

    <div class="flex shrink-0 items-center gap-2">
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-lg border border-slate-200 bg-white px-3 py-1.5 text-sm font-medium text-slate-600 transition hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-900 dark:text-slate-300"
        @click="emit('export')"
      >
        <AppIcon
          name="export"
          :size="15"
        /> Xuất
      </button>
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
