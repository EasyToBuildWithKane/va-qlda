<script setup>
import { computed, ref } from 'vue';
import AppIcon from '@/Components/AppIcon.vue';

const props = defineProps({
    report: { type: Object, required: true },
    processing: { type: Boolean, default: false },
    /** Nhúng cạnh tiêu đề — không bọc card riêng */
    inline: { type: Boolean, default: false },
});

const emit = defineEmits(['submit', 'approve', 'reject']);

const STEPS = [
    { key: 'draft', label: 'Nháp' },
    { key: 'generated', label: 'Tạo' },
    { key: 'edited', label: 'Chỉnh sửa' },
    { key: 'submitted', label: 'Chờ duyệt' },
    { key: 'approved', label: 'Đã duyệt' },
];

const statusValue = computed(() => props.report.status?.value);
const statusLabel = computed(() => {
    if (statusValue.value === 'rejected') return 'Bị trả lại';
    return STEPS.find((s) => s.key === statusValue.value)?.label ?? 'Nháp';
});

const can = computed(() => props.report.can ?? {});
const canSubmit = computed(() => can.value.submit && ['draft', 'generated', 'edited', 'rejected'].includes(statusValue.value));
const canApprove = computed(() => can.value.approve && statusValue.value === 'submitted');

const rejecting = ref(false);
const reason = ref('');

function doReject() {
    if (reason.value.trim().length < 3) return;
    emit('reject', reason.value.trim());
    rejecting.value = false;
    reason.value = '';
}
</script>

<template>
  <component
    :is="inline ? 'div' : 'section'"
    :class="inline
      ? 'min-w-0'
      : 'rounded-xl border border-slate-200 bg-white p-4 dark:border-slate-700 dark:bg-slate-900'"
  >
    <div
      class="flex flex-col gap-2"
      :class="inline
        ? 'xl:flex-row xl:flex-wrap xl:items-center xl:gap-x-3 xl:gap-y-2'
        : 'sm:flex-row sm:items-center sm:justify-between gap-3'"
    >
      <p
        class="text-sm font-medium text-slate-700 dark:text-slate-200"
        :class="inline ? 'xl:flex-1' : ''"
      >
        {{ statusLabel }}
      </p>

      <!-- Actions -->
      <div class="flex shrink-0 flex-wrap items-center gap-2">
        <button
          v-if="canSubmit"
          type="button"
          :disabled="processing"
          class="btn-primary inline-flex items-center gap-1.5 text-sm disabled:opacity-60"
          @click="emit('submit')"
        >
          <AppIcon
            name="send"
            :size="15"
          /> Gửi duyệt
        </button>
        <template v-if="canApprove">
          <button
            type="button"
            :disabled="processing"
            class="inline-flex items-center gap-1.5 rounded-lg border border-rose-200 bg-white px-3 py-1.5 text-sm font-medium text-rose-600 hover:bg-rose-50 disabled:opacity-60 dark:border-rose-900 dark:bg-slate-900"
            @click="rejecting = !rejecting"
          >
            <AppIcon
              name="close"
              :size="15"
            /> Trả lại
          </button>
          <button
            type="button"
            :disabled="processing"
            class="inline-flex items-center gap-1.5 rounded-lg bg-emerald-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-emerald-700 disabled:opacity-60"
            @click="emit('approve')"
          >
            <AppIcon
              name="check"
              :size="15"
            /> Duyệt
          </button>
        </template>
      </div>
    </div>

    <!-- Reject reason -->
    <div
      v-if="rejecting"
      class="flex flex-col gap-2 border-t border-slate-100 pt-3 dark:border-slate-800 sm:flex-row"
      :class="inline ? 'mt-2 w-full basis-full' : 'mt-3'"
    >
      <input
        v-model="reason"
        type="text"
        placeholder="Lý do trả lại báo cáo…"
        class="flex-1 rounded-lg border border-slate-300 bg-white px-3 py-1.5 text-sm focus:border-brand focus:ring-1 focus:ring-brand dark:border-slate-600 dark:bg-slate-800"
        @keyup.enter="doReject"
      >
      <button
        type="button"
        :disabled="reason.trim().length < 3 || processing"
        class="rounded-lg bg-rose-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-rose-700 disabled:opacity-50"
        @click="doReject"
      >
        Xác nhận trả lại
      </button>
    </div>

    <p
      v-if="statusValue === 'rejected' && report.reject_reason"
      class="mt-2 text-xs text-rose-600"
    >
      Lý do: {{ report.reject_reason }}
    </p>
  </component>
</template>
