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
const activeIndex = computed(() => {
    const v = statusValue.value;
    if (v === 'rejected') return 0;
    const i = STEPS.findIndex((s) => s.key === v);
    return i === -1 ? 0 : i;
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
      <!-- Workflow steps -->
      <ol
        class="flex min-w-0 flex-wrap items-center gap-x-1.5 gap-y-1 text-xs"
        :class="inline ? 'xl:flex-1' : ''"
      >
        <li
          v-for="(s, i) in STEPS"
          :key="s.key"
          class="flex items-center gap-1.5"
        >
          <span
            class="inline-flex h-5 w-5 items-center justify-center rounded-full text-[10px] font-semibold"
            :class="i <= activeIndex
              ? 'bg-brand text-white'
              : 'bg-slate-200 text-slate-500 dark:bg-slate-700'"
          >
            <AppIcon
              v-if="i < activeIndex"
              name="check"
              :size="11"
            />
            <template v-else>{{ i + 1 }}</template>
          </span>
          <span :class="i <= activeIndex ? 'font-medium text-slate-700 dark:text-slate-200' : 'text-slate-400'">{{ s.label }}</span>
          <AppIcon
            v-if="i < STEPS.length - 1"
            name="chevron-right"
            :size="12"
            class="text-slate-300"
          />
        </li>
      </ol>

      <!-- Actions -->
      <div class="flex shrink-0 flex-wrap items-center gap-2">
        <span
          v-if="statusValue === 'rejected'"
          class="rounded-full bg-rose-100 px-2 py-0.5 text-[11px] font-medium text-rose-700 dark:bg-rose-950 dark:text-rose-300"
        >Bị trả lại</span>
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
