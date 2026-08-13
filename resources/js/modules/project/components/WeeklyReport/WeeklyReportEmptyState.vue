<script setup>
import { computed } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    periodStart: { type: String, default: '' },
    periodEnd: { type: String, default: '' },
    canGenerate: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['generate']);

function fmt(iso) {
    if (!iso) return '';
    const [y, m, d] = iso.split('-');
    return y && m && d ? `${d}/${m}/${y}` : '';
}

const rangeLabel = computed(() => {
    const from = fmt(props.periodStart);
    const to = fmt(props.periodEnd);
    if (from && to) return `từ ${from} đến ${to}`;
    return 'khoảng ngày đã chọn';
});
</script>

<template>
  <div class="flex h-full min-h-[280px] flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand">
      <AppIcon
        name="weekly"
        :size="26"
      />
    </span>
    <h3 class="mt-4 font-display text-base font-semibold text-slate-800 dark:text-slate-100">
      Chưa có báo cáo {{ rangeLabel }}
    </h3>
    <p class="mt-1.5 max-w-sm text-sm text-slate-500">
      Chọn ngày bắt đầu và ngày kết thúc phía trên, rồi tạo báo cáo cho kỳ đó.
    </p>
    <button
      v-if="canGenerate"
      type="button"
      :disabled="processing || !periodStart || !periodEnd"
      class="btn-primary mt-5 inline-flex items-center gap-2 text-sm disabled:opacity-60"
      @click="emit('generate')"
    >
      <AppIcon
        name="sparkles"
        :size="16"
      />
      {{ processing ? 'Đang tổng hợp…' : 'Tạo báo cáo' }}
    </button>
    <p
      v-else
      class="mt-5 text-sm italic text-slate-400"
    >
      Bạn không có quyền tạo báo cáo cho dự án này.
    </p>
  </div>
</template>
