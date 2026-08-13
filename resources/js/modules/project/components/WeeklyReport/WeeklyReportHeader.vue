<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';
import DatagridToolbarActionButton from '@/shared/ui/DatagridToolbarActionButton.vue';
import WeeklyReportApprovalBar from './WeeklyReportApprovalBar.vue';

const props = defineProps({
    report: { type: Object, required: true },
    canGenerate: { type: Boolean, default: false },
    regenerationAvailable: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['regenerate', 'export', 'submit', 'approve', 'reject']);

const exportOpen = ref(false);
const exportWrap = ref(null);

function pick(format) {
    exportOpen.value = false;
    emit('export', format);
}

function onExportClickOutside(e) {
    if (exportWrap.value && !exportWrap.value.contains(e.target)) {
        exportOpen.value = false;
    }
}

onMounted(() => document.addEventListener('mousedown', onExportClickOutside));
onBeforeUnmount(() => document.removeEventListener('mousedown', onExportClickOutside));

const updated = computed(() => {
    const iso = props.report.updated_at;
    if (!iso) return null;
    return new Date(iso).toLocaleString('vi-VN', { hour: '2-digit', minute: '2-digit', day: '2-digit', month: '2-digit' });
});

const engine = computed(() => props.report.meta?.engine ?? 'heuristic');
const engineLabel = computed(() => {
    if (engine.value === 'llm') return 'AI đã tổng hợp';
    if (engine.value === 'heuristic_fallback') return 'AI lỗi — bản nội bộ';
    return 'Tổng hợp nội bộ';
});
const engineClass = computed(() => {
    if (engine.value === 'llm') return 'text-emerald-600';
    if (engine.value === 'heuristic_fallback') return 'text-amber-600';
    return 'text-slate-500';
});
</script>

<template>
  <div class="flex min-w-0 flex-1 flex-wrap items-center gap-x-3 gap-y-2 lg:flex-nowrap">
    <WeeklyReportApprovalBar
      inline
      :report="report"
      :processing="processing"
      @submit="emit('submit')"
      @approve="emit('approve')"
      @reject="emit('reject', $event)"
    />

    <div
      v-if="updated || report.approved_by || engine"
      class="hidden min-w-0 items-center gap-2 text-[11px] tabular-nums sm:flex"
    >
      <span
        class="shrink-0 whitespace-nowrap"
        :class="engineClass"
        :title="report.meta?.llm_error_message || undefined"
      >{{ engineLabel }}</span>
      <span
        v-if="updated"
        class="shrink-0 whitespace-nowrap text-slate-400"
      >Cập nhật {{ updated }}</span>
      <span
        v-if="report.approved_by"
        class="shrink-0 whitespace-nowrap text-emerald-600"
      >Duyệt bởi {{ report.approved_by }}</span>
    </div>

    <div class="ml-auto flex shrink-0 items-center gap-2">
      <div
        ref="exportWrap"
        class="relative"
      >
        <DatagridToolbarActionButton
          icon="export"
          title="Xuất báo cáo"
          :active="exportOpen"
          @click="exportOpen = !exportOpen"
        >
          Xuất
        </DatagridToolbarActionButton>
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
        class="inline-flex h-10 items-center gap-1.5 rounded-btn px-3 text-xs font-medium transition disabled:opacity-60"
        :class="regenerationAvailable
          ? 'bg-amber-500 text-white hover:bg-amber-600'
          : 'border border-slate-200 bg-white text-slate-600 hover:bg-slate-50 dark:border-slate-700 dark:bg-slate-800 dark:text-slate-300'"
        :title="regenerationAvailable ? 'Dữ liệu kỳ đã đổi — tạo lại toàn bộ báo cáo' : 'Tạo lại toàn bộ báo cáo theo prompt AI hiện tại'"
        @click="emit('regenerate')"
      >
        <AppIcon
          name="refresh"
          :size="15"
        />
        Tạo lại
      </button>
    </div>
  </div>
</template>
