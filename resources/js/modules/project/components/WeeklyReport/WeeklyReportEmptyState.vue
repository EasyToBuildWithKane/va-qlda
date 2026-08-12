<script setup>
import AppIcon from '@/Components/AppIcon.vue';

defineProps({
    weekNumber: { type: [Number, null], default: null },
    canGenerate: { type: Boolean, default: false },
    processing: { type: Boolean, default: false },
});

const emit = defineEmits(['generate']);
</script>

<template>
  <div class="flex h-full min-h-[320px] flex-col items-center justify-center rounded-xl border border-dashed border-slate-300 bg-white p-8 text-center dark:border-slate-700 dark:bg-slate-900">
    <span class="inline-flex h-14 w-14 items-center justify-center rounded-2xl bg-brand/10 text-brand">
      <AppIcon
        name="weekly"
        :size="26"
      />
    </span>
    <h3 class="mt-4 font-display text-base font-semibold text-slate-800 dark:text-slate-100">
      Chưa có báo cáo cho {{ weekNumber ? `tuần ${weekNumber}` : 'tuần này' }}
    </h3>
    <p class="mt-1 max-w-md text-sm text-slate-500">
      Hệ thống sẽ tự tổng hợp Tasks, Worklog, Vướng mắc và Phản hồi của Sprint thành báo cáo quản trị.
      Bạn chỉ cần kiểm tra, chỉnh sửa và gửi duyệt.
    </p>
    <button
      v-if="canGenerate"
      type="button"
      :disabled="processing || !weekNumber"
      class="btn-primary mt-5 inline-flex items-center gap-2 text-sm disabled:opacity-60"
      @click="emit('generate', weekNumber)"
    >
      <AppIcon
        name="sparkles"
        :size="16"
      />
      {{ processing ? 'Đang tổng hợp…' : 'Tạo báo cáo tự động' }}
    </button>
    <p
      v-else
      class="mt-5 text-sm italic text-slate-400"
    >
      Bạn không có quyền tạo báo cáo cho dự án này.
    </p>
  </div>
</template>
